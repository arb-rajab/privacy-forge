<?php

use App\Models\AuditLogEntry;
use App\Models\ConsentRecord;
use App\Models\DataCategory;
use App\Models\DeletionCertificate;
use App\Models\DsarRequest;
use App\Models\RetentionExecution;
use App\Models\RetentionPolicy;
use Illuminate\Database\QueryException;

// US-012/FR-015 — scheduled retention execution
// (App\Console\Commands\ExecuteRetentionPoliciesCommand). Deliberately
// wired against the same consent_records/dsar_requests tables the rest
// of the application actually uses (not a synthetic fixture table no
// other code path touches), per this session's explicit instruction to
// avoid repeating the "mechanism tested but not wired to reality" gap
// Session 10 found in Session 8's export-bundle work — see
// docs/project-memory/12-session-handoff.md's Session 8 TTL clarification.

test('US-012: erases eligible withdrawn consent records and issues a deletion certificate distinct from a DSAR-driven one', function () {
    $category = DataCategory::factory()->create(['subject_table' => DataCategory::SUBJECT_TABLE_CONSENT_RECORDS]);
    $policy = RetentionPolicy::factory()->create([
        'data_category_id' => $category->id,
        'retention_period_days' => 30,
        'post_expiry_action' => 'erase',
    ]);

    $eligible = ConsentRecord::factory()->count(2)->create([
        'status' => 'withdrawn',
        'withdrawn_at' => now()->subDays(60),
    ]);
    $tooRecent = ConsentRecord::factory()->create([
        'status' => 'withdrawn',
        'withdrawn_at' => now()->subDays(2),
    ]);

    $this->artisan('retention:execute')->assertExitCode(0);

    foreach ($eligible as $record) {
        expect(ConsentRecord::find($record->id))->toBeNull();
    }
    expect(ConsentRecord::find($tooRecent->id))->not->toBeNull();

    $execution = RetentionExecution::query()->where('retention_policy_id', $policy->id)->firstOrFail();
    expect($execution->mode)->toBe('real');
    expect($execution->affected_record_count)->toBe(2);
    expect($execution->certificate_id)->not->toBeNull();

    $certificate = DeletionCertificate::query()->findOrFail($execution->certificate_id);
    expect($certificate->retention_execution_id)->toBe($execution->id);
    expect($certificate->dsar_request_id)->toBeNull();
    expect($certificate->summary)->toContain('2 record(s) erased');

    // US-014: retention actions are audit-logged even though this path
    // never calls PolicyEvaluator (see the command's own header comment
    // for why) — actor_type=system, no ABAC policy backs a
    // scheduler-triggered action, so policy_id is correctly null.
    $entry = AuditLogEntry::query()
        ->where('action', 'retention.execution.run')
        ->where('resource_id', $policy->id)
        ->first();

    expect($entry)->not->toBeNull();
    expect($entry->actor_type)->toBe('system');
    expect($entry->actor_user_id)->toBeNull();
    expect($entry->decision)->toBe('allow');
    expect($entry->policy_id)->toBeNull();
});

test('US-012: anonymises eligible terminal DSAR requests, keeping status/timestamps but severing identifying fields', function () {
    $category = DataCategory::factory()->forDsarRequests()->create();
    $policy = RetentionPolicy::factory()->anonymise()->create([
        'data_category_id' => $category->id,
        'retention_period_days' => 30,
    ]);

    $eligible = DsarRequest::factory()->create(['status' => 'complete']);
    // created_at is not mass-assignable/factory-overridable here (it's an
    // automatic Eloquent timestamp, reset to now() on insert regardless of
    // any fill() value) — backdate it explicitly, post-insert, the same
    // way the rest of this codebase uses forceFill for anything
    // timestamp-adjacent that isn't a plain custom column.
    $eligible->forceFill(['created_at' => now()->subDays(60)])->save();
    $originalHash = $eligible->subject_identifier_hash;

    $tooRecent = DsarRequest::factory()->create(['status' => 'complete']);
    $originalTooRecentHash = $tooRecent->subject_identifier_hash;

    $stillOpen = DsarRequest::factory()->create(['status' => 'pending_verification']);
    $stillOpen->forceFill(['created_at' => now()->subDays(60)])->save();
    $originalStillOpenHash = $stillOpen->subject_identifier_hash;

    $this->artisan('retention:execute')->assertExitCode(0);

    $eligible->refresh();
    expect($eligible->status)->toBe('complete'); // row survives — anonymise, not erase
    expect($eligible->subject_identifier)->toBe('anonymised');
    expect($eligible->subject_identifier_hash)->not->toBe($originalHash);
    expect($eligible->subject_identifier_hash)->toStartWith('anonymised-');

    $tooRecent->refresh();
    expect($tooRecent->subject_identifier_hash)->toBe($originalTooRecentHash);

    $stillOpen->refresh();
    expect($stillOpen->status)->toBe('pending_verification');
    expect($stillOpen->subject_identifier_hash)->toBe($originalStillOpenHash);

    $execution = RetentionExecution::query()->where('retention_policy_id', $policy->id)->firstOrFail();
    expect($execution->affected_record_count)->toBe(1);

    $certificate = DeletionCertificate::query()->findOrFail($execution->certificate_id);
    expect($certificate->summary)->toContain('1 record(s) anonymised');
});

test('a deprecated retention policy is not processed by the scheduled command', function () {
    $category = DataCategory::factory()->create();
    RetentionPolicy::factory()->deprecated()->create(['data_category_id' => $category->id]);

    $record = ConsentRecord::factory()->create(['status' => 'withdrawn', 'withdrawn_at' => now()->subYears(2)]);

    $this->artisan('retention:execute')->assertExitCode(0);

    expect(ConsentRecord::find($record->id))->not->toBeNull();
    expect(RetentionExecution::query()->count())->toBe(0);
});

test('with no active retention policies, the scheduled command is a no-op', function () {
    $this->artisan('retention:execute')->assertExitCode(0);

    expect(RetentionExecution::query()->count())->toBe(0);
});

test('the deletion_certificates_exactly_one_source constraint rejects a certificate naming both sources at once', function () {
    $dsar = DsarRequest::factory()->create();
    $execution = RetentionExecution::factory()->real()->create();

    expect(fn () => DeletionCertificate::create([
        'dsar_request_id' => $dsar->id,
        'retention_execution_id' => $execution->id,
        'summary' => 'invalid — both sources set',
        'issued_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('the deletion_certificates_exactly_one_source constraint rejects a certificate naming no source at all', function () {
    expect(fn () => DeletionCertificate::create([
        'dsar_request_id' => null,
        'retention_execution_id' => null,
        'summary' => 'invalid — no source set',
        'issued_at' => now(),
    ]))->toThrow(QueryException::class);
});
