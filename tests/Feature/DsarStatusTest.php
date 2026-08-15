<?php

use App\Models\DeletionCertificate;
use App\Models\DsarRequest;
use App\Models\ExportBundle;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

// US-005 (status check) + T-05 (docs/project-memory/06-security-threat-model.md)
// — status/export access is keyed only by unguessable signed tokens, never
// a bare DSAR id.
//
// export_bundles/deletion_certificate (Session 10): before this session,
// the status link a data subject already holds never surfaced whether
// their export or deletion certificate was ready — ExportBundle's
// download_token existed but nothing ever minted a signed URL to it for
// the data subject (the gap Session 8's handoff flagged). These tests
// cover that this endpoint's response now does.

test('checking status via a valid signed link returns the current status', function () {
    $submit = $this->postJson('/api/v1/dsar', [
        'request_type' => 'access',
        'subject_identifier' => 'subject@example.com',
    ]);

    $statusUrl = $submit->json('status_url');
    $path = parse_url((string) $statusUrl, PHP_URL_PATH).'?'.parse_url((string) $statusUrl, PHP_URL_QUERY);

    $response = $this->getJson($path);

    $response->assertStatus(200)->assertJson(['status' => 'pending_verification']);
    expect($response->json('id'))->toBeString();
    expect($response->json('request_type'))->toBe('access');
});

test('an expired signed link returns 410, not the status', function () {
    $dsar = DsarRequest::factory()->create();

    $expiredUrl = URL::temporarySignedRoute(
        'dsar.status',
        now()->subMinute(),
        ['signedToken' => $dsar->status_token],
    );

    $path = parse_url($expiredUrl, PHP_URL_PATH).'?'.parse_url($expiredUrl, PHP_URL_QUERY);

    $response = $this->getJson($path);

    $response->assertStatus(410);
    expect($response->json('status'))->toBe(410);
});

test('a request with no signature at all is refused with 410, not treated as a lookup by bare token', function () {
    $dsar = DsarRequest::factory()->create();

    // No ?expires=/&signature= query string — this is exactly T-05's
    // concern: a bare identifier alone must never be sufficient.
    $response = $this->getJson('/api/v1/dsar/status/'.$dsar->status_token);

    $response->assertStatus(410);
});

test('the DSAR row\'s own uuid id cannot be used as the status token even with a forged signature', function () {
    $dsar = DsarRequest::factory()->create();

    // An attacker who knows (or guesses) the internal uuid id still can't
    // produce a valid signature for it without the app key, but this also
    // confirms the id itself was never usable as a lookup key even in
    // principle: signing a URL for the *id* rather than the real
    // status_token still doesn't resolve to this DSAR's record.
    $forgedUrl = URL::temporarySignedRoute(
        'dsar.status',
        now()->addHours(1),
        ['signedToken' => $dsar->id],
    );

    $path = parse_url($forgedUrl, PHP_URL_PATH).'?'.parse_url($forgedUrl, PHP_URL_QUERY);

    // The signature itself is valid (Laravel signed it), but no row has
    // this id as its status_token, so it 404s rather than ever exposing
    // this DSAR's status via its bare id.
    $this->getJson($path)->assertStatus(404);
});

test('status shows no export bundles and no certificate before either exists', function () {
    $submit = $this->postJson('/api/v1/dsar', [
        'request_type' => 'access',
        'subject_identifier' => 'subject@example.com',
    ]);

    $statusUrl = $submit->json('status_url');
    $path = parse_url((string) $statusUrl, PHP_URL_PATH).'?'.parse_url((string) $statusUrl, PHP_URL_QUERY);

    $response = $this->getJson($path);

    expect($response->json('export_bundles'))->toBe([]);
    expect($response->json('deletion_certificate'))->toBeNull();
});

test('a status check surfaces a ready, unexpired export bundle with a working signed download url', function () {
    Storage::fake('s3');
    $dsar = DsarRequest::factory()->create(['request_type' => 'export', 'status' => 'complete']);
    $bundle = ExportBundle::factory()->create(['dsar_request_id' => $dsar->id, 'format' => 'json']);
    Storage::disk('s3')->put($bundle->storage_path, Crypt::encryptString('{}'));

    $statusUrl = URL::temporarySignedRoute('dsar.status', now()->addHour(), ['signedToken' => $dsar->status_token]);
    $path = parse_url($statusUrl, PHP_URL_PATH).'?'.parse_url($statusUrl, PHP_URL_QUERY);

    $response = $this->getJson($path);

    $bundles = $response->json('export_bundles');
    expect($bundles)->toHaveCount(1);
    expect($bundles[0]['format'])->toBe('json');
    expect($bundles[0]['download_url'])->not->toBeNull();

    // The surfaced link actually resolves, proving it's a real signed URL
    // to the existing download endpoint, not just a string shaped like one.
    $this->getJson($bundles[0]['download_url'])->assertStatus(200)->assertJson(['format' => 'json']);
});

test('an expired export bundle is not surfaced as ready via the status endpoint', function () {
    $dsar = DsarRequest::factory()->create(['request_type' => 'export']);
    ExportBundle::factory()->expired()->create(['dsar_request_id' => $dsar->id]);

    $statusUrl = URL::temporarySignedRoute('dsar.status', now()->addHour(), ['signedToken' => $dsar->status_token]);
    $path = parse_url($statusUrl, PHP_URL_PATH).'?'.parse_url($statusUrl, PHP_URL_QUERY);

    $response = $this->getJson($path);

    expect($response->json('export_bundles'))->toBe([]);
});

test('a status check surfaces a ready deletion certificate, including the honest-partial exceptions text (US-009)', function () {
    $dsar = DsarRequest::factory()->create(['request_type' => 'erasure']);
    DeletionCertificate::factory()->create([
        'dsar_request_id' => $dsar->id,
        'summary' => 'Reference Stub Connector confirmed erasure.',
        'exceptions' => 'Bad Connector did not confirm erasure.',
    ]);

    $statusUrl = URL::temporarySignedRoute('dsar.status', now()->addHour(), ['signedToken' => $dsar->status_token]);
    $path = parse_url($statusUrl, PHP_URL_PATH).'?'.parse_url($statusUrl, PHP_URL_QUERY);

    $response = $this->getJson($path);

    $certificate = $response->json('deletion_certificate');
    expect($certificate)->not->toBeNull();
    expect($certificate['summary'])->toBe('Reference Stub Connector confirmed erasure.');
    expect($certificate['exceptions'])->toBe('Bad Connector did not confirm erasure.');
});
