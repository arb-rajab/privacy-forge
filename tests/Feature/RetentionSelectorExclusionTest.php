<?php

use App\Models\Connector;
use App\Models\ConsentRecord;
use App\Models\DataCategory;
use App\Models\DeletionCertificate;
use App\Models\DsarConnectorTask;
use App\Models\DsarRequest;
use App\Models\PolicyDefinition;
use App\Models\RetentionExecution;
use App\Models\RetentionPolicy;
use App\Models\User;
use App\Services\ConnectorSignatureService;
use App\Services\RetentionSelector;
use Illuminate\Support\Facades\Http;

// Session 12, Part A — cross-session integration check between retention
// execution (Session 11, US-010/011/012) and DSAR-driven erasure (Session
// 8, US-009): does a later retention sweep ever re-select (and
// re-certify) data that's already gone, either because a DSAR erased it or
// because a prior retention run already did? Two distinct findings below.

// FINDING 1 (premise correction, no bug): DSAR-driven erasure (US-009) is
// dispatched exclusively to external connectors over the signed webhook
// contract (ADR-0004) — DsarCompletionEvaluator only ever updates the
// DsarRequest's own status column and generates a DeletionCertificate; it
// never touches a ConsentRecord row, and it never erases/anonymises the
// DsarRequest row itself. RetentionExecutor is the *only* code path in this
// application that ever mutates consent_records/dsar_requests content. So
// there is no scenario today where a completed DSAR erasure has already
// removed data a retention sweep would otherwise re-select — demonstrated
// directly below, rather than assumed from reading the source.
test('a completed DSAR erasure never mutates local consent_records data, so retention remains the only local erasure path for it', function () {
    PolicyDefinition::factory()->create(); // dsar.identity.verify
    PolicyDefinition::factory()->forErasureApproval()->create();
    $connector = Connector::factory()->create(['webhook_url' => 'https://connector.example.test/hook']);
    $verifier = User::factory()->privacyManager()->create();
    $approver = User::factory()->privacyManager()->create();

    // Same real-world subject on both sides: DsarRequest::hashIdentifier()
    // and ConsentRecord::hashIdentifier() are the identical HMAC formula,
    // so hashing the same raw identifier ties the two rows together the
    // same way ExportBundleAssembler already relies on for export.
    $rawIdentifier = 'shared-subject@example.test';
    $sharedHash = ConsentRecord::hashIdentifier($rawIdentifier);

    // Eligible under a 30-day erase policy on consent_records — this is
    // exactly the record a retention sweep is supposed to pick up.
    $category = DataCategory::factory()->create(['subject_table' => DataCategory::SUBJECT_TABLE_CONSENT_RECORDS]);
    $policy = RetentionPolicy::factory()->create([
        'data_category_id' => $category->id,
        'retention_period_days' => 30,
    ]);
    $originalWithdrawnAt = now()->subDays(60)->startOfSecond();
    $consentRecord = ConsentRecord::factory()->create([
        'subject_identifier_hash' => $sharedHash,
        'status' => 'withdrawn',
        'withdrawn_at' => $originalWithdrawnAt,
    ]);

    $dsar = DsarRequest::factory()->create([
        'subject_identifier' => $rawIdentifier,
        'subject_identifier_hash' => $sharedHash,
        'request_type' => 'erasure',
        'status' => 'pending_verification',
    ]);

    Http::fake(['connector.example.test/*' => Http::response('', 200)]);

    $this->actingAs($verifier)->postJson("/api/v1/admin/dsar/{$dsar->id}/verify-identity")->assertStatus(200);
    $this->actingAs($approver)->postJson("/api/v1/admin/dsar/{$dsar->id}/approve-erasure")->assertStatus(200);

    $task = DsarConnectorTask::query()->where('dsar_request_id', $dsar->id)->firstOrFail();
    $signer = app(ConnectorSignatureService::class);
    $timestamp = (string) now()->timestamp;
    $body = json_encode(['status' => 'success']);
    $signature = $signer->sign($connector->secret_hash, $timestamp, $body);

    $this->withHeaders(['X-Connector-Signature' => $signature, 'X-Connector-Timestamp' => $timestamp])
        ->postJson("/api/v1/connector-callback/{$task->id}", ['status' => 'success'])
        ->assertStatus(200);

    // The DSAR itself reached its genuine terminal state, with a
    // DSAR-sourced certificate — the erasure "happened" from the DSAR's
    // point of view.
    $dsar->refresh();
    expect($dsar->status)->toBe('complete');
    $dsarCertificate = DeletionCertificate::query()->where('dsar_request_id', $dsar->id)->first();
    expect($dsarCertificate)->not->toBeNull();

    // And yet the ConsentRecord this subject actually holds locally is
    // completely untouched — same status, same withdrawn_at, same hash.
    // Nothing about the DSAR's completion erased or anonymised it.
    $consentRecord->refresh();
    expect($consentRecord->status)->toBe('withdrawn');
    expect($consentRecord->subject_identifier_hash)->toBe($sharedHash);
    expect($consentRecord->withdrawn_at->equalTo($originalWithdrawnAt))->toBeTrue();

    // Which means RetentionSelector correctly still selects it — there is
    // nothing to "exclude" on account of the DSAR, because the DSAR never
    // erased it in the first place. Retention itself remains the only
    // thing that will ever actually remove this row.
    $candidateIds = app(RetentionSelector::class)->query($policy)->pluck('id')->all();
    expect($candidateIds)->toContain($consentRecord->id);
});

