<?php

use App\Models\AuditLogEntry;
use App\Models\ConsentNotice;
use App\Models\ConsentPurpose;
use App\Models\ConsentRecord;
use App\Models\User;

// US-001 — Define a consent purpose and lawful basis
// docs/project-memory/02-requirements.md

test('a privacy manager can create a consent purpose, saved and versioned at version 1', function () {
    $manager = User::factory()->privacyManager()->create();

    $response = $this->actingAs($manager)->postJson('/api/v1/admin/consent-purposes', [
        'name' => 'Marketing emails',
        'description' => 'Sending promotional email campaigns',
        'lawful_basis' => 'consent',
    ]);

    $response->assertStatus(201)->assertJson([
        'name' => 'Marketing emails',
        'lawful_basis' => 'consent',
        'status' => 'active',
        'version' => 1,
    ]);

    $purpose = ConsentPurpose::query()->firstOrFail();
    expect($purpose->version)->toBe(1);
    expect($purpose->status)->toBe('active');

    expect(AuditLogEntry::query()->where('action', 'consent_purpose.create')->where('resource_id', $purpose->id)->exists())->toBeTrue();
});

test('support staff cannot create a consent purpose', function () {
    $staff = User::factory()->supportStaff()->create();

    $this->actingAs($staff)->postJson('/api/v1/admin/consent-purposes', [
        'name' => 'Marketing emails',
        'lawful_basis' => 'consent',
    ])->assertStatus(403);

    expect(ConsentPurpose::query()->count())->toBe(0);
});

test('deleting a purpose with active consent records is refused and instructs deprecation instead', function () {
    $manager = User::factory()->privacyManager()->create();
    $purpose = ConsentPurpose::factory()->create();
    $notice = ConsentNotice::factory()->create(['purpose_id' => $purpose->id, 'version' => 1]);
    ConsentRecord::create([
        'subject_identifier_hash' => ConsentRecord::hashIdentifier('subject@example.com'),
        'purpose_id' => $purpose->id,
        'notice_id' => $notice->id,
        'status' => 'active',
        'given_at' => now(),
    ]);

    $response = $this->actingAs($manager)->deleteJson("/api/v1/admin/consent-purposes/{$purpose->id}");

    $response->assertStatus(409);
    expect($response->json('detail'))->toContain('deprecate');
    expect(ConsentPurpose::query()->whereKey($purpose->id)->exists())->toBeTrue();
});

test('deleting a purpose with no active consent records succeeds', function () {
    $manager = User::factory()->privacyManager()->create();
    $purpose = ConsentPurpose::factory()->create();

    $this->actingAs($manager)->deleteJson("/api/v1/admin/consent-purposes/{$purpose->id}")
        ->assertStatus(204);

    expect(ConsentPurpose::query()->whereKey($purpose->id)->exists())->toBeFalse();
});
