<?php

use App\Jobs\DispatchConnectorTaskJob;
use App\Models\Connector;
use App\Models\DsarConnectorTask;
use App\Models\DsarRequest;
use App\Models\PolicyDefinition;
use App\Models\User;
use App\Services\ConnectorSignatureService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

// US-007/FR-008/FR-009 (ADR-0004). phpunit.xml.dist sets
// QUEUE_CONNECTION=sync for the whole suite: under sync, a job's
// exceptions propagate synchronously into whatever call stack dispatched
// it (including the original HTTP request), which is a sync-driver-only
// quirk — a real (redis) worker instead catches failures in its own
// process, never surfacing them to the original caller. So: HTTP-level
// tests here use Queue::fake() to assert *that* dispatch happened without
// letting sync-driver semantics leak into the response, and job-level
// tests instantiate DispatchConnectorTaskJob directly to exercise its
// real handle()/failed() logic without needing an actual multi-minute
// exponential backoff to elapse.

test('US-007 AC1: verifying identity on an export DSAR with N registered connectors dispatches N independently tracked tasks', function () {
    PolicyDefinition::factory()->create();
    $connectorA = Connector::factory()->create();
    $connectorB = Connector::factory()->create();
    $manager = User::factory()->privacyManager()->create();
    $dsar = DsarRequest::factory()->create(['request_type' => 'export', 'status' => 'pending_verification']);

    Queue::fake();

    $this->actingAs($manager)->postJson("/api/v1/admin/dsar/{$dsar->id}/verify-identity")
        ->assertStatus(200);

    $tasks = DsarConnectorTask::query()->where('dsar_request_id', $dsar->id)->get();
    expect($tasks)->toHaveCount(2);
    expect($tasks->pluck('connector_id')->sort()->values()->all())
        ->toBe(collect([$connectorA->id, $connectorB->id])->sort()->values()->all());
    expect($tasks->pluck('status')->unique()->all())->toBe(['pending']);
    expect($tasks->pluck('task_type')->unique()->all())->toBe(['export']);

    Queue::assertPushed(DispatchConnectorTaskJob::class, 2);
});

test('US-007: erasure DSARs are not dispatched at verify-identity — only at approve-erasure', function () {
    PolicyDefinition::factory()->create();
    PolicyDefinition::factory()->forErasureApproval()->create();
    Connector::factory()->create();
    $manager = User::factory()->privacyManager()->create();
    $approver = User::factory()->privacyManager()->create();
    $dsar = DsarRequest::factory()->create(['request_type' => 'erasure', 'status' => 'pending_verification']);

    Queue::fake();

    $this->actingAs($manager)->postJson("/api/v1/admin/dsar/{$dsar->id}/verify-identity")->assertStatus(200);
    expect(DsarConnectorTask::query()->where('dsar_request_id', $dsar->id)->count())->toBe(0);

    $this->actingAs($approver)->postJson("/api/v1/admin/dsar/{$dsar->id}/approve-erasure")->assertStatus(200);

    $tasks = DsarConnectorTask::query()->where('dsar_request_id', $dsar->id)->get();
    expect($tasks)->toHaveCount(1);
    expect($tasks->first()->task_type)->toBe('erasure');

    Queue::assertPushed(DispatchConnectorTaskJob::class, 1);
});

test('only active connectors receive dispatched tasks; a disabled connector is skipped', function () {
    PolicyDefinition::factory()->create();
    $active = Connector::factory()->create();
    Connector::factory()->disabled()->create();
    $manager = User::factory()->privacyManager()->create();
    $dsar = DsarRequest::factory()->create(['request_type' => 'export', 'status' => 'pending_verification']);

    Queue::fake();

    $this->actingAs($manager)->postJson("/api/v1/admin/dsar/{$dsar->id}/verify-identity")->assertStatus(200);

    $tasks = DsarConnectorTask::query()->where('dsar_request_id', $dsar->id)->get();
    expect($tasks)->toHaveCount(1);
    expect($tasks->first()->connector_id)->toBe($active->id);
});

