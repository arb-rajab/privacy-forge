<?php

use App\Models\Connector;
use App\Models\ConsentPurpose;
use App\Models\DataCategory;
use App\Models\PolicyDefinition;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Demo Instance Data Safety, control 1 (docs/project-memory/
// 06-security-threat-model.md) — designed at Session 4, built this
// session (Part B groundwork, docs/project-memory/12-session-handoff.md).
// The one thing this test suite cares about most: the command must be
// inert on a real (non-demo) instance, since routes/console.php registers
// its scheduler entry unconditionally.

test('refuses to run when DEMO_MODE is not enabled, and touches nothing', function () {
    config(['demo.enabled' => false]);
    ConsentPurpose::factory()->create();

    $this->artisan('demo:reset')->assertFailed();

    expect(ConsentPurpose::query()->count())->toBe(1);
});

test('when enabled, wipes subject/activity data and re-seeds the ABAC policies and reference connector', function () {
    config(['demo.enabled' => true]);

    ConsentPurpose::factory()->create();
    DataCategory::factory()->create();
    PolicyDefinition::factory()->create(['action_name' => 'some.leftover.policy']);
    Connector::factory()->create(['webhook_url' => 'https://leftover-connector.example.test/webhook']);
    $owner = User::factory()->owner()->create();
    app(AuditLogger::class)->record(actorType: 'staff', actor: $owner, action: 'test.action', resourceType: 'test_resource', resourceId: (string) Str::uuid());

    $this->artisan('demo:reset')
        ->expectsOutputToContain('Demo instance reset')
        ->assertSuccessful();

    expect(ConsentPurpose::query()->count())->toBe(0);
    expect(DataCategory::query()->count())->toBe(0);
    expect(DB::table('audit_log_entries')->count())->toBe(0);

    // Users are deliberately untouched — see the command's class comment
    // on why (no scoped per-visitor demo identity exists yet).
    expect(User::query()->whereKey($owner->id)->exists())->toBeTrue();

    // Re-seeded to the standard fresh-install baseline: exactly the five
    // real sensitive-action policies, not the leftover test row above.
    expect(PolicyDefinition::query()->where('action_name', 'some.leftover.policy')->exists())->toBeFalse();
    expect(PolicyDefinition::query()->where('action_name', 'retention.policy.manage')->exists())->toBeTrue();
    expect(PolicyDefinition::query()->where('action_name', 'audit.log.view')->exists())->toBeTrue();

    // Exactly one connector — the freshly re-registered reference/stub
    // connector, per Demo Instance Data Safety control 3 — not the
    // leftover connector created above (distinguished by webhook_url,
    // since both share the same default display name).
    expect(Connector::query()->count())->toBe(1);
    expect(Connector::query()->first()->webhook_url)->not->toBe('https://leftover-connector.example.test/webhook');
});

test('when enabled, the audit chain sequence restarts at genesis for the next write', function () {
    config(['demo.enabled' => true]);

    $owner = User::factory()->owner()->create();
    app(AuditLogger::class)->record(actorType: 'staff', actor: $owner, action: 'test.action', resourceType: 'test_resource', resourceId: (string) Str::uuid());

    $this->artisan('demo:reset')->assertSuccessful();

    $newOwner = User::factory()->owner()->create();
    $entry = app(AuditLogger::class)->record(actorType: 'staff', actor: $newOwner, action: 'test.action.after-reset', resourceType: 'test_resource', resourceId: (string) Str::uuid());

    // sequence is a DB-generated default (nextval()), not returned by a
    // plain insert — refetch to see the value Postgres actually assigned.
    expect($entry->fresh()->sequence)->toBe(1);
    expect($entry->prev_hash)->toBe(AuditLogger::genesisHash());
});
