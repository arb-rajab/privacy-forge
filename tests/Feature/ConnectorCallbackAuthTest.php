<?php

use App\Models\AuditLogEntry;
use App\Models\Connector;
use App\Models\DsarConnectorTask;
use App\Models\DsarRequest;
use App\Services\ConnectorSignatureService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

// T-07/T-08 (06-security-threat-model.md): forged callbacks and replay of
// a validly-signed callback must both be rejected before touching
// business logic. Every failure path here is 401 — the OpenAPI contract
// only documents 200/401 for this endpoint, deliberately collapsing
// "unknown task" into the same response as "bad signature" (no existence
// oracle for an unauthenticated caller).

function connectorCallbackTestSetup(): array
{
    $connector = Connector::factory()->create();
    $dsar = DsarRequest::factory()->create(['request_type' => 'export']);
    $task = DsarConnectorTask::factory()->create([
        'dsar_request_id' => $dsar->id,
        'connector_id' => $connector->id,
        'status' => 'pending',
    ]);

    return [$connector, $dsar, $task];
}

function connectorCallbackTestSign($connector, string $timestamp, array $payload): array
{
    $signer = app(ConnectorSignatureService::class);
    $body = json_encode($payload);
    $signature = $signer->sign($connector->secret_hash, $timestamp, $body);

    return [$signature, $body];
}

test('a callback with valid signature and fresh timestamp is accepted', function () {
    [$connector, , $task] = connectorCallbackTestSetup();
    $timestamp = (string) now()->timestamp;
    $payload = ['status' => 'success'];
    [$signature] = connectorCallbackTestSign($connector, $timestamp, $payload);

    $this->withHeaders([
        'X-Connector-Signature' => $signature,
        'X-Connector-Timestamp' => $timestamp,
    ])->postJson("/api/v1/connector-callback/{$task->id}", $payload)
        ->assertStatus(200);

    expect($task->fresh()->status)->toBe('success');
});

test('T-07: a forged signature (wrong secret) is rejected with 401 and does not change task state', function () {
    [$connector, , $task] = connectorCallbackTestSetup();
    $timestamp = (string) now()->timestamp;
    $payload = ['status' => 'success'];

    // Signed with an unrelated secret, not the connector's real one.
    $forgedSignature = hash_hmac('sha256', $timestamp.'.'.json_encode($payload), 'not-the-real-secret');

    $this->withHeaders([
        'X-Connector-Signature' => $forgedSignature,
        'X-Connector-Timestamp' => $timestamp,
    ])->postJson("/api/v1/connector-callback/{$task->id}", $payload)
        ->assertStatus(401);

    expect($task->fresh()->status)->toBe('pending');
});

test('a callback with missing signature or timestamp headers is rejected with 401', function () {
    [, , $task] = connectorCallbackTestSetup();

    $this->postJson("/api/v1/connector-callback/{$task->id}", ['status' => 'success'])
        ->assertStatus(401);
});

test('a callback for an unknown task id is rejected with 401, not 404 (no existence oracle, T-05-style)', function () {
    $connector = Connector::factory()->create();
    $timestamp = (string) now()->timestamp;
    $payload = ['status' => 'success'];
    [$signature] = connectorCallbackTestSign($connector, $timestamp, $payload);

    $this->withHeaders([
        'X-Connector-Signature' => $signature,
        'X-Connector-Timestamp' => $timestamp,
    ])->postJson('/api/v1/connector-callback/'.Str::uuid(), $payload)
        ->assertStatus(401);
});

test('T-08: a validly-signed callback outside the tolerance window is rejected as a replay, regardless of signature validity', function () {
    [$connector, , $task] = connectorCallbackTestSetup();
    $staleTimestamp = (string) now()->subSeconds(301)->timestamp; // tolerance default is 300s
    $payload = ['status' => 'success'];
    [$signature] = connectorCallbackTestSign($connector, $staleTimestamp, $payload);

    $this->withHeaders([
        'X-Connector-Signature' => $signature,
        'X-Connector-Timestamp' => $staleTimestamp,
    ])->postJson("/api/v1/connector-callback/{$task->id}", $payload)
        ->assertStatus(401);

    expect($task->fresh()->status)->toBe('pending');
});

test('a callback within the tolerance window is accepted', function () {
    [$connector, , $task] = connectorCallbackTestSetup();
    $timestamp = (string) now()->subSeconds(299)->timestamp;
    $payload = ['status' => 'success'];
    [$signature] = connectorCallbackTestSign($connector, $timestamp, $payload);

    $this->withHeaders([
        'X-Connector-Signature' => $signature,
        'X-Connector-Timestamp' => $timestamp,
    ])->postJson("/api/v1/connector-callback/{$task->id}", $payload)
        ->assertStatus(200);

    expect($task->fresh()->status)->toBe('success');
});

