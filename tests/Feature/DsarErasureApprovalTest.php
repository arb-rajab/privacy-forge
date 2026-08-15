<?php

use App\Models\AuditLogEntry;
use App\Models\DsarRequest;
use App\Models\PolicyDefinition;
use App\Models\User;

// US-006's remaining half — erasure approval and separation of duties.
// docs/project-memory/02-requirements.md, docs/adr/ADR-0001, ADR-0007.
//
// Session 6b left two acceptance criteria explicitly untested because this
// endpoint didn't exist yet (see docs/project-memory/12-session-handoff.md):
// - Separation of duties: the approver must differ from the identity
//   verifier (ADR-0001), now expressible via ADR-0007's
//   `not_equals_attribute` condition operator.
// - US-006 AC2: no export/erasure task executes before identity
//   verification — here, that "task" is erasure approval itself.
// Both are covered below against the real endpoints, not mocks.

test('a privacy manager can approve erasure for a DSAR verified by someone else, logging the allow decision with a policy id', function () {
    $policy = PolicyDefinition::factory()->forErasureApproval()->create();
    $verifier = User::factory()->privacyManager()->create();
    $approver = User::factory()->privacyManager()->create();
    $dsar = DsarRequest::factory()->create([
        'request_type' => 'erasure',
        'status' => 'in_progress',
        'identity_verified_by' => $verifier->id,
        'identity_verified_at' => now(),
    ]);

    $response = $this->actingAs($approver)->postJson("/api/v1/admin/dsar/{$dsar->id}/approve-erasure");

    $response->assertStatus(200)->assertJson(['status' => 'in_progress']);

    $dsar->refresh();
    expect($dsar->erasure_approved_by)->toBe($approver->id);
    expect($dsar->erasure_approved_at)->not->toBeNull();

    $entry = AuditLogEntry::query()
        ->where('action', 'dsar.erasure.approve')
        ->where('resource_id', $dsar->id)
        ->first();

    expect($entry)->not->toBeNull();
    expect($entry->decision)->toBe('allow');
    expect($entry->policy_id)->toBe($policy->id);
    expect($entry->reason_code)->toBeNull();
});

test('an owner can also approve erasure for a DSAR verified by someone else', function () {
    PolicyDefinition::factory()->forErasureApproval()->create();
    $verifier = User::factory()->privacyManager()->create();
    $owner = User::factory()->owner()->create();
    $dsar = DsarRequest::factory()->create([
        'request_type' => 'erasure',
        'status' => 'in_progress',
        'identity_verified_by' => $verifier->id,
        'identity_verified_at' => now(),
    ]);

    $this->actingAs($owner)->postJson("/api/v1/admin/dsar/{$dsar->id}/approve-erasure")
        ->assertStatus(200)
        ->assertJson(['status' => 'in_progress']);
});

test('support staff cannot approve erasure — denied by the ABAC policy, not silently allowed', function () {
    $policy = PolicyDefinition::factory()->forErasureApproval()->create();
    $verifier = User::factory()->privacyManager()->create();
    $staff = User::factory()->supportStaff()->create();
    $dsar = DsarRequest::factory()->create([
        'request_type' => 'erasure',
        'status' => 'in_progress',
        'identity_verified_by' => $verifier->id,
        'identity_verified_at' => now(),
    ]);

    $response = $this->actingAs($staff)->postJson("/api/v1/admin/dsar/{$dsar->id}/approve-erasure");

    $response->assertStatus(403);
    expect($response->json('policy_id'))->toBe($policy->id);

    $dsar->refresh();
    expect($dsar->erasure_approved_by)->toBeNull();

    $entry = AuditLogEntry::query()
        ->where('action', 'dsar.erasure.approve')
        ->where('resource_id', $dsar->id)
        ->first();

    expect($entry->decision)->toBe('deny');
    expect($entry->reason_code)->toBe('policy_conditions_not_met');
});

test('separation of duties: the same user who verified identity cannot also approve erasure on that DSAR (ADR-0001/ADR-0007)', function () {
    PolicyDefinition::factory()->create(); // dsar.identity.verify
    PolicyDefinition::factory()->forErasureApproval()->create();
    $manager = User::factory()->privacyManager()->create();
    $dsar = DsarRequest::factory()->create(['request_type' => 'erasure']);

    // Real HTTP calls through the real evaluator for both actions — not
    // fixture data standing in for "already verified".
    $this->actingAs($manager)->postJson("/api/v1/admin/dsar/{$dsar->id}/verify-identity")
        ->assertStatus(200);

    $dsar->refresh();
    expect($dsar->identity_verified_by)->toBe($manager->id);

    $response = $this->actingAs($manager)->postJson("/api/v1/admin/dsar/{$dsar->id}/approve-erasure");

    $response->assertStatus(403);

    $dsar->refresh();
    expect($dsar->erasure_approved_by)->toBeNull();

    $entry = AuditLogEntry::query()
        ->where('action', 'dsar.erasure.approve')
        ->where('resource_id', $dsar->id)
        ->first();

    expect($entry)->not->toBeNull();
    expect($entry->decision)->toBe('deny');
    expect($entry->reason_code)->toBe('policy_conditions_not_met');
});

