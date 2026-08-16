<?php

use App\Models\AuditLogEntry;
use App\Models\ConsentPurpose;
use App\Models\DataCategory;
use App\Models\PolicyDefinition;
use App\Models\RetentionPolicy;
use App\Models\User;

// Session 12 — ropa.export (US-013/FR-016, Art. 30 RTM row), the fifth
// registered sensitive action. Same split as RetentionPolicyManagementTest
// vs AuthorisationMatrixTest: the (role x ropa.export) matrix cells live
// in AuthorisationMatrixTest.php (format=csv there, the lighter format);
// this file covers what that matrix deliberately doesn't — both export
// formats' actual content, fail-closed reason codes, format validation,
// and — the point of this file — a live scenario proving the export
// reflects genuinely current data rather than a static/stubbed report.

test('an owner can export the RoPA as CSV, gated and audit-logged', function () {
    $gate = PolicyDefinition::factory()->forRopaExport()->create();
    $owner = User::factory()->owner()->create();
    ConsentPurpose::factory()->create(['name' => 'Marketing emails', 'lawful_basis' => 'consent']);

    $response = $this->actingAs($owner)->get('/api/v1/admin/ropa/export?format=csv');

    $response->assertStatus(200);
    expect($response->headers->get('Content-Type'))->toContain('text/csv');
    expect($response->getContent())->toContain('Marketing emails');
    expect($response->getContent())->toContain('consent');

    $entry = AuditLogEntry::query()
        ->where('action', 'ropa.export')
        ->first();

    expect($entry)->not->toBeNull();
    expect($entry->decision)->toBe('allow');
    expect($entry->policy_id)->toBe($gate->id);
});

test('a privacy manager can also export the RoPA as PDF', function () {
    PolicyDefinition::factory()->forRopaExport()->create();
    $manager = User::factory()->privacyManager()->create();
    ConsentPurpose::factory()->create();

    $response = $this->actingAs($manager)->get('/api/v1/admin/ropa/export?format=pdf');

    $response->assertStatus(200);
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
    // A genuine PDF-rendered document, not a stub — real PDFs open with
    // this exact magic header (dompdf's actual output, not a mock).
    expect(substr($response->getContent(), 0, 5))->toBe('%PDF-');
});

test('support staff cannot export the RoPA — denied by the ABAC policy, not silently allowed', function () {
    $gate = PolicyDefinition::factory()->forRopaExport()->create();
    $staff = User::factory()->supportStaff()->create();

    $response = $this->actingAs($staff)->getJson('/api/v1/admin/ropa/export?format=csv');

    $response->assertStatus(403);
    expect($response->json('policy_id'))->toBe($gate->id);

    $entry = AuditLogEntry::query()->where('action', 'ropa.export')->first();
    expect($entry->decision)->toBe('deny');
    expect($entry->reason_code)->toBe('policy_conditions_not_met');
});

test('fail-closed: a missing ropa.export policy denies even an Owner, and logs a policy_missing reason code (ADR-0006)', function () {
    expect(PolicyDefinition::query()->where('action_name', 'ropa.export')->exists())->toBeFalse();

    $owner = User::factory()->owner()->create();

    $response = $this->actingAs($owner)->getJson('/api/v1/admin/ropa/export?format=csv');

    $response->assertStatus(403);
    expect($response->json('policy_id'))->toBeNull();

    $entry = AuditLogEntry::query()->where('action', 'ropa.export')->first();
    expect($entry)->not->toBeNull();
    expect($entry->decision)->toBe('deny');
    expect($entry->reason_code)->toBe('policy_missing');
});

test('fail-closed: a malformed ropa.export condition denies even an Owner, and logs an evaluation_error reason code (ADR-0006)', function () {
    $gate = PolicyDefinition::factory()->forRopaExport()->create([
        'subject_conditions' => ['role' => 'not-a-valid-condition-object'],
    ]);
    $owner = User::factory()->owner()->create();

    $response = $this->actingAs($owner)->getJson('/api/v1/admin/ropa/export?format=csv');

    $response->assertStatus(403);
    expect($response->json('policy_id'))->toBeNull();

    $entry = AuditLogEntry::query()->where('action', 'ropa.export')->first();
    expect($entry->decision)->toBe('deny');
    expect($entry->reason_code)->toBe('evaluation_error');
    expect($gate->fresh()->status)->toBe('active');
});