test('a disabled connector cannot submit further callbacks, even with a valid signature', function () {
    [$connector, , $task] = connectorCallbackTestSetup();
    $connector->forceFill(['status' => 'disabled'])->save();
    $timestamp = (string) now()->timestamp;
    $payload = ['status' => 'success'];
    [$signature] = connectorCallbackTestSign($connector, $timestamp, $payload);

    $this->withHeaders([
        'X-Connector-Signature' => $signature,
        'X-Connector-Timestamp' => $timestamp,
    ])->postJson("/api/v1/connector-callback/{$task->id}", $payload)
        ->assertStatus(401);

    expect($task->fresh()->status)->toBe('pending');
});

test('secrets management: a forged-signature rejection never logs the connector secret in plaintext', function () {
    Log::spy();
    [$connector, , $task] = connectorCallbackTestSetup();
    $timestamp = (string) now()->timestamp;
    $payload = ['status' => 'success'];

    $this->withHeaders([
        'X-Connector-Signature' => 'deliberately-wrong-signature',
        'X-Connector-Timestamp' => $timestamp,
    ])->postJson("/api/v1/connector-callback/{$task->id}", $payload)
        ->assertStatus(401);

    Log::shouldNotHaveReceived('error', function (array $args) use ($connector) {
        return str_contains(json_encode($args), (string) $connector->secret_hash);
    });
});

test('idempotency: a connector re-sending the same terminal status for the same task is a no-op, not an anomaly', function () {
    [$connector, $dsar, $task] = connectorCallbackTestSetup();
    $timestamp1 = (string) now()->timestamp;
    [$signature1] = connectorCallbackTestSign($connector, $timestamp1, ['status' => 'success']);

    $this->withHeaders(['X-Connector-Signature' => $signature1, 'X-Connector-Timestamp' => $timestamp1])
        ->postJson("/api/v1/connector-callback/{$task->id}", ['status' => 'success'])
        ->assertStatus(200);

    $completedAt = $task->fresh()->completed_at;

    $timestamp2 = (string) now()->addSecond()->timestamp;
    [$signature2] = connectorCallbackTestSign($connector, $timestamp2, ['status' => 'success']);

    $this->withHeaders(['X-Connector-Signature' => $signature2, 'X-Connector-Timestamp' => $timestamp2])
        ->postJson("/api/v1/connector-callback/{$task->id}", ['status' => 'success'])
        ->assertStatus(200);

    $task->refresh();
    expect($task->status)->toBe('success');
    expect($task->completed_at->equalTo($completedAt))->toBeTrue();
    expect($connector->fresh()->status)->toBe('active');

    expect(AuditLogEntry::query()->where('action', 'connector.callback.anomaly')->where('resource_id', $task->id)->exists())->toBeFalse();
});

test('T-09: a callback reporting a conflicting terminal status for an already-terminal task is an anomaly — task keeps its original status, connector is auto-disabled', function () {
    [$connector, $dsar, $task] = connectorCallbackTestSetup();
    $timestamp1 = (string) now()->timestamp;
    [$signature1] = connectorCallbackTestSign($connector, $timestamp1, ['status' => 'success']);

    $this->withHeaders(['X-Connector-Signature' => $signature1, 'X-Connector-Timestamp' => $timestamp1])
        ->postJson("/api/v1/connector-callback/{$task->id}", ['status' => 'success'])
        ->assertStatus(200);

    expect($task->fresh()->status)->toBe('success');
    expect($connector->fresh()->status)->toBe('active');

    // Second callback for the SAME task_id claims a conflicting terminal
    // status. Note: signed with the connector's original secret — this
    // isn't a signature-validity problem, it's a business-logic anomaly.
    $timestamp2 = (string) now()->addSecond()->timestamp;
    [$signature2] = connectorCallbackTestSign($connector, $timestamp2, ['status' => 'failed', 'failure_reason' => 'conflicting report']);

    $this->withHeaders(['X-Connector-Signature' => $signature2, 'X-Connector-Timestamp' => $timestamp2])
        ->postJson("/api/v1/connector-callback/{$task->id}", ['status' => 'failed', 'failure_reason' => 'conflicting report'])
        ->assertStatus(200);

    // The task is left in its ORIGINAL terminal state — never overwritten
    // by the conflicting report.
    $task->refresh();
    expect($task->status)->toBe('success');

    // The connector is auto-disabled pending manual review.
    expect($connector->fresh()->status)->toBe('disabled');

    $anomaly = AuditLogEntry::query()
        ->where('action', 'connector.callback.anomaly')
        ->where('resource_id', $task->id)
        ->first();

    expect($anomaly)->not->toBeNull();
    expect($anomaly->decision)->toBe('deny');
    expect($anomaly->reason_code)->toBe('connector_status_conflict');
    expect($anomaly->actor_type)->toBe('connector');
});
