<?php

use App\Models\AuditLogEntry;
use App\Models\PolicyDefinition;
use App\Models\User;

// Session 10 — policy.update (ADR-0006), closing R-03
// (docs/project-memory/10-risk-register.md): ADR-0006 named this action
// as Owner-only, audit-logged, and gated through PolicyEvaluator, but as
// of Session 9's NFR-005 matrix nothing implemented it. Same pattern as
// DsarIdentityVerificationTest.php/DsarErasureApprovalTest.php — the gate
// is App\Services\PolicyEvaluator, not a role check, so it needs its own
// fail-closed fault-injection coverage exactly like those two actions.
// The (role x policy.update) matrix cells live in AuthorisationMatrixTest.php
// (PATCH is the representative endpoint there); this file covers what
// that matrix deliberately doesn't: index/show sharing the same gate,
// versioning-on-update, and both fail-closed reason codes.

test('an owner can list policy definitions', function () {
    PolicyDefinition::factory()->forPolicyUpdate()->create();
    PolicyDefinition::factory()->create(); // dsar.identity.verify
    $owner = User::factory()->owner()->create();

    $response = $this->actingAs($owner)->getJson('/api/v1/admin/policies');

    $response->assertStatus(200);
    expect($response->json())->toHaveCount(2);
});

test('an owner can view a single policy definition', function () {
    PolicyDefinition::factory()->forPolicyUpdate()->create();
    $target = PolicyDefinition::factory()->create();
    $owner = User::factory()->owner()->create();

    $response = $this->actingAs($owner)->getJson("/api/v1/admin/policies/{$target->id}");

    $response->assertStatus(200)->assertJson([
        'id' => $target->id,
        'action_name' => 'dsar.identity.verify',
    ]);
});

test('an owner can update a policy definition, superseding the old version and logging the allow decision with a policy id', function () {
    $gate = PolicyDefinition::factory()->forPolicyUpdate()->create();
    $target = PolicyDefinition::factory()->create(); // dsar.identity.verify, v1
    $owner = User::factory()->owner()->create();

    $response = $this->actingAs($owner)->patchJson("/api/v1/admin/policies/{$target->id}", [
        'subject_conditions' => ['role' => ['in' => ['owner']]],
        'resource_conditions' => [],
        'environment_conditions' => [],
        'effect' => 'allow',
    ]);

    $response->assertStatus(200);
    expect($response->json('version'))->toBe(2);
    expect($response->json('action_name'))->toBe('dsar.identity.verify');

    expect($target->fresh()->status)->toBe('superseded');

    $newRow = PolicyDefinition::query()
        ->where('action_name', 'dsar.identity.verify')
        ->where('status', 'active')
        ->first();

    expect($newRow)->not->toBeNull();
    expect($newRow->version)->toBe(2);
    expect($newRow->subject_conditions)->toBe(['role' => ['in' => ['owner']]]);

    $entry = AuditLogEntry::query()
        ->where('action', 'policy.update')
        ->where('resource_id', $target->id)
        ->first();

    expect($entry)->not->toBeNull();
    expect($entry->decision)->toBe('allow');
    expect($entry->policy_id)->toBe($gate->id);
    expect($entry->reason_code)->toBeNull();
});

test('a privacy manager cannot update a policy definition — denied by the ABAC policy, not silently allowed', function () {
    $gate = PolicyDefinition::factory()->forPolicyUpdate()->create();
    $target = PolicyDefinition::factory()->create();
    $manager = User::factory()->privacyManager()->create();

    $response = $this->actingAs($manager)->patchJson("/api/v1/admin/policies/{$target->id}", [
        'effect' => 'allow',
    ]);

    $response->assertStatus(403);
    expect($response->json('policy_id'))->toBe($gate->id);

    expect($target->fresh()->status)->toBe('active');

    $entry = AuditLogEntry::query()
        ->where('action', 'policy.update')
        ->where('resource_id', $target->id)
        ->first();

    expect($entry->decision)->toBe('deny');
    expect($entry->reason_code)->toBe('policy_conditions_not_met');
});

test('support staff cannot even view policy definitions — the same ABAC gate covers index, not just edit', function () {
    $gate = PolicyDefinition::factory()->forPolicyUpdate()->create();
    $staff = User::factory()->supportStaff()->create();

    $response = $this->actingAs($staff)->getJson('/api/v1/admin/policies');

    $response->assertStatus(403);
    expect($response->json('policy_id'))->toBe($gate->id);
});

test('fail-closed: a missing policy.update policy denies even an Owner, and logs a policy_missing reason code (ADR-0006)', function () {
    expect(PolicyDefinition::query()->where('action_name', 'policy.update')->exists())->toBeFalse();

    $target = PolicyDefinition::factory()->create();
    $owner = User::factory()->owner()->create();

    $response = $this->actingAs($owner)->patchJson("/api/v1/admin/policies/{$target->id}", [
        'effect' => 'allow',
    ]);

    $response->assertStatus(403);
    expect($response->json('policy_id'))->toBeNull();

    expect($target->fresh()->status)->toBe('active');

    $entry = AuditLogEntry::query()
        ->where('action', 'policy.update')
        ->where('resource_id', $target->id)
        ->first();

    expect($entry)->not->toBeNull();
    expect($entry->decision)->toBe('deny');
    expect($entry->policy_id)->toBeNull();
    expect($entry->reason_code)->toBe('policy_missing');
});

test('fail-closed: a malformed policy.update condition denies even an Owner, and logs an evaluation_error reason code (ADR-0006)', function () {
    $gate = PolicyDefinition::factory()->forPolicyUpdate()->create([
        // Structurally broken, same pattern as the other two actions'
        // fail-closed tests: a condition's spec must be an array
        // ({"in": [...]}), not a bare scalar.
        'subject_conditions' => ['role' => 'not-a-valid-condition-object'],
    ]);

    $target = PolicyDefinition::factory()->create();
    $owner = User::factory()->owner()->create();

    $response = $this->actingAs($owner)->patchJson("/api/v1/admin/policies/{$target->id}", [
        'effect' => 'allow',
    ]);

    $response->assertStatus(403);
    expect($response->json('policy_id'))->toBeNull();

    expect($target->fresh()->status)->toBe('active');

    $entry = AuditLogEntry::query()
        ->where('action', 'policy.update')
        ->where('resource_id', $target->id)
        ->first();

    expect($entry)->not->toBeNull();
    expect($entry->decision)->toBe('deny');
    expect($entry->policy_id)->toBeNull();
    expect($entry->reason_code)->toBe('evaluation_error');

    // Confirms this really was evaluated (and failed), not skipped: the
    // gating policy row itself is untouched by the failure.
    expect($gate->fresh()->status)->toBe('active');
});

test('validation: effect must be allow or deny', function () {
    PolicyDefinition::factory()->forPolicyUpdate()->create();
    $target = PolicyDefinition::factory()->create();
    $owner = User::factory()->owner()->create();

    $response = $this->actingAs($owner)->patchJson("/api/v1/admin/policies/{$target->id}", [
        'effect' => 'not-a-real-effect',
    ]);

    $response->assertStatus(422);
});
