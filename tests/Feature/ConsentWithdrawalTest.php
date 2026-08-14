<?php

use App\Models\AuditLogEntry;
use App\Models\ConsentNotice;
use App\Models\ConsentPurpose;
use App\Models\ConsentRecord;

// US-004 — Withdraw consent
// docs/project-memory/02-requirements.md

test('withdrawing consent marks it withdrawn with a timestamp rather than deleting it, and logs an audit entry', function () {
    $purpose = ConsentPurpose::factory()->create();
    $notice = ConsentNotice::factory()->create(['purpose_id' => $purpose->id, 'version' => 1]);
    $record = ConsentRecord::create([
        'subject_identifier_hash' => ConsentRecord::hashIdentifier('subject@example.com'),
        'purpose_id' => $purpose->id,
        'notice_id' => $notice->id,
        'status' => 'active',
        'given_at' => now(),
    ]);

    $response = $this->postJson("/api/v1/consent/{$record->id}/withdraw");

    $response->assertStatus(200)->assertJson(['status' => 'withdrawn']);

    $record->refresh();
    expect($record->status)->toBe('withdrawn');
    expect($record->withdrawn_at)->not->toBeNull();

    expect(AuditLogEntry::query()->where('action', 'consent.withdraw')->where('resource_id', $record->id)->exists())->toBeTrue();
});

test('withdrawn consent is no longer considered active from the moment of withdrawal onward', function () {
    $purpose = ConsentPurpose::factory()->create();
    $notice = ConsentNotice::factory()->create(['purpose_id' => $purpose->id, 'version' => 1]);
    $subjectHash = ConsentRecord::hashIdentifier('subject@example.com');
    $record = ConsentRecord::create([
        'subject_identifier_hash' => $subjectHash,
        'purpose_id' => $purpose->id,
        'notice_id' => $notice->id,
        'status' => 'active',
        'given_at' => now(),
    ]);

    expect(ConsentRecord::isActiveFor($subjectHash, $purpose->id))->toBeTrue();

    $this->postJson("/api/v1/consent/{$record->id}/withdraw")->assertStatus(200);

    expect(ConsentRecord::isActiveFor($subjectHash, $purpose->id))->toBeFalse();
});

test('a consent record cannot be deleted directly, only withdrawn', function () {
    $purpose = ConsentPurpose::factory()->create();
    $notice = ConsentNotice::factory()->create(['purpose_id' => $purpose->id, 'version' => 1]);
    $record = ConsentRecord::create([
        'subject_identifier_hash' => ConsentRecord::hashIdentifier('subject@example.com'),
        'purpose_id' => $purpose->id,
        'notice_id' => $notice->id,
        'status' => 'active',
        'given_at' => now(),
    ]);

    expect(fn () => $record->delete())->toThrow(LogicException::class);
});
