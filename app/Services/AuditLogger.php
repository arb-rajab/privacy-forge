<?php

namespace App\Services;

use App\Models\AuditLogEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// Hash-chain half of ADR-0003 (docs/adr/ADR-0003-audit-log-tamper-evidence.md).
// This is the *only* supported way to create an AuditLogEntry — it's what
// computes prev_hash/entry_hash correctly. The DB-grant half of that ADR
// and the periodic external anchor are not implemented here; see
// docs/project-memory/12-session-handoff.md for why (the app's DB role
// also owns the audit_log_entries table in the current docker-compose/CI
// setup, so a bare REVOKE would be a no-op against that role) and to
// confirm anchoring's Session 8 placement is unchanged.
class AuditLogger
{
    public const GENESIS_HASH_CHAR = '0';

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
    ): AuditLogEntry {
        return DB::transaction(function () use ($actorType, $actor, $action, $resourceType, $resourceId, $decision, $policyId) {
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
            ];

            if (self::computeHash($entry->prev_hash, $payload) !== $entry->entry_hash) {
                return ['valid' => false, 'brokenAtSequence' => $entry->sequence];
            }

            $expectedPrevHash = $entry->entry_hash;
        }

        return ['valid' => true, 'brokenAtSequence' => null];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function computeHash(string $prevHash, array $payload): string
    {
        return hash('sha256', $prevHash.json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
