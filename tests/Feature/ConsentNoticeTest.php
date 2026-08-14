<?php

use App\Models\AuditLogEntry;
use App\Models\ConsentNotice;
use App\Models\ConsentPurpose;
use App\Models\ConsentRecord;
use App\Models\User;

// US-002 — Publish a versioned consent notice
// docs/project-memory/02-requirements.md

test('publishing a notice versions it, timestamps it, and keeps previous versions retrievable', function () {
    $manager = User::factory()->privacyManager()->create();
    $purpose = ConsentPurpose::factory()->create();

    $first = $this->actingAs($manager)->postJson("/api/v1/admin/consent-purposes/{$purpose->id}/notices", [
        'body' => 'Version 1 wording',
    ]);
    $first->assertStatus(201)->assertJson(['version' => 1, 'body' => 'Version 1 wording']);

    $second = $this->actingAs($manager)->postJson("/api/v1/admin/consent-purposes/{$purpose->id}/notices", [
        'body' => 'Version 2, materially different wording',
    ]);
    $second->assertStatus(201)->assertJson(['version' => 2]);

    expect(ConsentNotice::query()->where('purpose_id', $purpose->id)->count())->toBe(2);

    $v1 = ConsentNotice::query()->where('purpose_id', $purpose->id)->where('version', 1)->firstOrFail();
    expect($v1->body)->toBe('Version 1 wording');

    $purpose->refresh();
    expect($purpose->currentNotice->version)->toBe(2);

    expect(AuditLogEntry::query()->where('action', 'consent_notice.publish')->count())->toBe(2);
});

test('republishing a notice does not silently upgrade existing consent records to the new version', function () {
    $manager = User::factory()->privacyManager()->create();
    $purpose = ConsentPurpose::factory()->create();
    $noticeV1 = ConsentNotice::factory()->create(['purpose_id' => $purpose->id, 'version' => 1]);
    $purpose->forceFill(['current_notice_id' => $noticeV1->id])->save();

    $record = ConsentRecord::create([
        'subject_identifier_hash' => ConsentRecord::hashIdentifier('subject@example.com'),
        'purpose_id' => $purpose->id,
        'notice_id' => $noticeV1->id,
        'status' => 'active',
        'given_at' => now(),
    ]);

    $this->actingAs($manager)->postJson("/api/v1/admin/consent-purposes/{$purpose->id}/notices", [
        'body' => 'Materially different wording',
    ])->assertStatus(201);

    $record->refresh();
    expect($record->notice_id)->toBe($noticeV1->id);
});

test('an existing consent notice cannot be edited in place', function () {
    $notice = ConsentNotice::factory()->create();

    expect(fn () => $notice->update(['body' => 'edited']))->toThrow(LogicException::class);
});
