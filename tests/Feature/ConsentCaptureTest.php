<?php

use App\Models\AuditLogEntry;
use App\Models\ConsentNotice;
use App\Models\ConsentPurpose;
use App\Models\ConsentRecord;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

// US-003 — Capture consent via API or embeddable widget
// docs/project-memory/02-requirements.md

test('capturing a valid consent event creates a record and an audit log entry', function () {
    $purpose = ConsentPurpose::factory()->create();
    $notice = ConsentNotice::factory()->create(['purpose_id' => $purpose->id, 'version' => 1]);

    $response = $this->postJson('/api/v1/consent', [
        'purpose_id' => $purpose->id,
        'notice_version' => 1,
        'subject_identifier' => 'subject@example.com',
    ]);

    $response->assertStatus(201)->assertJson([
        'purpose_id' => $purpose->id,
        'notice_version' => 1,
        'status' => 'active',
    ]);

    $record = ConsentRecord::query()->firstOrFail();
    expect($record->purpose_id)->toBe($purpose->id);
    expect($record->notice_id)->toBe($notice->id);
    expect($record->subject_identifier_hash)->toBe(ConsentRecord::hashIdentifier('subject@example.com'));

    $entry = AuditLogEntry::query()->where('action', 'consent.capture')->where('resource_id', $record->id)->first();
    expect($entry)->not->toBeNull();
    expect($entry->actor_type)->toBe('data_subject');
});

test('capturing consent against a historical, non-current notice version is accepted', function () {
    $purpose = ConsentPurpose::factory()->create();
    $noticeV1 = ConsentNotice::factory()->create(['purpose_id' => $purpose->id, 'version' => 1]);
    ConsentNotice::factory()->create(['purpose_id' => $purpose->id, 'version' => 2]);
    $purpose->forceFill(['current_notice_id' => $noticeV1->id])->save();

    // The widget shown was version 1, even though version 2 now exists —
    // the capture must record what was actually shown (US-003 AC2).
    $response = $this->postJson('/api/v1/consent', [
        'purpose_id' => $purpose->id,
        'notice_version' => 1,
        'subject_identifier' => 'subject@example.com',
    ]);

    $response->assertStatus(201)->assertJson(['notice_version' => 1]);

    $record = ConsentRecord::query()->firstOrFail();
    expect($record->notice_id)->toBe($noticeV1->id);
});

test('capturing consent with a missing required field returns 422 and creates no partial record', function () {
    $purpose = ConsentPurpose::factory()->create();
    ConsentNotice::factory()->create(['purpose_id' => $purpose->id, 'version' => 1]);

    $response = $this->postJson('/api/v1/consent', [
        'purpose_id' => $purpose->id,
        'notice_version' => 1,
        // subject_identifier omitted
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['subject_identifier']);
    expect(ConsentRecord::query()->count())->toBe(0);
});

test('capturing consent against a notice version that does not exist for the purpose returns 422 with no partial record', function () {
    $purpose = ConsentPurpose::factory()->create();
    ConsentNotice::factory()->create(['purpose_id' => $purpose->id, 'version' => 1]);

    $response = $this->postJson('/api/v1/consent', [
        'purpose_id' => $purpose->id,
        'notice_version' => 99,
        'subject_identifier' => 'subject@example.com',
    ]);

    $response->assertStatus(422);
    expect(ConsentRecord::query()->count())->toBe(0);
});

test('the audit log chain for consent actions verifies and detects tampering', function () {
    $purpose = ConsentPurpose::factory()->create();
    ConsentNotice::factory()->create(['purpose_id' => $purpose->id, 'version' => 1]);

    $this->postJson('/api/v1/consent', [
        'purpose_id' => $purpose->id,
        'notice_version' => 1,
        'subject_identifier' => 'subject-a@example.com',
    ])->assertStatus(201);

    $this->postJson('/api/v1/consent', [
        'purpose_id' => $purpose->id,
        'notice_version' => 1,
        'subject_identifier' => 'subject-b@example.com',
    ])->assertStatus(201);

    $logger = app(AuditLogger::class);
    expect($logger->verifyChain())->toBe(['valid' => true, 'brokenAtSequence' => null]);

    // R-01: the app's own runtime connection can no longer UPDATE
    // audit_log_entries at all (verified in
    // tests/Feature/AuditLogGrantEnforcementTest.php) — this simulated
    // tampering now has to go through the schema-owning connection,
    // which is realistic anyway: the threat ADR-0003's hash chain
    // defends against is someone with direct/elevated DB access, not the
    // app's own restricted credential. That connection is a genuinely
    // separate Postgres session, so the entries created above (via the
    // default connection, inside RefreshDatabase's still-open
    // transaction) must be committed first — otherwise they're invisible
    // to it and the UPDATE below would silently match zero rows.
    DB::commit();

    $entry = AuditLogEntry::query()->orderBy('sequence')->first();
    DB::connection('pgsql_migrate')->table('audit_log_entries')->where('id', $entry->id)->update(['action' => 'consent.capture.tampered']);

    $result = $logger->verifyChain();
    expect($result['valid'])->toBeFalse();
    expect($result['brokenAtSequence'])->toBe($entry->sequence);
});