test('DispatchConnectorTaskJob signs the outbound webhook and leaves the task pending on a successful delivery', function () {
    $connector = Connector::factory()->create(['webhook_url' => 'https://connector.example.test/hook']);
    $dsar = DsarRequest::factory()->create(['request_type' => 'export', 'subject_identifier' => 'subject@example.com']);
    $task = DsarConnectorTask::factory()->create([
        'dsar_request_id' => $dsar->id,
        'connector_id' => $connector->id,
        'task_type' => 'export',
        'status' => 'pending',
    ]);

    Http::fake(['connector.example.test/*' => Http::response('', 200)]);

    (new DispatchConnectorTaskJob($task->id))->handle(app(ConnectorSignatureService::class));

    Http::assertSent(function ($request) use ($connector, $task, $dsar) {
        $timestamp = $request->header('X-Connector-Timestamp')[0] ?? null;
        $signature = $request->header('X-Connector-Signature')[0] ?? null;
        expect($timestamp)->not->toBeNull();
        // Independently recomputed via the raw HMAC formula (ADR-0004),
        // not by calling ConnectorSignatureService again, so this actually
        // checks the wire format rather than the service agreeing with
        // itself.
        expect($signature)->toBe(hash_hmac('sha256', $timestamp.'.'.$request->body(), $connector->secret_hash));

        // Shape matches 05-api-contracts.md's documented outbound webhook
        // contract exactly — including subject_identifier, without which
        // a connector would have no way to know whose data to act on.
        $payload = json_decode($request->body(), true);
        expect($payload)->toBe([
            'task_id' => $task->id,
            'dsar_id' => $dsar->id,
            'task_type' => 'export',
            'subject_identifier' => 'subject@example.com',
            'schema_version' => 1,
        ]);

        return true;
    });

    $task->refresh();
    expect($task->status)->toBe('pending');
    expect($task->attempt_count)->toBe(1);
    expect($task->dispatched_at)->not->toBeNull();
});

test('DispatchConnectorTaskJob throws on a non-2xx delivery response, so the queue worker will retry it', function () {
    $connector = Connector::factory()->create(['webhook_url' => 'https://connector.example.test/hook']);
    $dsar = DsarRequest::factory()->create(['request_type' => 'export']);
    $task = DsarConnectorTask::factory()->create([
        'dsar_request_id' => $dsar->id,
        'connector_id' => $connector->id,
        'status' => 'pending',
    ]);

    Http::fake(['connector.example.test/*' => Http::response('', 503)]);

    expect(fn () => (new DispatchConnectorTaskJob($task->id))->handle(app(ConnectorSignatureService::class)))
        ->toThrow(RuntimeException::class);

    // Delivery failure alone (before retries are exhausted) does not mark
    // the task failed — only failed() (called once the queue exhausts
    // $tries) does that.
    $task->refresh();
    expect($task->status)->toBe('pending');
});

test('US-007 AC2 / FR-009, a real multi-connector scenario: one connector succeeds, one exhausts retries — the DSAR becomes partially_complete, never a false complete', function () {
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

    Queue::fake();
    $this->actingAs($approver)->postJson("/api/v1/admin/dsar/{$dsar->id}/approve-erasure")->assertStatus(200);

    $goodTask = DsarConnectorTask::query()->where('connector_id', $goodConnector->id)->firstOrFail();
    $badTask = DsarConnectorTask::query()->where('connector_id', $badConnector->id)->firstOrFail();

    Http::fake([
        'good.example.test/*' => Http::response('', 200),
        'bad.example.test/*' => Http::response('', 503),
    ]);

    $signer = app(ConnectorSignatureService::class);
    (new DispatchConnectorTaskJob($goodTask->id))->handle($signer);

    $exception = null;
    try {
        (new DispatchConnectorTaskJob($badTask->id))->handle($signer);
    } catch (RuntimeException $e) {
        $exception = $e;
    }
    expect($exception)->not->toBeNull();

    // Simulates the queue worker exhausting $tries for the bad connector
    // — see the file-level comment on why this is invoked directly rather
    // than waiting out real exponential backoff.
    (new DispatchConnectorTaskJob($badTask->id))->failed($exception);

    $badTask->refresh();
    expect($badTask->status)->toBe('failed');
    expect($badTask->failure_reason)->not->toBeNull();

    // The good connector hasn't called back yet — DSAR must not resolve
    // to any terminal status while a task is still pending.
    $dsar->refresh();
    expect($dsar->status)->toBe('in_progress');

    // Now the good connector's callback arrives for real, through the
    // actual connector-callback endpoint.
    $timestamp = (string) now()->timestamp;
    $body = json_encode(['status' => 'success']);
    $signature = $signer->sign($goodConnector->secret_hash, $timestamp, $body);

    $this->withHeaders([
        'X-Connector-Signature' => $signature,
        'X-Connector-Timestamp' => $timestamp,
    ])->postJson("/api/v1/connector-callback/{$goodTask->id}", ['status' => 'success'])
        ->assertStatus(200);

    $dsar->refresh();
    expect($dsar->status)->toBe('partially_complete');

    $goodTask->refresh();
    expect($goodTask->status)->toBe('success');
});
