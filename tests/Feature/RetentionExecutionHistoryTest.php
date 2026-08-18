<?php

use App\Models\DeletionCertificate;
use App\Models\PolicyDefinition;
use App\Models\RetentionExecution;
use App\Models\RetentionPolicy;
use App\Models\User;

// B-05 (docs/project-memory/11-backlog.md) — no read endpoint existed for
// past RetentionExecution/DeletionCertificate rows; AdminRetention.vue's
// "Past execution history" section stated this gap explicitly rather
// than faking it (Session 21). This file covers the new
// GET /admin/retention-policies/{policyId}/executions endpoint. It
// shares the retention.policy.manage gate with the rest of
// Admin\RetentionPolicyController — the (role x retention.policy.manage)
// allow/deny cells and fail-closed cases are already covered in
// tests/Feature/RetentionPolicyManagementTest.php and
// tests/Feature/AuthorisationMatrixTest.php; not reimplemented here, per
// this codebase's cross-reference-rather-than-duplicate convention.

test('an owner can list past executions of a policy, most recent first, each with its certificate when one exists', function () {
    PolicyDefinition::factory()->forRetentionPolicyManage()->create();
    $policy = RetentionPolicy::factory()->create();
    $owner = User::factory()->owner()->create();

    $dryRun = RetentionExecution::factory()->create([
        'retention_policy_id' => $policy->id,
        'affected_record_count' => 3,
        'executed_at' => now()->subDays(2),
    ]);

    $realRun = RetentionExecution::factory()->real()->create([
        'retention_policy_id' => $policy->id,
        'affected_record_count' => 5,
        'executed_at' => now()->subDay(),
    ]);
    $certificate = DeletionCertificate::factory()->create([
        'dsar_request_id' => null,
        'retention_execution_id' => $realRun->id,
        'summary' => '5 record(s) erased.',
        'exceptions' => null,
    ]);
    $realRun->forceFill(['certificate_id' => $certificate->id])->save();

    $response = $this->actingAs($owner)->getJson("/api/v1/admin/retention-policies/{$policy->id}/executions");

    $response->assertStatus(200)->assertJsonCount(2);

    $body = $response->json();

    // Most recent first: the real run (executed yesterday) precedes the
    // dry run (executed two days ago).
    expect($body[0]['id'])->toBe($realRun->id);
    expect($body[0]['mode'])->toBe('real');
    expect($body[0]['affected_record_count'])->toBe(5);
    expect($body[0]['certificate']['summary'])->toBe('5 record(s) erased.');
    expect($body[0]['certificate']['exceptions'])->toBeNull();

    expect($body[1]['id'])->toBe($dryRun->id);
    expect($body[1]['mode'])->toBe('dry_run');
    expect($body[1]['affected_record_count'])->toBe(3);
    expect($body[1]['certificate'])->toBeNull();
});

test('a policy with no executions yet returns an empty list, not an error', function () {
    PolicyDefinition::factory()->forRetentionPolicyManage()->create();
    $policy = RetentionPolicy::factory()->create();
    $owner = User::factory()->owner()->create();

    $response = $this->actingAs($owner)->getJson("/api/v1/admin/retention-policies/{$policy->id}/executions");

    $response->assertStatus(200)->assertJsonCount(0);
});

test('support staff cannot list execution history — the same retention.policy.manage gate covers this endpoint too', function () {
    $gate = PolicyDefinition::factory()->forRetentionPolicyManage()->create();
    $policy = RetentionPolicy::factory()->create();
    $staff = User::factory()->supportStaff()->create();

    $response = $this->actingAs($staff)->getJson("/api/v1/admin/retention-policies/{$policy->id}/executions");

    $response->assertStatus(403);
    expect($response->json('policy_id'))->toBe($gate->id);
});

test('executions for an unknown policy id return 404', function () {
    PolicyDefinition::factory()->forRetentionPolicyManage()->create();
    $owner = User::factory()->owner()->create();

    $response = $this->actingAs($owner)->getJson('/api/v1/admin/retention-policies/00000000-0000-0000-0000-000000000000/executions');

    $response->assertStatus(404);
});
