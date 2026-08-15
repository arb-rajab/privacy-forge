<?php

use App\Models\Connector;
use App\Models\DeletionCertificate;
use App\Models\DsarConnectorTask;
use App\Models\DsarRequest;
use App\Models\PolicyDefinition;
use App\Models\User;
use App\Services\ConnectorSignatureService;
use Illuminate\Support\Facades\Http;

// US-009/FR-011: "the system must never overstate what it achieved." Both
// the all-success and the honest-partial case are tested explicitly —
// the latter is the one the requirement actually exists to guarantee.

test('US-009 AC1: once every erasure connector confirms, a certificate is issued naming who confirmed and when', function () {
    PolicyDefinition::factory()->forErasureApproval()->create();
    $connector = Connector::factory()->create(['webhook_url' => 'https://connector.example.test/hook', 'name' => 'Reference Stub Connector']);
    $verifier = User::factory()->privacyManager()->create();
    $approver = User::factory()->privacyManager()->create();
    $dsar = DsarRequest::factory()->create([
        'request_type' => 'erasure',
        'status' => 'in_progress',
        'identity_verified_by' => $verifier->id,
        'identity_verified_at' => now(),
    ]);

    // QUEUE_CONNECTION=sync in phpunit.xml.dist means DispatchConnectorTaskJob
    // actually runs inline during this HTTP call, so the outbound webhook
    // must be faked before it, not after.
    Http::fake(['connector.example.test/*' => Http::response('', 200)]);

    $this->actingAs($approver)->postJson("/api/v1/admin/dsar/{$dsar->id}/approve-erasure")->assertStatus(200);

    $task = DsarConnectorTask::query()->where('dsar_request_id', $dsar->id)->firstOrFail();

    $signer = app(ConnectorSignatureService::class);
    $timestamp = (string) now()->timestamp;
    $body = json_encode(['status' => 'success']);
    $signature = $signer->sign($connector->secret_hash, $timestamp, $body);

    $this->withHeaders(['X-Connector-Signature' => $signature, 'X-Connector-Timestamp' => $timestamp])
        ->postJson("/api/v1/connector-callback/{$task->id}", ['status' => 'success'])
        ->assertStatus(200);

    $dsar->refresh();
    expect($dsar->status)->toBe('complete');

    $certificate = DeletionCertificate::query()->where('dsar_request_id', $dsar->id)->first();
    expect($certificate)->not->toBeNull();
    expect($certificate->summary)->toContain('Reference Stub Connector');
    expect($certificate->summary)->toContain('confirmed erasure');
    expect($certificate->exceptions)->toBeNull();
});

test('US-009 AC2, the honest-partial case: a connector that cannot confirm erasure produces a certificate that states the exception explicitly rather than overstating completion', function () {
    PolicyDefinition::factory()->forErasureApproval()->create();
    $goodConnector = Connector::factory()->create(['webhook_url' => 'https://good.example.test/hook', 'name' => 'Good Connector']);
    $badConnector = Connector::factory()->create(['webhook_url' => 'https://bad.example.test/hook', 'name' => 'Bad Connector']);
    $verifier = User::factory()->privacyManager()->create();
    $approver = User::factory()->privacyManager()->create();
    $dsar = DsarRequest::factory()->create([
        'request_type' => 'erasure',
        'status' => 'in_progress',
        'identity_verified_by' => $verifier->id,
        'identity_verified_at' => now(),
    ]);

    // Both connectors receive their webhook fine (200) — the "bad" one's
    // failure is reported later via its own callback, not a delivery
    // failure, so both must be faked here before approve-erasure runs the
    // dispatch jobs inline (sync queue in tests).
    Http::fake([
        'good.example.test/*' => Http::response('', 200),
        'bad.example.test/*' => Http::response('', 200),
    ]);

    $this->actingAs($approver)->postJson("/api/v1/admin/dsar/{$dsar->id}/approve-erasure")->assertStatus(200);

    $goodTask = DsarConnectorTask::query()->where('connector_id', $goodConnector->id)->firstOrFail();
    $badTask = DsarConnectorTask::query()->where('connector_id', $badConnector->id)->firstOrFail();

    $signer = app(ConnectorSignatureService::class);

    $timestamp = (string) now()->timestamp;
    $body = json_encode(['status' => 'success']);
    $signature = $signer->sign($goodConnector->secret_hash, $timestamp, $body);
    $this->withHeaders(['X-Connector-Signature' => $signature, 'X-Connector-Timestamp' => $timestamp])
        ->postJson("/api/v1/connector-callback/{$goodTask->id}", ['status' => 'success'])
        ->assertStatus(200);

    // The bad connector reports failure directly via callback (not a
    // delivery/retry failure this time — the connector received the task
    // fine but could not actually erase the data, e.g. its own downstream
    // system was unreachable).
    $badTimestamp = (string) now()->timestamp;
    $badBody = json_encode(['status' => 'failed', 'failure_reason' => 'downstream system unreachable']);
    $badSignature = $signer->sign($badConnector->secret_hash, $badTimestamp, $badBody);
    $this->withHeaders(['X-Connector-Signature' => $badSignature, 'X-Connector-Timestamp' => $badTimestamp])
        ->postJson("/api/v1/connector-callback/{$badTask->id}", ['status' => 'failed', 'failure_reason' => 'downstream system unreachable'])
        ->assertStatus(200);

    $dsar->refresh();
    expect($dsar->status)->toBe('partially_complete');

    $certificate = DeletionCertificate::query()->where('dsar_request_id', $dsar->id)->first();
    expect($certificate)->not->toBeNull();
    expect($certificate->summary)->toContain('Good Connector');
    expect($certificate->summary)->not->toContain('Bad Connector confirmed');
    expect($certificate->exceptions)->not->toBeNull();
    expect($certificate->exceptions)->toContain('Bad Connector');
    expect($certificate->exceptions)->toContain('did not confirm erasure');
    expect($certificate->exceptions)->toContain('downstream system unreachable');
});