test('separation of duties: an Owner who verified identity also cannot approve erasure on that DSAR themselves (NFR-005 gap found and closed this session)', function () {
    // The existing pair of separation-of-duties tests above only ever
    // exercise the privacy_manager role. ADR-0007's actual policy row
    // applies `not_equals_attribute` to `role: {in: [owner,
    // privacy_manager]}` — i.e. by design, Owner is not exempt from this
    // control. 02-requirements.md's Owner row ("Nothing withheld within
    // the instance") reads as if it should be exempt; that wording
    // predates ADR-0007 (2026-08-10 vs 2026-08-14) and was never updated
    // to reflect it. This is flagged as a documentation-currency finding
    // in docs/project-memory/12-session-handoff.md — not fixed by
    // weakening the policy, which would reopen ADR-0007's decision.
    PolicyDefinition::factory()->create(); // dsar.identity.verify
    PolicyDefinition::factory()->forErasureApproval()->create();
    $owner = User::factory()->owner()->create();
    $dsar = DsarRequest::factory()->create(['request_type' => 'erasure']);

    $this->actingAs($owner)->postJson("/api/v1/admin/dsar/{$dsar->id}/verify-identity")
        ->assertStatus(200);

    $dsar->refresh();
    expect($dsar->identity_verified_by)->toBe($owner->id);

    $response = $this->actingAs($owner)->postJson("/api/v1/admin/dsar/{$dsar->id}/approve-erasure");

    $response->assertStatus(403);

    $dsar->refresh();
    expect($dsar->erasure_approved_by)->toBeNull();

    $entry = AuditLogEntry::query()
        ->where('action', 'dsar.erasure.approve')
        ->where('resource_id', $dsar->id)
        ->first();

    expect($entry)->not->toBeNull();
    expect($entry->decision)->toBe('deny');
    expect($entry->reason_code)->toBe('policy_conditions_not_met');
});

test('separation of duties: a different verifier and approver succeeds, proving the rule discriminates rather than always denying', function () {
    PolicyDefinition::factory()->create(); // dsar.identity.verify
    PolicyDefinition::factory()->forErasureApproval()->create();
    $verifier = User::factory()->privacyManager()->create();
    $approver = User::factory()->privacyManager()->create();
    $dsar = DsarRequest::factory()->create(['request_type' => 'erasure']);

    $this->actingAs($verifier)->postJson("/api/v1/admin/dsar/{$dsar->id}/verify-identity")
        ->assertStatus(200);

    $this->actingAs($approver)->postJson("/api/v1/admin/dsar/{$dsar->id}/approve-erasure")
        ->assertStatus(200);

    $dsar->refresh();
    expect($dsar->identity_verified_by)->toBe($verifier->id);
    expect($dsar->erasure_approved_by)->toBe($approver->id);
});

test('US-006 AC2: erasure approval is refused and logged when identity has not yet been verified', function () {
    $policy = PolicyDefinition::factory()->forErasureApproval()->create();
    $manager = User::factory()->privacyManager()->create();
    $dsar = DsarRequest::factory()->create([
        'request_type' => 'erasure',
        'status' => 'pending_verification',
    ]);

    $response = $this->actingAs($manager)->postJson("/api/v1/admin/dsar/{$dsar->id}/approve-erasure");

    $response->assertStatus(403);
    expect($response->json('policy_id'))->toBe($policy->id);

    $dsar->refresh();
    expect($dsar->erasure_approved_by)->toBeNull();
    expect($dsar->status)->toBe('pending_verification');

    $entry = AuditLogEntry::query()
        ->where('action', 'dsar.erasure.approve')
        ->where('resource_id', $dsar->id)
        ->first();

    expect($entry)->not->toBeNull();
    expect($entry->decision)->toBe('deny');
    expect($entry->reason_code)->toBe('policy_conditions_not_met');
});

