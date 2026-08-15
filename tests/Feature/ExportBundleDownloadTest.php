<?php

use App\Models\Connector;
use App\Models\DsarConnectorTask;
use App\Models\DsarRequest;
use App\Models\ExportBundle;
use App\Models\PolicyDefinition;
use App\Models\User;
use App\Services\ConnectorSignatureService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

// US-008/FR-010/NFR-007. TTL is enforced twice per 04-data-model.md's
// invariant table: at creation (ExportBundleAssembler, checked here via
// the row's own signed_url_expires_at) and again at download-serving
// time (ExportBundleController::download, checked here with a
// deliberately-expired row behind an otherwise-still-valid URL signature
// — proving the row's own expiry is checked independently, not just
// Laravel's URL signature).

test('US-008 AC1: once every export connector task succeeds, a bundle is assembled in both json and csv with a TTL that never exceeds 72 hours', function () {
    Storage::fake('s3');
    PolicyDefinition::factory()->create();
    $connector = Connector::factory()->create(['webhook_url' => 'https://connector.example.test/hook']);
    $manager = User::factory()->privacyManager()->create();
    $dsar = DsarRequest::factory()->create(['request_type' => 'export', 'status' => 'pending_verification']);

    // QUEUE_CONNECTION=sync in phpunit.xml.dist means DispatchConnectorTaskJob
    // actually runs inline during this HTTP call, so the outbound webhook
    // must be faked before it, not after.
    Http::fake(['connector.example.test/*' => Http::response('', 200)]);

    $this->actingAs($manager)->postJson("/api/v1/admin/dsar/{$dsar->id}/verify-identity")->assertStatus(200);

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

    $bundles = ExportBundle::query()->where('dsar_request_id', $dsar->id)->get();
    expect($bundles)->toHaveCount(2);
    expect($bundles->pluck('format')->sort()->values()->all())->toBe(['csv', 'json']);

    foreach ($bundles as $bundle) {
        expect(Storage::disk('s3')->exists($bundle->storage_path))->toBeTrue();
        expect($bundle->signed_url_expires_at->lessThanOrEqualTo($bundle->created_at->copy()->addHours(72)))->toBeTrue();
        expect($bundle->signed_url_expires_at->greaterThan(now()))->toBeTrue();
    }
});

test('the download endpoint returns a fresh download_url for a valid, unexpired bundle token', function () {
    Storage::fake('s3');
    $dsar = DsarRequest::factory()->create(['request_type' => 'export']);
    $bundle = ExportBundle::factory()->create(['dsar_request_id' => $dsar->id, 'format' => 'json']);
    Storage::disk('s3')->put($bundle->storage_path, '{"example":true}');

    $url = URL::temporarySignedRoute('dsar.export.download', now()->addMinutes(10), ['signedToken' => $bundle->download_token]);

    $response = $this->getJson($url);

    $response->assertStatus(200)->assertJson(['format' => 'json']);
    expect($response->json('download_url'))->not->toBeNull();
    expect($response->json('expires_at'))->not->toBeNull();
});

test('US-008 AC2 / NFR-007: an expired bundle is refused at download time (410), even behind a still-validly-signed URL', function () {
    Storage::fake('s3');
    $dsar = DsarRequest::factory()->create(['request_type' => 'export']);
    // The row itself has already passed its own TTL...
    $bundle = ExportBundle::factory()->expired()->create(['dsar_request_id' => $dsar->id]);

    // ...but the outer URL signature is deliberately given a long, still-
    // valid window, proving the 410 comes from checking the row's own
    // signed_url_expires_at, not merely from the URL signature expiring.
    $url = URL::temporarySignedRoute('dsar.export.download', now()->addDay(), ['signedToken' => $bundle->download_token]);

    $this->getJson($url)->assertStatus(410);
});

test('a download link whose own URL signature has expired is refused with 410', function () {
    $dsar = DsarRequest::factory()->create(['request_type' => 'export']);
    $bundle = ExportBundle::factory()->create(['dsar_request_id' => $dsar->id]);

    $url = URL::temporarySignedRoute('dsar.export.download', now()->subMinute(), ['signedToken' => $bundle->download_token]);

    $this->getJson($url)->assertStatus(410);
});

test('an unknown download token is refused with 410, not 404', function () {
    $url = URL::temporarySignedRoute('dsar.export.download', now()->addMinutes(10), ['signedToken' => 'not-a-real-token']);

    $this->getJson($url)->assertStatus(410);
});

test('the raw download link actually serves the stored bundle bytes, decrypted (FR-010: encrypted at rest)', function () {
    Storage::fake('s3');
    $dsar = DsarRequest::factory()->create(['request_type' => 'export']);
    $bundle = ExportBundle::factory()->create(['dsar_request_id' => $dsar->id, 'format' => 'json']);
    // Stored the same way ExportBundleAssembler does: encrypted, not plain
    // bytes — object storage itself never sees the real content.
    Storage::disk('s3')->put($bundle->storage_path, Crypt::encryptString('{"example":true}'));

    $downloadResponse = $this->getJson(
        URL::temporarySignedRoute('dsar.export.download', now()->addMinutes(10), ['signedToken' => $bundle->download_token])
    );

    $rawUrl = $downloadResponse->json('download_url');
    $rawResponse = $this->get($rawUrl);

    $rawResponse->assertStatus(200);
    expect($rawResponse->getContent())->toBe('{"example":true}');
});