// FINDING 2 (real bug, fixed this session): RetentionSelector's own query
// had no way to tell "already anonymised by a previous run of this exact
// policy" apart from "still eligible" — anonymise() deliberately leaves
// status/withdrawn_at/created_at untouched (that's the whole point of
// anonymise vs erase: the row survives for aggregate value), but the
// selector's WHERE clause only ever looked at those columns. Left
// unfixed, every subsequent scheduled `retention:execute` run would
// re-select the same already-anonymised row forever, re-running
// anonymise() pointlessly and — the actually harmful part — minting a
// fresh RetentionExecution + DeletionCertificate every single day
// asserting "1 record(s) anonymised" for a record that was actually
// anonymised days or weeks earlier. Fixed in RetentionSelector::query() by
// excluding rows whose subject_identifier_hash already carries the
// 'anonymised-' marker both anonymise() methods write.
test('a consent record already anonymised by a prior retention run is not re-selected or re-certified by a later run', function () {
    $category = DataCategory::factory()->create(['subject_table' => DataCategory::SUBJECT_TABLE_CONSENT_RECORDS]);
    $policy = RetentionPolicy::factory()->anonymise()->create([
        'data_category_id' => $category->id,
        'retention_period_days' => 30,
    ]);
    $record = ConsentRecord::factory()->create([
        'status' => 'withdrawn',
        'withdrawn_at' => now()->subDays(60),
    ]);

    $this->artisan('retention:execute')->assertExitCode(0);

    $record->refresh();
    expect($record->subject_identifier_hash)->toStartWith('anonymised-');
    $hashAfterFirstRun = $record->subject_identifier_hash;

    $firstExecution = RetentionExecution::query()->where('retention_policy_id', $policy->id)->firstOrFail();
    expect($firstExecution->affected_record_count)->toBe(1);

    // Time passes; the scheduler fires again. Nothing new is eligible —
    // the only candidate that ever existed was already handled.
    $this->artisan('retention:execute')->assertExitCode(0);

    $record->refresh();
    // Not re-anonymised a second time (a fresh anonymise() call would mint
    // a brand new random UUID suffix, so an unchanged hash proves it).
    expect($record->subject_identifier_hash)->toBe($hashAfterFirstRun);

    $secondExecution = RetentionExecution::query()
        ->where('retention_policy_id', $policy->id)
        ->where('id', '!=', $firstExecution->id)
        ->firstOrFail();
    expect($secondExecution->affected_record_count)->toBe(0);

    $secondCertificate = DeletionCertificate::query()->findOrFail($secondExecution->certificate_id);
    expect($secondCertificate->summary)->toContain('0 record(s) anonymised');
});

test('a DSAR request already anonymised by a prior retention run is not re-selected or re-certified by a later run', function () {
    $category = DataCategory::factory()->forDsarRequests()->create();
    $policy = RetentionPolicy::factory()->anonymise()->create([
        'data_category_id' => $category->id,
        'retention_period_days' => 30,
    ]);
    $dsar = DsarRequest::factory()->create(['status' => 'complete']);
    $dsar->forceFill(['created_at' => now()->subDays(60)])->save();

    $this->artisan('retention:execute')->assertExitCode(0);

    $dsar->refresh();
    expect($dsar->subject_identifier_hash)->toStartWith('anonymised-');
    $hashAfterFirstRun = $dsar->subject_identifier_hash;

    $firstExecution = RetentionExecution::query()->where('retention_policy_id', $policy->id)->firstOrFail();
    expect($firstExecution->affected_record_count)->toBe(1);

    $this->artisan('retention:execute')->assertExitCode(0);

    $dsar->refresh();
    expect($dsar->subject_identifier_hash)->toBe($hashAfterFirstRun);

    $secondExecution = RetentionExecution::query()
        ->where('retention_policy_id', $policy->id)
        ->where('id', '!=', $firstExecution->id)
        ->firstOrFail();
    expect($secondExecution->affected_record_count)->toBe(0);
});
