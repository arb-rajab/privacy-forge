<?php

use App\Models\DsarRequest;
use Illuminate\Support\Facades\URL;

// US-005 (status check) + T-05 (docs/project-memory/06-security-threat-model.md)
// — status/export access is keyed only by unguessable signed tokens, never
// a bare DSAR id.

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
