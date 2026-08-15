<?php

use App\Models\AuditLogEntry;
use App\Models\DataCategory;
use App\Models\PolicyDefinition;
use App\Models\RetentionPolicy;
use App\Models\User;

// Session 11 — retention.policy.manage (US-010/US-011, ADR-0002), the
// fourth registered sensitive action. Same pattern as PolicyManagementTest
// (which covers policy.update): the (role x retention.policy.manage)
// matrix cells live in AuthorisationMatrixTest.php (POST
// /admin/data-categories is the representative endpoint there); this
// file covers what that matrix deliberately doesn't — index/show sharing
// the gate, versioning-on-update, dry-run also sharing the gate, and both
// fail-closed reason codes. The dry-run/execution parity guarantee itself
// (ADR-0002's whole reason for existing) is asserted separately in
// tests/Feature/RetentionDryRunParityTest.php, not here.

test('an owner can define a data category', function () {
    PolicyDefinition::factory()->forRetentionPolicyManage()->create();
    $owner = User::factory()->owner()->create();

    $response = $this->actingAs($owner)->postJson('/api/v1/admin/data-categories', [
        'name' => 'Withdrawn consent records',
        'sensitivity' => 'standard',
        'subject_table' => 'consent_records',
    ]);

    $response->assertStatus(201)->assertJson([
        'name' => 'Withdrawn consent records',
        'sensitivity' => 'standard',
        'subject_table' => 'consent_records',
    ]);
});

test('a privacy manager can also define a data category — this action is not Owner-only, unlike policy.update', function () {
    PolicyDefinition::factory()->forRetentionPolicyManage()->create();
    $manager = User::factory()->privacyManager()->create();

    $response = $this->actingAs($manager)->postJson('/api/v1/admin/data-categories', [
        'name' => 'Closed DSAR requests',
        'sensitivity' => 'elevated',
        'subject_table' => 'dsar_requests',
    ]);

    $response->assertStatus(201);
});

test('support staff cannot even list data categories — the same ABAC gate covers index, not just edit', function () {
    $gate = PolicyDefinition::factory()->forRetentionPolicyManage()->create();
    $staff = User::factory()->supportStaff()->create();

    $response = $this->actingAs($staff)->getJson('/api/v1/admin/data-categories');

    $response->assertStatus(403);
    expect($response->json('policy_id'))->toBe($gate->id);
});

test('an owner can define a retention policy for an existing data category, saved and versioned at version 1 (US-010)', function () {
    PolicyDefinition::factory()->forRetentionPolicyManage()->create();
    $category = DataCategory::factory()->create();
    $owner = User::factory()->owner()->create();

    $response = $this->actingAs($owner)->postJson('/api/v1/admin/retention-policies', [
        'data_category_id' => $category->id,
        'retention_period_days' => 30,
        'post_expiry_action' => 'erase',
    ]);

    $response->assertStatus(201)->assertJson([
        'data_category_id' => $category->id,
        'retention_period_days' => 30,
        'post_expiry_action' => 'erase',
        'status' => 'active',
        'version' => 1,
    ]);
});

test('an owner can list and view retention policies', function () {
    PolicyDefinition::factory()->forRetentionPolicyManage()->create();
    $policy = RetentionPolicy::factory()->create();
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->getJson('/api/v1/admin/retention-policies')
        ->assertStatus(200)
        ->assertJsonCount(1);

    $this->actingAs($owner)->getJson("/api/v1/admin/retention-policies/{$policy->id}")
        ->assertStatus(200)
        ->assertJson(['id' => $policy->id]);
});

test('updating a retention policy supersedes the current version and creates the next, keeping the same data category', function () {
    $gate = PolicyDefinition::factory()->forRetentionPolicyManage()->create();
    $policy = RetentionPolicy::factory()->create(['retention_period_days' => 30, 'post_expiry_action' => 'erase']);
    $manager = User::factory()->privacyManager()->create();

    $response = $this->actingAs($manager)->patchJson("/api/v1/admin/retention-policies/{$policy->id}", [
        'retention_period_days' => 90,
        'post_expiry_action' => 'anonymise',
    ]);

    $response->assertStatus(200);
    expect($response->json('version'))->toBe(2);
    expect($response->json('data_category_id'))->toBe($policy->data_category_id);
    expect($response->json('post_expiry_action'))->toBe('anonymise');

    expect($policy->fresh()->status)->toBe('deprecated');

    $newRow = RetentionPolicy::query()
        ->where('data_category_id', $policy->data_category_id)
        ->where('status', 'active')
        ->first();

    expect($newRow)->not->toBeNull();
    expect($newRow->version)->toBe(2);
    expect($newRow->retention_period_days)->toBe(90);

    $entry = AuditLogEntry::query()
        ->where('action', 'retention.policy.manage')
        ->where('resource_id', $policy->id)
        ->first();

    expect($entry)->not->toBeNull();
    expect($entry->decision)->toBe('allow');
    expect($entry->policy_id)->toBe($gate->id);
});

