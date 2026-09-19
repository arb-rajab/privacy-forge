<?php

use App\Models\PolicyDefinition;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

// R-09 (docs/project-memory/10-risk-register.md) / ADR-0009: proves the
// T-16 fix is real. Every statement below is raw SQL issued through the
// connection the running application actually uses
// (config('database.default'), the same `pgsql` connection every
// controller/service in this app connects through — no test-only
// elevated credential) — specifically to rule out "the app just chooses
// not to issue this query" as the reason it fails, the same standard
// AuditLogGrantEnforcementTest.php (R-01) already holds itself to.
//
// Before ADR-0009's migration existed, every assertion in the first two
// tests below failed: R-01's own ALTER DEFAULT PRIVILEGES grants
// SELECT/INSERT/UPDATE/DELETE on every table to this role by default, so
// policy_definitions had no restriction at all — an UPDATE against
// `effect` (or any other column) and a DELETE both succeeded silently.
// That is the exact gap T-16 named and 06-security-threat-model.md had
// fabricated a fix for. Confirmed directly this session by running this
// file against the schema without the new migration applied before
// writing it, not assumed from reading the grant SQL alone.
it('rejects a raw SQL UPDATE of a policy_definitions row\'s effect at the Postgres level', function () {
    $policy = PolicyDefinition::factory()->create(['effect' => 'allow']);

    $threw = false;

    try {
        DB::transaction(function () use ($policy) {
            DB::statement('UPDATE policy_definitions SET effect = ? WHERE id = ?', ['deny', $policy->id]);
        });
    } catch (QueryException $e) {
        $threw = true;
        expect($e->getCode())->toBe('42501'); // insufficient_privilege
        expect($e->getMessage())->toContain('permission denied');
    }

    expect($threw)->toBeTrue('Expected Postgres to reject a raw UPDATE of `effect`; the app runtime role must not have column-wide UPDATE on policy_definitions.');
    expect(PolicyDefinition::query()->find($policy->id)->effect)->toBe('allow');
});

it('rejects a raw SQL DELETE against policy_definitions at the Postgres level', function () {
    $policy = PolicyDefinition::factory()->create();

    $threw = false;

    try {
        DB::transaction(function () use ($policy) {
            DB::statement('DELETE FROM policy_definitions WHERE id = ?', [$policy->id]);
        });
    } catch (QueryException $e) {
        $threw = true;
        expect($e->getCode())->toBe('42501');
        expect($e->getMessage())->toContain('permission denied');
    }

    expect($threw)->toBeTrue('Expected Postgres to reject the DELETE; the app runtime role must not have DELETE on policy_definitions.');
    expect(PolicyDefinition::query()->find($policy->id))->not->toBeNull();
});

it('still allows a raw SQL UPDATE of only status/updated_at, matching what PolicyController::update() actually does', function () {
    // Positive control: ADR-0009 narrows the grant, it must not
    // accidentally take away the one column the versioning workflow
    // (PolicyController::update()'s supersede step) genuinely needs to
    // keep working.
    $policy = PolicyDefinition::factory()->create(['status' => 'active']);

    DB::statement('UPDATE policy_definitions SET status = ? WHERE id = ?', ['superseded', $policy->id]);

    expect($policy->fresh()->status)->toBe('superseded');
});

it('still allows SELECT and INSERT against policy_definitions for the app runtime role', function () {
    $policy = PolicyDefinition::factory()->create();

    expect(PolicyDefinition::query()->find($policy->id))->not->toBeNull();
    expect(DB::table('policy_definitions')->where('id', $policy->id)->exists())->toBeTrue();

    $newVersion = PolicyDefinition::create([
        'action_name' => $policy->action_name,
        'version' => $policy->version + 1,
        'subject_conditions' => [],
        'resource_conditions' => [],
        'environment_conditions' => [],
        'effect' => 'allow',
        'status' => 'active',
    ]);

    expect($newVersion->exists)->toBeTrue();
});
