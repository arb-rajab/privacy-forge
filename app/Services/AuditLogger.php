<?php

namespace App\Services;

use App\Models\AuditLogEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// Hash-chain half of ADR-0003 (docs/adr/ADR-0003-audit-log-tamper-evidence.md).
// This is the *only* supported way to create an AuditLogEntry — it's what
// computes prev_hash/entry_hash correctly. The DB-grant half of that ADR
// (R-01) is not implemented here; see docs/project-memory/12-session-
// handoff.md for why (the app's DB role also owns the audit_log_entries
// table in the current docker-compose/CI setup, so a bare REVOKE would be
// a no-op against that role). The periodic external anchor (R-04) *is*
// implemented here — see anchorChain()/verifyAnchors() below.
class AuditLogger
{
    public const GENESIS_HASH_CHAR = '0';

    // R-04/ADR-0003: anchors live on the 's3' disk (the same external
    // object storage export bundles already use — no new infrastructure
    // dependency) rather than in this application's own Postgres
    // database. That distinction is the entire point: ADR-0003's threat
    // model is an attacker who has compromised DB credentials and can
    // therefore edit any row *and* recompute every subsequent hash to
    // keep verifyChain() passing. An anchor stored in that same database
    // would be just as editable by that attacker, defeating the purpose.
    // Each anchor is written to a path keyed by the sequence it covers
    // and this class never issues a write to an already-anchored key —
    // append-only by construction, not by a storage-level lock (an
    // accepted limitation stated in ADR-0003's Consequences: this proves
    // tamper *evidence*, not tamper *impossibility*).
    public const ANCHOR_DISK = 's3';

    public const ANCHOR_PATH_PREFIX = 'audit-anchors/';

    public static function genesisHash(): string
    {
        return str_repeat(self::GENESIS_HASH_CHAR, 64);
    }

    public function record(
        string $actorType,
        ?User $actor,
        string $action,
        string $resourceType,
        string $resourceId,
        string $decision = 'allow',
        ?string $policyId = null,
        ?string $reasonCode = null,
    ): AuditLogEntry {
        return DB::transaction(function () use ($actorType, $actor, $action, $resourceType, $resourceId, $decision, $policyId, $reasonCode) {
            $previous = AuditLogEntry::query()->orderByDesc('sequence')->lockForUpdate()->first();
            $prevHash = $previous === null ? self::genesisHash() : $previous->entry_hash;

            $payload = [
                'actor_user_id' => $actor?->id,
                'actor_type' => $actorType,
                'action' => $action,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'policy_id' => $policyId,
                'decision' => $decision,
                'reason_code' => $reasonCode,
            ];

            $entryHash = self::computeHash($prevHash, $payload);

            return AuditLogEntry::create([
                ...$payload,
                'prev_hash' => $prevHash,
                'entry_hash' => $entryHash,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Replay the entire chain in write order and confirm every stored
     * hash matches its recomputed value. Returns ['valid' => bool,
     * 'brokenAtSequence' => int|null] — the first sequence number at
     * which the chain no longer verifies, per US-014's acceptance
     * criterion that tampering must be identifiable, not just detectable.
     *
     * @return array{valid: bool, brokenAtSequence: int|null}
     */
    public function verifyChain(): array
    {
        $expectedPrevHash = self::genesisHash();

        foreach (AuditLogEntry::query()->orderBy('sequence')->cursor() as $entry) {
            if ($entry->prev_hash !== $expectedPrevHash) {
                return ['valid' => false, 'brokenAtSequence' => $entry->sequence];
            }

            $payload = [
                'actor_user_id' => $entry->actor_user_id,
                'actor_type' => $entry->actor_type,
                'action' => $entry->action,
                'resource_type' => $entry->resource_type,
                'resource_id' => $entry->resource_id,
                'policy_id' => $entry->policy_id,
                'decision' => $entry->decision,
                'reason_code' => $entry->reason_code,
            ];

            if (self::computeHash($entry->prev_hash, $payload) !== $entry->entry_hash) {
                return ['valid' => false, 'brokenAtSequence' => $entry->sequence];
            }

            $expectedPrevHash = $entry->entry_hash;
        }

        return ['valid' => true, 'brokenAtSequence' => null];
    }

    /**
     * Anchor the current chain head (the latest entry's sequence + hash)
     * to external storage. Idempotent: re-anchoring the same head writes
     * identical content to the same key, so running this more often than
     * the chain actually grows is harmless, not a duplicate-anchor bug.
     *
     * @return array{anchored: bool, reason: string|null, sequence: int|null, entry_hash: string|null, entry_id: string|null}
     */
    public function anchorChain(): array
    {
        $latest = AuditLogEntry::query()->orderByDesc('sequence')->first();

        if ($latest === null) {
            return ['anchored' => false, 'reason' => 'no_entries', 'sequence' => null, 'entry_hash' => null, 'entry_id' => null];
        }

        $anchor = [
            'sequence' => $latest->sequence,
            'entry_hash' => $latest->entry_hash,
            'anchored_at' => now()->toIso8601String(),
        ];

        $written = Storage::disk(self::ANCHOR_DISK)->put(
            self::ANCHOR_PATH_PREFIX.$latest->sequence.'.json',
            json_encode($anchor, JSON_THROW_ON_ERROR),
        );

        if (! $written) {
            return ['anchored' => false, 'reason' => 'storage_write_failed', 'sequence' => $latest->sequence, 'entry_hash' => null, 'entry_id' => null];
        }

        return [
            'anchored' => true,
            'reason' => null,
            'sequence' => $latest->sequence,
            'entry_hash' => $latest->entry_hash,
            'entry_id' => $latest->id,
        ];
    }

    /**
     * Replay every anchor ever written and confirm the sequence it names
     * still has the same entry_hash in the live database *today*. This is
     * the check verifyChain() cannot do on its own: an attacker who edits
     * an old entry and recomputes every subsequent prev_hash/entry_hash
     * (a full chain rewrite) makes verifyChain() pass again, because that
     * method only replays the chain as it currently stands — it has no
     * memory of what the chain looked like before the rewrite. Anchors
     * are that memory, held outside the database the attacker compromised.
     *
     * @return array{valid: bool, brokenAtSequence: int|null, checkedAnchors: int}
     */
    public function verifyAnchors(): array
    {
        $checked = 0;

        foreach (Storage::disk(self::ANCHOR_DISK)->files(self::ANCHOR_PATH_PREFIX) as $file) {
            $contents = Storage::disk(self::ANCHOR_DISK)->get($file);

            if ($contents === null) {
                return ['valid' => false, 'brokenAtSequence' => null, 'checkedAnchors' => $checked];
            }

            /** @var array{sequence: int, entry_hash: string, anchored_at: string} $anchor */
            $anchor = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

            $entry = AuditLogEntry::query()->where('sequence', $anchor['sequence'])->first();

            if ($entry === null || $entry->entry_hash !== $anchor['entry_hash']) {
                return ['valid' => false, 'brokenAtSequence' => $anchor['sequence'], 'checkedAnchors' => $checked];
            }

            $checked++;
        }

        return ['valid' => true, 'brokenAtSequence' => null, 'checkedAnchors' => $checked];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function computeHash(string $prevHash, array $payload): string
    {
        return hash('sha256', $prevHash.json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
