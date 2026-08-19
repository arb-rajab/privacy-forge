<?php

use App\Models\Connector;
use App\Models\ConsentPurpose;
use App\Models\DataCategory;
use App\Models\PolicyDefinition;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

    // R-01: demo:reset's TRUNCATE runs via the schema-owning `pgsql_migrate`
    // connection (a genuinely different Postgres session from the app's
    // default connection), because the app's normal runtime role no
    // longer has TRUNCATE on audit_log_entries. RefreshDatabase wraps this
    // whole test in one still-open transaction on the default connection;
    // without committing it first, the rows just inserted above are still
    // only tentatively locked by that session, and TRUNCATE from the other
    // session would block on them forever — a real Postgres lock wait, not
    // a bug in this test, but also not something that can happen in real
    // usage (a scheduled demo:reset never runs inside another request's
    // still-open transaction). Committing here makes the test match that
    // real-world ordering instead of an artifact of RefreshDatabase.
    DB::commit();

    $this->artisan('demo:reset')
        ->expectsOutputToContain('Demo instance reset')
        ->assertSuccessful();

    expect(ConsentPurpose::query()->count())->toBe(0);
    expect(DataCategory::query()->count())->toBe(0);
    expect(DB::table('audit_log_entries')->count())->toBe(0);

    // B-08 (Session 24): users is now truncated too — the pre-existing
    // owner from before the reset is gone, replaced by exactly one
    // fixed, documented demo-viewer account (config('demo.viewer_email')).
    expect(User::query()->whereKey($owner->id)->exists())->toBeFalse();
    expect(User::query()->count())->toBe(1);

    $viewer = User::query()->first();
    expect($viewer->email)->toBe(config('demo.viewer_email'));
    expect($viewer->role)->toBe('owner');
    expect(Hash::check(config('demo.viewer_password'), $viewer->password))->toBeTrue();

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

test('when enabled, the demo-viewer account is recreated fresh even if it existed before the reset', function () {
    config(['demo.enabled' => true]);

    // Simulate a visitor who changed nothing, plus a prior reset's own
    // viewer account left over — the new reset must still end with
    // exactly one viewer, at the documented credentials, not two rows
    // or a stale password.
    User::factory()->create([
        'email' => config('demo.viewer_email'),
        'role' => 'support_staff',
        'password' => Hash::make('a-different-stale-password'),
    ]);

    // R-01: see the previous test's comment — demo:reset's TRUNCATE runs
    // via a different connection than this test's default one, and would
    // otherwise deadlock against RefreshDatabase's still-open transaction.
    DB::commit();

    $this->artisan('demo:reset')->assertSuccessful();

    expect(User::query()->count())->toBe(1);
    $viewer = User::query()->first();
    expect($viewer->role)->toBe('owner');
    expect(Hash::check(config('demo.viewer_password'), $viewer->password))->toBeTrue();
});

test('when enabled, the audit chain sequence restarts at genesis for the next write', function () {
    config(['demo.enabled' => true]);

    $owner = User::factory()->owner()->create();
    app(AuditLogger::class)->record(actorType: 'staff', actor: $owner, action: 'test.action', resourceType: 'test_resource', resourceId: (string) Str::uuid());

    // R-01: see the first test's comment in this file.
    DB::commit();

    $this->artisan('demo:reset')->assertSuccessful();

    $newOwner = User::factory()->owner()->create();
    $entry = app(AuditLogger::class)->record(actorType: 'staff', actor: $newOwner, action: 'test.action.after-reset', resourceType: 'test_resource', resourceId: (string) Str::uuid());

    // sequence is a DB-generated default (nextval()), not returned by a
    // plain insert — refetch to see the value Postgres actually assigned.
    expect($entry->fresh()->sequence)->toBe(1);
    expect($entry->prev_hash)->toBe(AuditLogger::genesisHash());
});
