<?php

use App\Console\Commands\AnchorAuditChainCommand;
use App\Models\AuditLogEntry;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// R-04/ADR-0003: anchorChain()/verifyAnchors() exist specifically to
// catch what verifyChain() alone cannot — an attacker with direct
// database access who edits an old entry *and* recomputes every
// subsequent prev_hash/entry_hash so the chain looks internally
// consistent again. This test performs that exact rewrite (bypassing
// AuditLogEntry's append-only guard via the schema-owning connection,
// the same technique ConsentCaptureTest already uses to simulate DB-
// level tampering) and proves verifyChain() is fooled while
// verifyAnchors() is not. It must use the owning connection, not the
// app's default one: R-01 means the app's own runtime role genuinely
// cannot UPDATE audit_log_entries any more (AuditLogGrantEnforcementTest)
// — which is realistic here too, since the attacker this test models
// has direct/elevated DB access, not the app's restricted credential.
test('verifyAnchors detects a full chain rewrite that verifyChain alone cannot', function () {
    Storage::fake('s3');

    $logger = app(AuditLogger::class);
    $actor = User::factory()->owner()->create();

    foreach (range(1, 3) as $i) {
        $logger->record(
            actorType: 'staff',
            actor: $actor,
            action: "test.action.{$i}",
            resourceType: 'test_resource',
            resourceId: (string) Str::uuid(),
        );
    }

    // The anchor covers the *latest* entry (sequence 3) — the tampering
    // below targets the *first* entry instead, deliberately: the attack
    // this test simulates is editing an old entry and recomputing every
    // hash after it, so the mismatch anchoring catches shows up at the
    // anchored (later) sequence, not at the tampered (earlier) one.
    $anchorResult = $logger->anchorChain();
    expect($anchorResult['anchored'])->toBeTrue();

    $entries = AuditLogEntry::query()->orderBy('sequence')->get();
    $tamperedEntry = $entries->first();

    // R-01: the entries above were written via the app's default
    // connection inside RefreshDatabase's still-open transaction —
    // genuinely uncommitted from any other Postgres session's point of
    // view, including `pgsql_migrate`'s. Without committing here first,
    // the UPDATEs below would silently match zero rows (not error, just
    // no-op — verified directly: an uncommitted insert on one connection
    // is invisible to another), and this test would pass for the wrong
    // reason. This mirrors real usage: a genuine attacker rewriting old
    // entries would be acting on already-committed rows, never ones
    // still inside another session's open transaction.
    DB::commit();

    // Rewrite the tampered entry's action, then recompute prev_hash/
    // entry_hash for it and every entry after it, exactly as an attacker
    // with real DB access and knowledge of the (documented) hash formula
    // would have to. This is what makes the rewrite internally
    // consistent — and exactly what an anchor written *before* the
    // rewrite is meant to catch.
    $prevHash = $tamperedEntry->prev_hash;

    foreach ($entries as $entry) {
        $payload = [
            'actor_user_id' => $entry->actor_user_id,
            'actor_type' => $entry->actor_type,
            'action' => $entry->id === $tamperedEntry->id ? 'test.action.tampered' : $entry->action,
            'resource_type' => $entry->resource_type,
            'resource_id' => $entry->resource_id,
            'policy_id' => $entry->policy_id,
            'decision' => $entry->decision,
            'reason_code' => $entry->reason_code,
        ];

        $newHash = hash('sha256', $prevHash.json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        DB::connection('pgsql_migrate')->table('audit_log_entries')->where('id', $entry->id)->update([
            'action' => $payload['action'],
            'prev_hash' => $prevHash,
            'entry_hash' => $newHash,
        ]);

        $prevHash = $newHash;
    }

    expect($logger->verifyChain()['valid'])
        ->toBeTrue('the rewritten chain should be internally consistent — this is the exact gap anchoring closes');

    $anchorCheck = $logger->verifyAnchors();
    expect($anchorCheck['valid'])->toBeFalse();
    expect($anchorCheck['brokenAtSequence'])->toBe($anchorResult['sequence']);
});

test('anchorChain reports no_entries on a fresh instance rather than anchoring nothing', function () {
    Storage::fake('s3');

    expect(app(AuditLogger::class)->anchorChain())->toBe([
        'anchored' => false,
        'reason' => 'no_entries',
        'sequence' => null,
        'entry_hash' => null,
        'entry_id' => null,
    ]);
});

test('anchorChain is idempotent when the chain has not grown since the last anchor', function () {
    Storage::fake('s3');

    $logger = app(AuditLogger::class);
    $logger->record(actorType: 'system', actor: null, action: 'test.action', resourceType: 'test_resource', resourceId: (string) Str::uuid());

    $first = $logger->anchorChain();
    $second = $logger->anchorChain();

    expect($first)->toBe($second);
    expect(Storage::disk('s3')->files(AuditLogger::ANCHOR_PATH_PREFIX))->toHaveCount(1);
});

test('AnchorAuditChainCommand anchors the chain and records its own audit entry', function () {
    Storage::fake('s3');

    $logger = app(AuditLogger::class);
    $logger->record(actorType: 'system', actor: null, action: 'test.action', resourceType: 'test_resource', resourceId: (string) Str::uuid());

    $this->artisan(AnchorAuditChainCommand::class)->assertExitCode(0);

    $latest = AuditLogEntry::query()->orderByDesc('sequence')->first();
    expect($latest->action)->toBe('audit.chain.anchored');
    expect($logger->verifyChain()['valid'])->toBeTrue();
});

test('AnchorAuditChainCommand alerts rather than fails silently when the external write fails', function () {
    Storage::fake('s3');

    $logger = app(AuditLogger::class);
    $logger->record(actorType: 'system', actor: null, action: 'test.action', resourceType: 'test_resource', resourceId: (string) Str::uuid());

    // Simulate the anchor's external storage being unreachable — a bare
    // stub is enough since AnchorAuditChainCommand only ever calls put().
    Storage::set('s3', new class
    {
        public function put(): bool
        {
            return false;
        }
    });

    Log::spy();

    $this->artisan(AnchorAuditChainCommand::class)->assertExitCode(1);

    Log::shouldHaveReceived('critical')->once();
});
