<?php

use App\Models\ConsentNotice;
use App\Models\ConsentPurpose;
use App\Models\ConsentRecord;

// T-03 (06-security-threat-model.md): IP-level rate limiting on the public
// consent capture/withdraw endpoints — a volumetric-abuse control, distinct
// from the per-subject DSAR limit in NFR-006. Mirrors this portfolio's
// existing FlexCatalog login rate-limiting pattern: prove the throttle
// actually returns 429 against a real HTTP call, not just that config
// exists.

test('capturing more than the per-minute IP limit is rate-limited on the next attempt', function () {
    config(['consent.capture_rate_limit_per_minute' => 3]);

    $purpose = ConsentPurpose::factory()->create();
    ConsentNotice::factory()->create(['purpose_id' => $purpose->id, 'version' => 1]);

    for ($i = 0; $i < 3; $i++) {
        $this->postJson('/api/v1/consent', [
            'purpose_id' => $purpose->id,
            'notice_version' => 1,
            'subject_identifier' => "subject-{$i}@example.com",
        ])->assertStatus(201);
    }

    $response = $this->postJson('/api/v1/consent', [
        'purpose_id' => $purpose->id,
        'notice_version' => 1,
        'subject_identifier' => 'subject-overflow@example.com',
    ]);

    $response->assertStatus(429);
    expect($response->json('status'))->toBe(429);

    // Blocked, not silently dropped nor silently allowed through: exactly
    // 3 records exist, not 4.
    expect(ConsentRecord::query()->count())->toBe(3);
});

test('the capture rate limit is scoped per IP, not global', function () {
    config(['consent.capture_rate_limit_per_minute' => 1]);

    $purpose = ConsentPurpose::factory()->create();
    ConsentNotice::factory()->create(['purpose_id' => $purpose->id, 'version' => 1]);

    $this->postJson('/api/v1/consent', [
        'purpose_id' => $purpose->id,
        'notice_version' => 1,
        'subject_identifier' => 'subject-a@example.com',
    ], ['REMOTE_ADDR' => '10.0.0.1'])->assertStatus(201);

    // A different caller IP is unaffected by the first IP's exhausted limit.
    $this->postJson('/api/v1/consent', [
        'purpose_id' => $purpose->id,
        'notice_version' => 1,
        'subject_identifier' => 'subject-b@example.com',
    ], ['REMOTE_ADDR' => '10.0.0.2'])->assertStatus(201);

    expect(ConsentRecord::query()->count())->toBe(2);
});

test('withdrawing more than the per-minute IP limit is rate-limited on the next attempt', function () {
    config(['consent.withdraw_rate_limit_per_minute' => 3]);

    $purpose = ConsentPurpose::factory()->create();
    $notice = ConsentNotice::factory()->create(['purpose_id' => $purpose->id, 'version' => 1]);

    $records = ConsentRecord::factory()->count(4)->create([
        'purpose_id' => $purpose->id,
        'notice_id' => $notice->id,
        'status' => 'active',
    ]);

    foreach ($records->take(3) as $record) {
        $this->postJson("/api/v1/consent/{$record->id}/withdraw")->assertStatus(200);
    }

    $response = $this->postJson("/api/v1/consent/{$records[3]->id}/withdraw");

    $response->assertStatus(429);
    expect($response->json('status'))->toBe(429);

    // Blocked, not silently applied: the 4th record is still active.
    expect($records[3]->fresh()->status)->toBe('active');
});

test('the withdraw rate limit is scoped per IP, not global', function () {
    config(['consent.withdraw_rate_limit_per_minute' => 1]);

    $purpose = ConsentPurpose::factory()->create();
    $notice = ConsentNotice::factory()->create(['purpose_id' => $purpose->id, 'version' => 1]);

    $records = ConsentRecord::factory()->count(2)->create([
        'purpose_id' => $purpose->id,
        'notice_id' => $notice->id,
        'status' => 'active',
    ]);

    $this->postJson("/api/v1/consent/{$records[0]->id}/withdraw", [], ['REMOTE_ADDR' => '10.0.0.1'])
        ->assertStatus(200);

    // A different caller IP is unaffected by the first IP's exhausted limit.
    $this->postJson("/api/v1/consent/{$records[1]->id}/withdraw", [], ['REMOTE_ADDR' => '10.0.0.2'])
        ->assertStatus(200);

    expect($records[0]->fresh()->status)->toBe('withdrawn');
    expect($records[1]->fresh()->status)->toBe('withdrawn');
});
