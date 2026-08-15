<?php

use App\Models\ConsentRecord;
use App\Models\DataCategory;
use App\Models\PolicyDefinition;
use App\Models\RetentionExecution;
use App\Models\RetentionPolicy;
use App\Models\User;
use App\Services\RetentionSelector;

// ADR-0002/FR-012/US-011 — the centerpiece test of this session, per the
// ADR's own stated reason for existing: "a dry run that could diverge
// from the real run defeats the entire purpose of previewing a deletion
// before it happens." This is asserted directly, not inferred from
// reading RetentionSelector/RetentionExecutor's source: dry-run candidate
// IDs and a subsequent real run's affected IDs, given unchanged data,
// must be identical.

test('US-011 AC2/ADR-0002: dry-run candidate IDs and a subsequent real run\'s affected IDs are identical given unchanged data', function () {
    PolicyDefinition::factory()->forRetentionPolicyManage()->create();
    $manager = User::factory()->privacyManager()->create();

    $category = DataCategory::factory()->create(['subject_table' => DataCategory::SUBJECT_TABLE_CONSENT_RECORDS]);
    $policy = RetentionPolicy::factory()->create([
        'data_category_id' => $category->id,
        'retention_period_days' => 30,
        'post_expiry_action' => 'erase',
    ]);

    // Eligible: withdrawn well past the 30-day retention period.
    $eligible = ConsentRecord::factory()->count(3)->create([
        'status' => 'withdrawn',
        'withdrawn_at' => now()->subDays(45),
    ]);

    // Not eligible: withdrawn, but too recently.
    $tooRecent = ConsentRecord::factory()->create([
        'status' => 'withdrawn',
        'withdrawn_at' => now()->subDays(5),
    ]);

    // Not eligible: still active consent, regardless of age — "never
    // auto-deleted while a related lawful-basis question is open"
    // (04-data-model.md).
    $stillActive = ConsentRecord::factory()->create(['status' => 'active']);

    $expectedCandidateIds = $eligible->pluck('id')->sort()->values()->all();

    // The selector is the single source of truth (ADR-0002) — compute the
    // candidate set independently of both the dry-run and real-run call
    // sites below, as the referee this test checks both against.
    $selector = app(RetentionSelector::class);
    $refereeIdsBeforeDryRun = $selector->query($policy)->pluck('id')->sort()->values()->all();
    expect($refereeIdsBeforeDryRun)->toBe($expectedCandidateIds);

    // Dry run, via the real HTTP endpoint (US-011: no side effects).
    $dryRunResponse = $this->actingAs($manager)
        ->postJson("/api/v1/admin/retention-policies/{$policy->id}/dry-run");

    $dryRunResponse->assertStatus(200);
    expect($dryRunResponse->json('policy_id'))->toBe($policy->id);
    expect($dryRunResponse->json('affected_record_count'))->toBe(3);

    $dryRunSampleIds = collect($dryRunResponse->json('sample_record_ids'))->sort()->values()->all();
    expect($dryRunSampleIds)->toBe($expectedCandidateIds);

    // No side effects: every record — eligible or not — is untouched.
    expect(ConsentRecord::query()->whereKey($eligible->pluck('id'))->count())->toBe(3);
    expect(ConsentRecord::find($tooRecent->id))->not->toBeNull();
    expect(ConsentRecord::find($stillActive->id))->not->toBeNull();

    // A dry run is not "free" (ADR-0002) — it leaves behind its own
    // RetentionExecution row.
    $dryRunExecution = RetentionExecution::query()->where('mode', 'dry_run')->latest('executed_at')->first();
    expect($dryRunExecution)->not->toBeNull();
    expect($dryRunExecution->affected_record_count)->toBe(3);
    expect($dryRunExecution->certificate_id)->toBeNull();

    // Time passes; data is unchanged. Re-querying the selector must return
    // the exact same candidate set — this is what "given unchanged data"
    // means concretely.
    $refereeIdsBeforeRealRun = $selector->query($policy)->pluck('id')->sort()->values()->all();
    expect($refereeIdsBeforeRealRun)->toBe($expectedCandidateIds);

    // Real run, via the actual scheduled command (US-012) — not the
    // executor called directly — so this exercises the real production
    // trigger, not just the service layer.
    $this->artisan('retention:execute')->assertExitCode(0);

    $realExecution = RetentionExecution::query()->where('mode', 'real')->latest('executed_at')->first();
    expect($realExecution)->not->toBeNull();
    expect($realExecution->retention_policy_id)->toBe($policy->id);

    // The single most important assertion in this file: the real run's
    // affected count matches the dry run's exactly.
    expect($realExecution->affected_record_count)->toBe($dryRunExecution->affected_record_count);
    expect($realExecution->affected_record_count)->toBe(count($expectedCandidateIds));

    // And the *identifiers* match too, not just the count — confirmed by
    // erasure: exactly the previewed IDs are gone, nothing else.
    foreach ($expectedCandidateIds as $id) {
        expect(ConsentRecord::find($id))->toBeNull();
    }
    expect(ConsentRecord::find($tooRecent->id))->not->toBeNull();
    expect(ConsentRecord::find($stillActive->id))->not->toBeNull();
});
