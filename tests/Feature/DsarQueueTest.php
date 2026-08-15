<?php

use App\Models\Connector;
use App\Models\DsarConnectorTask;
use App\Models\DsarRequest;
use App\Models\User;

// Staff-facing DSAR queue (Session 10) — closes the gap flagged after
// Session 8: per-connector task status was previously visible only via
// direct DB access. Not one of ADR-0001's enumerated sensitive actions
// (docs/adr/ADR-0001-abac-policy-model.md), so gated by a plain
// authenticated-staff check per the roles matrix (every staff role,
// including Support Staff, "can view DSAR status") — not PolicyEvaluator.

test('a privacy manager can list the DSAR queue including per-connector task status', function () {
    $manager = User::factory()->privacyManager()->create();
    $verifier = User::factory()->privacyManager()->create();
    $dsar = DsarRequest::factory()->create([
        'request_type' => 'erasure',
        'status' => 'in_progress',
        'identity_verified_by' => $verifier->id,
        'identity_verified_at' => now(),
    ]);
    $connector = Connector::factory()->create(['name' => 'Reference Stub Connector']);
    DsarConnectorTask::factory()->create([
        'dsar_request_id' => $dsar->id,
        'connector_id' => $connector->id,
        'task_type' => 'erasure',
        'status' => 'success',
    ]);

    $response = $this->actingAs($manager)->getJson('/api/v1/admin/dsar');

    $response->assertStatus(200);
    $rows = $response->json();
    expect($rows)->toHaveCount(1);
    expect($rows[0]['id'])->toBe($dsar->id);
    expect($rows[0]['tasks'])->toHaveCount(1);
    expect($rows[0]['tasks'][0]['connector_name'])->toBe('Reference Stub Connector');
    expect($rows[0]['tasks'][0]['status'])->toBe('success');
});

test('support staff can also list the DSAR queue — viewing status is not withheld from that role', function () {
    $staff = User::factory()->supportStaff()->create();
    DsarRequest::factory()->create();

    $this->actingAs($staff)->getJson('/api/v1/admin/dsar')->assertStatus(200);
});

test('an owner can also list the DSAR queue', function () {
    $owner = User::factory()->owner()->create();
    DsarRequest::factory()->create();

    $this->actingAs($owner)->getJson('/api/v1/admin/dsar')->assertStatus(200);
});

test('an unauthenticated caller cannot list the DSAR queue', function () {
    DsarRequest::factory()->create();

    $this->getJson('/api/v1/admin/dsar')->assertStatus(401);
});

test('a DSAR with no dispatched tasks yet shows an empty task list, not an error', function () {
    $manager = User::factory()->privacyManager()->create();
    DsarRequest::factory()->create(['status' => 'pending_verification']);

    $response = $this->actingAs($manager)->getJson('/api/v1/admin/dsar');

    $response->assertStatus(200);
    expect($response->json('0.tasks'))->toBe([]);
});
