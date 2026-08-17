<?php

use App\Models\Connector;
use App\Models\DsarConnectorTask;
use App\Models\DsarRequest;
use App\Models\User;
use App\Services\ConnectorSignatureService;
use Database\Seeders\PolicyDefinitionSeeder;
use Illuminate\Support\Facades\Http;

// R-06 (docs/project-memory/10-risk-register.md): before this session, NO
// connector — reference/stub or otherwise — had ever been exercised over
// real HTTP; every existing test (ConnectorDispatchTest.php,
// ConnectorCallbackAuthTest.php) either Http::fake()s the outbound leg or
// hand-signs a postJson() call to simulate a connector that was never
// actually built. This file proves the two new pieces this session
// added — App\Http\Controllers\ReferenceConnectorWebhookController and
// its wiring via RegisterReferenceConnectorCommand — actually make a
// fresh instance's first erasure DSAR reach `complete`, not just
// `partially_complete` forever.
//
// Honest limitation of this test (stated, not glossed over): Pest
// Feature tests run single-process, single-RefreshDatabase-transaction —
// there is no way to make DispatchConnectorTaskJob's Http::post() cross
// a real second OS process from here without breaking transaction
// visibility (a genuinely separate process/connection would not see this
// test's uncommitted rows). Http::fake() below is used only to redirect
// the job's real HTTP call into this same process's real routing/
// controllers (via postJson, exactly as this test's own assertions
// would) rather than a second real socket — every byte of the request
// the job actually builds (headers, raw signed body) survives that
// redirection unchanged, and both controllers run their real production
// code, unmodified. The genuinely cross-container version of this same
// call — `worker` container to `app` container, no shared process or
// transaction at all — is what actually happens in the docker-compose
// demo this session re-timed; this test cannot substitute for that, only
// prove the contract logic on both ends is correct.
//
// The Http::fake() redirect must be armed *before* approve-erasure is
// called, not after: phpunit.xml.dist sets QUEUE_CONNECTION=sync for the
// whole suite (see ConnectorDispatchTest.php's file-level comment), so
// DispatchConnectorTaskJob actually runs synchronously inside the
// approve-erasure request itself — its real Http::post() fires before
// this test gets control back, not on a separate manually-invoked job
// call. (First version of this test armed the fake afterwards and
// learned this the hard way: the job's unfaked real HTTP call escaped to
// this host's actual running dev-server process, which correctly
// returned 401 — right behaviour, wrong process, since that process
// doesn't share this test's uncommitted transaction.)
test('R-06: the built-in reference connector receives the real signed webhook and calls back, reaching complete on a fresh seed', function () {
    $this->seed(PolicyDefinitionSeeder::class);

    $this->artisan('connectors:register-reference', ['--webhook-url' => 'http://app:8000/api/reference-connector/webhook'])
        ->assertSuccessful();
    $connector = Connector::query()->firstOrFail();
    expect($connector->status)->toBe('active');

    $verifier = User::factory()->privacyManager()->create();
    $approver = User::factory()->privacyManager()->create();
    $dsar = DsarRequest::factory()->create(['request_type' => 'erasure', 'status' => 'pending_verification']);

    $this->actingAs($verifier)->postJson("/api/v1/admin/dsar/{$dsar->id}/verify-identity")->assertStatus(200);

    Http::fake(function ($request) {
        $path = parse_url($request->url(), PHP_URL_PATH);

        $response = $this->withHeaders([
            'X-Connector-Signature' => $request->header('X-Connector-Signature')[0] ?? null,
            'X-Connector-Timestamp' => $request->header('X-Connector-Timestamp')[0] ?? null,
        ])->postJson($path, json_decode($request->body(), true) ?? []);

        return Http::response($response->getContent(), $response->getStatusCode());
    });

    // Under the test suite's sync queue driver, this single call is where
    // everything happens: DsarController::approveErasure() dispatches the
    // real DispatchConnectorTaskJob, which signs and "sends" its real
    // outbound webhook (redirected above into the real
    // ReferenceConnectorWebhookController), which independently verifies
    // that signature and fires its own real, independently-signed
    // callback back to the real ConnectorCallbackController — three real
    // production classes, chained via one HTTP-shaped hop each.
    $this->actingAs($approver)->postJson("/api/v1/admin/dsar/{$dsar->id}/approve-erasure")->assertStatus(200);

    $task = DsarConnectorTask::query()->where('dsar_request_id', $dsar->id)->where('connector_id', $connector->id)->firstOrFail();
    expect($task->status)->toBe('success');
    expect($task->failure_reason)->toBeNull();

    $dsar->refresh();
    expect($dsar->status)->toBe('complete');
    expect($dsar->deletionCertificate)->not->toBeNull();
});

test('R-06: a tampered webhook body is rejected by the reference connector, and the task stays pending rather than falsely succeeding', function () {
    $this->seed(PolicyDefinitionSeeder::class);
    $connector = Connector::factory()->create(['webhook_url' => 'http://app:8000/api/reference-connector/webhook']);
    $dsar = DsarRequest::factory()->create(['request_type' => 'export']);
    $task = DsarConnectorTask::factory()->create([
        'dsar_request_id' => $dsar->id,
        'connector_id' => $connector->id,
        'task_type' => 'export',
        'status' => 'pending',
    ]);

    $timestamp = (string) now()->timestamp;
    $body = json_encode(['task_id' => $task->id, 'dsar_id' => $dsar->id, 'task_type' => 'export', 'subject_identifier' => 'x@example.test', 'schema_version' => 1]);
    $signer = app(ConnectorSignatureService::class);
    $validSignature = $signer->sign($connector->secret_hash, $timestamp, $body);

    // Same signature, different body than what was signed — the
    // reference connector must reject this exactly as a real third-party
    // connector implementing the documented HMAC contract would.
    $tamperedBody = json_encode(['task_id' => $task->id, 'dsar_id' => $dsar->id, 'task_type' => 'export', 'subject_identifier' => 'attacker@example.test', 'schema_version' => 1]);

    $this->withHeaders([
        'X-Connector-Signature' => $validSignature,
        'X-Connector-Timestamp' => $timestamp,
    ])->postJson('/api/reference-connector/webhook', json_decode($tamperedBody, true))
        ->assertStatus(401);

    expect($task->fresh()->status)->toBe('pending');
});