test('validation: format must be pdf or csv', function () {
    PolicyDefinition::factory()->forRopaExport()->create();
    $owner = User::factory()->owner()->create();

    $response = $this->actingAs($owner)->getJson('/api/v1/admin/ropa/export?format=xml');

    $response->assertStatus(422);
});

// The centerpiece test of this session's Part B, per the brief's explicit
// instruction: proves the RoPA export reflects live state — a purpose
// linked to a data category and a retention policy, and a purpose that
// gets deprecated mid-scenario — rather than a static or stubbed report.
test('US-013 AC1: the RoPA export reflects live purposes, their linked retention period, and excludes a purpose once deprecated', function () {
    PolicyDefinition::factory()->forRopaExport()->create();
    $owner = User::factory()->owner()->create();

    $category = DataCategory::factory()->create([
        'name' => 'Marketing consent records',
        'description' => 'Consent records for the marketing-emails purpose.',
        'subject_table' => DataCategory::SUBJECT_TABLE_CONSENT_RECORDS,
    ]);
    RetentionPolicy::factory()->create([
        'data_category_id' => $category->id,
        'retention_period_days' => 400,
        'post_expiry_action' => 'anonymise',
    ]);

    $stayingActive = ConsentPurpose::factory()->create([
        'name' => 'Marketing emails',
        'lawful_basis' => 'consent',
        'data_category_id' => $category->id,
        'data_subjects_description' => 'Newsletter subscribers',
    ]);

    $toBeDeprecated = ConsentPurpose::factory()->create([
        'name' => 'Legacy SMS campaign',
        'lawful_basis' => 'legitimate_interests',
    ]);

    // Before deprecation: both purposes are active, both appear.
    $before = $this->actingAs($owner)->get('/api/v1/admin/ropa/export?format=csv')->getContent();
    expect($before)->toContain('Marketing emails');
    expect($before)->toContain('Legacy SMS campaign');
    expect($before)->toContain('400');
    expect($before)->toContain('anonymise');
    expect($before)->toContain('Newsletter subscribers');
    expect($before)->toContain('Marketing consent records');

    // Deprecate one purpose — a real state change via the existing
    // versioned-entity pattern (ConsentPurpose::status), not a fixture.
    $toBeDeprecated->forceFill(['status' => 'deprecated'])->save();

    // FR-016/US-013 AC1 says "covering all active purposes" explicitly —
    // the deprecated purpose is excluded from this compliance report
    // (historical accountability for it lives in the audit log instead,
    // not in a RoPA describing current processing activity).
    $after = $this->actingAs($owner)->get('/api/v1/admin/ropa/export?format=csv')->getContent();
    expect($after)->toContain('Marketing emails');
    expect($after)->not->toContain('Legacy SMS campaign');

    $stayingActive->refresh();
    expect($stayingActive->status)->toBe('active');
});

test('a purpose with no linked data category or retention policy is reported honestly, not fabricated', function () {
    PolicyDefinition::factory()->forRopaExport()->create();
    $owner = User::factory()->owner()->create();
    ConsentPurpose::factory()->create(['name' => 'Unclassified purpose', 'data_category_id' => null]);

    $response = $this->actingAs($owner)->get('/api/v1/admin/ropa/export?format=csv');

    $rows = array_map('str_getcsv', explode("\n", trim($response->getContent())));
    $header = $rows[0];
    $dataRow = $rows[1];
    $row = array_combine($header, $dataRow);

    expect($row['purpose_name'])->toBe('Unclassified purpose');
    expect($row['data_category'])->toBe('');
    expect($row['retention_period_days'])->toBe('');
});
