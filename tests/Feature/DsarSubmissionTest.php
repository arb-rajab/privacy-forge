<?php

use App\Models\DsarRequest;

// US-005 — Submit a DSAR via the public portal
// docs/project-memory/02-requirements.md

test('submitting a valid DSAR creates a pending_verification record and returns a signed status link', function () {
    $response = $this->postJson('/api/v1/dsar', [
        'request_type' => 'access',
        'subject_identifier' => 'subject@example.com',
    ]);

    $response->assertStatus(201)->assertJson(['status' => 'pending_verification']);
    expect($response->json('status_url'))->toBeString()->toContain('/api/v1/dsar/status/');
    expect($response->json('status_url'))->toContain('signature=');

    $dsar = DsarRequest::query()->firstOrFail();
    expect($dsar->status)->toBe('pending_verification');
    expect($dsar->request_type)->toBe('access');
    expect($dsar->subject_identifier)->toBe('subject@example.com');
    expect($dsar->subject_identifier_hash)->toBe(DsarRequest::hashIdentifier('subject@example.com'));
});

test('submitting a DSAR with a missing required field returns 422 and creates no record', function () {
    $response = $this->postJson('/api/v1/dsar', [
        'request_type' => 'access',
        // subject_identifier omitted
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['subject_identifier']);
    expect(DsarRequest::query()->count())->toBe(0);
});

test('submitting a DSAR with an invalid request_type returns 422', function () {
    $this->postJson('/api/v1/dsar', [
        'request_type' => 'delete-everything',
        'subject_identifier' => 'subject@example.com',
    ])->assertStatus(422);

    expect(DsarRequest::query()->count())->toBe(0);
});

test('submitting more than 3 DSARs from the same identifier within 24h is rate-limited on the 4th (NFR-006)', function () {
    for ($i = 0; $i < 3; $i++) {
        $this->postJson('/api/v1/dsar', [
            'request_type' => 'access',
            'subject_identifier' => 'repeat-subject@example.com',
        ])->assertStatus(201);
    }

    $response = $this->postJson('/api/v1/dsar', [
        'request_type' => 'access',
        'subject_identifier' => 'repeat-subject@example.com',
    ]);

    $response->assertStatus(429);
    expect($response->json('type'))->toBeString();
    expect($response->json('status'))->toBe(429);

    // Blocked, not silently dropped nor silently allowed through: exactly
    // 3 records exist, not 4.
    expect(DsarRequest::query()->count())->toBe(3);
});

test('the rate limit is scoped per identifier, not global', function () {
    for ($i = 0; $i < 3; $i++) {
        $this->postJson('/api/v1/dsar', [
            'request_type' => 'access',
            'subject_identifier' => 'subject-a@example.com',
        ])->assertStatus(201);
    }

    // A different subject is unaffected by subject-a's exhausted limit.
    $this->postJson('/api/v1/dsar', [
        'request_type' => 'access',
        'subject_identifier' => 'subject-b@example.com',
    ])->assertStatus(201);

    expect(DsarRequest::query()->count())->toBe(4);
});

test('the signed status link never carries a TTL beyond the 72-hour NFR-007 cap', function () {
    $response = $this->postJson('/api/v1/dsar', [
        'request_type' => 'export',
        'subject_identifier' => 'subject@example.com',
    ]);

    $statusUrl = $response->json('status_url');
    parse_str((string) parse_url((string) $statusUrl, PHP_URL_QUERY), $query);

    expect($query)->toHaveKey('expires');
    $expiresAt = (int) $query['expires'];

    expect($expiresAt)->toBeLessThanOrEqual(now()->addHours(72)->addSeconds(5)->getTimestamp());
    expect($expiresAt)->toBeGreaterThan(now()->getTimestamp());
});