test('support staff cannot update a retention policy — denied by the ABAC policy, not silently allowed', function () {
    $gate = PolicyDefinition::factory()->forRetentionPolicyManage()->create();
    $policy = RetentionPolicy::factory()->create();
    $staff = User::factory()->supportStaff()->create();

    $response = $this->actingAs($staff)->patchJson("/api/v1/admin/retention-policies/{$policy->id}", [
        'retention_period_days' => 90,
        'post_expiry_action' => 'anonymise',
    ]);

    $response->assertStatus(403);
    expect($response->json('policy_id'))->toBe($gate->id);
    expect($policy->fresh()->status)->toBe('active');

    $entry = AuditLogEntry::query()
        ->where('action', 'retention.policy.manage')
        ->where('resource_id', $policy->id)
        ->first();

    expect($entry->decision)->toBe('deny');
    expect($entry->reason_code)->toBe('policy_conditions_not_met');
});

test('support staff cannot dry-run a retention policy either — the same gate covers preview, not just edit', function () {
    $gate = PolicyDefinition::factory()->forRetentionPolicyManage()->create();
    $policy = RetentionPolicy::factory()->create();
    $staff = User::factory()->supportStaff()->create();

    $response = $this->actingAs($staff)->postJson("/api/v1/admin/retention-policies/{$policy->id}/dry-run");

    $response->assertStatus(403);
    expect($response->json('policy_id'))->toBe($gate->id);
});

test('fail-closed: a missing retention.policy.manage policy denies even an Owner, and logs a policy_missing reason code (ADR-0006)', function () {
    expect(PolicyDefinition::query()->where('action_name', 'retention.policy.manage')->exists())->toBeFalse();

    $policy = RetentionPolicy::factory()->create();
    $owner = User::factory()->owner()->create();

    $response = $this->actingAs($owner)->postJson("/api/v1/admin/retention-policies/{$policy->id}/dry-run");

    $response->assertStatus(403);
    expect($response->json('policy_id'))->toBeNull();

    $entry = AuditLogEntry::query()
        ->where('action', 'retention.policy.manage')
        ->where('resource_id', $policy->id)
        ->first();

    expect($entry)->not->toBeNull();
    expect($entry->decision)->toBe('deny');
    expect($entry->reason_code)->toBe('policy_missing');
});

test('fail-closed: a malformed retention.policy.manage condition denies even an Owner, and logs an evaluation_error reason code (ADR-0006)', function () {
    $gate = PolicyDefinition::factory()->forRetentionPolicyManage()->create([
        'subject_conditions' => ['role' => 'not-a-valid-condition-object'],
    ]);

    $policy = RetentionPolicy::factory()->create();
    $owner = User::factory()->owner()->create();

    $response = $this->actingAs($owner)->postJson("/api/v1/admin/retention-policies/{$policy->id}/dry-run");

    $response->assertStatus(403);
    expect($response->json('policy_id'))->toBeNull();

    $entry = AuditLogEntry::query()
        ->where('action', 'retention.policy.manage')
        ->where('resource_id', $policy->id)
        ->first();

    expect($entry)->not->toBeNull();
    expect($entry->decision)->toBe('deny');
    expect($entry->reason_code)->toBe('evaluation_error');

    // Confirms this really was evaluated (and failed), not skipped.
    expect($gate->fresh()->status)->toBe('active');
});

test('validation: subject_table must be one of the two supported tables', function () {
    PolicyDefinition::factory()->forRetentionPolicyManage()->create();
    $owner = User::factory()->owner()->create();

    $response = $this->actingAs($owner)->postJson('/api/v1/admin/data-categories', [
        'name' => 'Bogus category',
        'sensitivity' => 'standard',
        'subject_table' => 'audit_log_entries',
    ]);

    $response->assertStatus(422);
});

test('validation: retention_period_days must be a positive integer', function () {
    PolicyDefinition::factory()->forRetentionPolicyManage()->create();
    $category = DataCategory::factory()->create();
    $owner = User::factory()->owner()->create();

    $response = $this->actingAs($owner)->postJson('/api/v1/admin/retention-policies', [
        'data_category_id' => $category->id,
        'retention_period_days' => 0,
        'post_expiry_action' => 'erase',
    ]);

    $response->assertStatus(422);
});
