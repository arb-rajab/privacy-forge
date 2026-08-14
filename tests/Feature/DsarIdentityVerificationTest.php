<?php

use App\Models\AuditLogEntry;
use App\Models\DsarRequest;
use App\Models\PolicyDefinition;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

// US-006 — Verify identity before acting on a DSAR
// docs/project-memory/02-requirements.md
//
// This is the first real invocation of the ADR-0001 PolicyEvaluator, and
// the first test of ADR-0006's fail-closed guarantee against something
// real rather than a stub.
//
// NOT covered here, deliberately (see docs/project-memory/12-session-handoff.md):
// - The separation-of-duties acceptance criterion (verifier != approver)
//   — erasure approval doesn't exist yet, so only half the pair exists.
// - US-006 AC2 ("any export or erasure task is attempted... refuses and
//   logs the refusal") — task execution (US-007) doesn't exist yet
//   either, so there is no code path to attempt.

test('a privacy manager can verify identity, moving the DSAR to in_progress and logging the allow decision with a policy id', function () {
    $policy = PolicyDefinition::factory()->create();
    $manager = User::factory()->privacyManager()->create();
    $dsar = DsarRequest::factory()->create();

    $response = $this->actingAs($manager)->postJson("/api/v1/admin/dsar/{$dsar->id}/verify-identity");

    $response->assertStatus(200)->assertJson(['status' => 'in_progress']);

    $dsar->refresh();
    expect($dsar->status)->toBe('in_progress');
    expect($dsar->identity_verified_by)->toBe($manager->id);
    expect($dsar->identity_verified_at)->not->toBeNull();

    $entry = AuditLogEntry::query()
        ->where('action', 'dsar.identity.verify')
        ->where('resource_id', $dsar->id)
        ->first();

    expect($entry)->not->toBeNull();
    expect($entry->decision)->toBe('allow');
    expect($entry->policy_id)->toBe($policy->id);
    expect($entry->reason_code)->toBeNull();
});

test('an owner can also verify identity', function () {
    PolicyDefinition::factory()->create();
    $owner = User::factory()->owner()->create();
    $dsar = DsarRequest::factory()->create();

    $this->actingAs($owner)->postJson("/api/v1/admin/dsar/{$dsar->id}/verify-identity")
        ->assertStatus(200)
        ->assertJson(['status' => 'in_progress']);
});

test('support staff cannot verify identity — denied by the ABAC policy, not silently allowed', function () {
    $policy = PolicyDefinition::factory()->create();
    $staff = User::factory()->supportStaff()->create();
    $dsar = DsarRequest::factory()->create();

    $response = $this->actingAs($staff)->postJson("/api/v1/admin/dsar/{$dsar->id}/verify-identity");

    $response->assertStatus(403);
    expect($response->json('policy_id'))->toBe($policy->id);

    $dsar->refresh();
    expect($dsar->status)->toBe('pending_verification');
    expect($dsar->identity_verified_by)->toBeNull();

    $entry = AuditLogEntry::query()
        ->where('action', 'dsar.identity.verify')
        ->where('resource_id', $dsar->id)
        ->first();

    expect($entry->decision)->toBe('deny');
    expect($entry->reason_code)->toBe('policy_conditions_not_met');
});

test('fail-closed: a missing dsar.identity.verify policy denies even an Owner, and logs a policy_missing reason code (ADR-0006)', function () {
    // Deliberately no PolicyDefinition row exists at all — simulates the
    // policy definition being removed/never configured.
    expect(PolicyDefinition::query()->where('action_name', 'dsar.identity.verify')->exists())->toBeFalse();

    $owner = User::factory()->owner()->create();
    $dsar = DsarRequest::factory()->create();

    $response = $this->actingAs($owner)->postJson("/api/v1/admin/dsar/{$dsar->id}/verify-identity");

    $response->assertStatus(403);
    expect($response->json('policy_id'))->toBeNull();

    $dsar->refresh();
    expect($dsar->status)->toBe('pending_verification');

    $entry = AuditLogEntry::query()
        ->where('action', 'dsar.identity.verify')
        ->where('resource_id', $dsar->id)
        ->first();

    expect($entry)->not->toBeNull();
    expect($entry->decision)->toBe('deny');
    expect($entry->policy_id)->toBeNull();
    expect($entry->reason_code)->toBe('policy_missing');
});

test('fail-closed: a malformed policy condition denies even an Owner, and logs an evaluation_error reason code (ADR-0006)', function () {
    $policy = PolicyDefinition::factory()->create([
        // Structurally broken: a condition's spec must be an array
        // ({"in": [...]} / {"equals": ...}), not a bare scalar. Simulates
        // a policy row corrupted directly at the DB level.
        'subject_conditions' => ['role' => 'not-a-valid-condition-object'],
    ]);

    $owner = User::factory()->owner()->create();
    $dsar = DsarRequest::factory()->create();

    $response = $this->actingAs($owner)->postJson("/api/v1/admin/dsar/{$dsar->id}/verify-identity");

    $response->assertStatus(403);
    expect($response->json('policy_id'))->toBeNull();

    $dsar->refresh();
    expect($dsar->status)->toBe('pending_verification');

    $entry = AuditLogEntry::query()
        ->where('action', 'dsar.identity.verify')
        ->where('resource_id', $dsar->id)
        ->first();

    expect($entry)->not->toBeNull();
    expect($entry->decision)->toBe('deny');
    expect($entry->policy_id)->toBeNull();
    expect($entry->reason_code)->toBe('evaluation_error');

    // Confirms this really was evaluated (and failed), not skipped: the
    // policy row itself is untouched by the failure.
    expect($policy->fresh()->status)->toBe('active');
});

test('fail-closed: a policy row present but superseded (no active policy) is treated the same as a missing policy', function () {
    PolicyDefinition::factory()->create(['status' => 'superseded']);

    $owner = User::factory()->owner()->create();
    $dsar = DsarRequest::factory()->create();

    $response = $this->actingAs($owner)->postJson("/api/v1/admin/dsar/{$dsar->id}/verify-identity");

    $response->assertStatus(403);

    $entry = AuditLogEntry::query()
        ->where('action', 'dsar.identity.verify')
        ->where('resource_id', $dsar->id)
        ->first();

    expect($entry->reason_code)->toBe('policy_missing');
});

test('verifying identity twice does not overwrite the original verifier or move the DSAR backward', function () {
    PolicyDefinition::factory()->create();
    $firstManager = User::factory()->privacyManager()->create();
    $secondManager = User::factory()->privacyManager()->create();
    $dsar = DsarRequest::factory()->create();

    $this->actingAs($firstManager)->postJson("/api/v1/admin/dsar/{$dsar->id}/verify-identity")
        ->assertStatus(200);

    $this->actingAs($secondManager)->postJson("/api/v1/admin/dsar/{$dsar->id}/verify-identity")
        ->assertStatus(200);

    $dsar->refresh();
    expect($dsar->identity_verified_by)->toBe($firstManager->id);
});

test('the DB check constraint refuses in_progress without identity verification recorded, independent of the application layer', function () {
    $dsar = DsarRequest::factory()->create();

    expect(function () use ($dsar) {
        DB::table('dsar_requests')->where('id', $dsar->id)->update(['status' => 'in_progress']);
    })->toThrow(QueryException::class);
});