test('erasure approval is refused for a non-erasure DSAR even once verified', function () {
    PolicyDefinition::factory()->forErasureApproval()->create();
    $verifier = User::factory()->privacyManager()->create();
    $approver = User::factory()->privacyManager()->create();
    $dsar = DsarRequest::factory()->create([
        'request_type' => 'access',
        'status' => 'in_progress',
        'identity_verified_by' => $verifier->id,
        'identity_verified_at' => now(),
    ]);

    $response = $this->actingAs($approver)->postJson("/api/v1/admin/dsar/{$dsar->id}/approve-erasure");

    $response->assertStatus(403);

    $dsar->refresh();
    expect($dsar->erasure_approved_by)->toBeNull();
});

test('fail-closed: a missing dsar.erasure.approve policy denies even an Owner, and logs a policy_missing reason code (ADR-0006)', function () {
    expect(PolicyDefinition::query()->where('action_name', 'dsar.erasure.approve')->exists())->toBeFalse();

    $verifier = User::factory()->privacyManager()->create();
    $owner = User::factory()->owner()->create();
    $dsar = DsarRequest::factory()->create([
        'request_type' => 'erasure',
        'status' => 'in_progress',
        'identity_verified_by' => $verifier->id,
        'identity_verified_at' => now(),
    ]);

    $response = $this->actingAs($owner)->postJson("/api/v1/admin/dsar/{$dsar->id}/approve-erasure");

    $response->assertStatus(403);
    expect($response->json('policy_id'))->toBeNull();

    $dsar->refresh();
    expect($dsar->erasure_approved_by)->toBeNull();

    $entry = AuditLogEntry::query()
        ->where('action', 'dsar.erasure.approve')
        ->where('resource_id', $dsar->id)
        ->first();

    expect($entry)->not->toBeNull();
    expect($entry->decision)->toBe('deny');
    expect($entry->policy_id)->toBeNull();
    expect($entry->reason_code)->toBe('policy_missing');
});

test('fail-closed: a malformed not_equals_attribute reference denies even an Owner, and logs an evaluation_error reason code (ADR-0006/ADR-0007)', function () {
    $policy = PolicyDefinition::factory()->forErasureApproval()->create([
        // Structurally broken: not_equals_attribute must be a "bag.attribute"
        // string (e.g. "resource.identity_verified_by"), not a bare scalar
        // with no bag separator. Simulates a policy row corrupted at the
        // DB level, same pattern as Session 6b's malformed-condition test.
        'subject_conditions' => [
            'role' => ['in' => ['owner', 'privacy_manager']],
            'id' => ['not_equals_attribute' => 'identity_verified_by'],
        ],
    ]);

    $verifier = User::factory()->privacyManager()->create();
    $owner = User::factory()->owner()->create();
    $dsar = DsarRequest::factory()->create([
        'request_type' => 'erasure',
        'status' => 'in_progress',
        'identity_verified_by' => $verifier->id,
        'identity_verified_at' => now(),
    ]);

    $response = $this->actingAs($owner)->postJson("/api/v1/admin/dsar/{$dsar->id}/approve-erasure");

    $response->assertStatus(403);
    expect($response->json('policy_id'))->toBeNull();

    $dsar->refresh();
    expect($dsar->erasure_approved_by)->toBeNull();

    $entry = AuditLogEntry::query()
        ->where('action', 'dsar.erasure.approve')
        ->where('resource_id', $dsar->id)
        ->first();

    expect($entry)->not->toBeNull();
    expect($entry->decision)->toBe('deny');
    expect($entry->policy_id)->toBeNull();
    expect($entry->reason_code)->toBe('evaluation_error');

    expect($policy->fresh()->status)->toBe('active');
});

test('approving erasure twice does not overwrite the original approver', function () {
    PolicyDefinition::factory()->forErasureApproval()->create();
    $verifier = User::factory()->privacyManager()->create();
    $firstApprover = User::factory()->privacyManager()->create();
    $secondApprover = User::factory()->owner()->create();
    $dsar = DsarRequest::factory()->create([
        'request_type' => 'erasure',
        'status' => 'in_progress',
        'identity_verified_by' => $verifier->id,
        'identity_verified_at' => now(),
    ]);

    $this->actingAs($firstApprover)->postJson("/api/v1/admin/dsar/{$dsar->id}/approve-erasure")
        ->assertStatus(200);

    $this->actingAs($secondApprover)->postJson("/api/v1/admin/dsar/{$dsar->id}/approve-erasure")
        ->assertStatus(200);

    $dsar->refresh();
    expect($dsar->erasure_approved_by)->toBe($firstApprover->id);
});
