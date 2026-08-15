<?php

namespace App\Services;

use App\Models\ConsentRecord;
use App\Models\DeletionCertificate;
use App\Models\DsarRequest;
use App\Models\RetentionExecution;
use App\Models\RetentionPolicy;

// ADR-0002, option B: this is the "executor" half — it consumes
// RetentionSelector's query and is the only place that branches on mode.
// preview() (US-011/FR-012) never mutates a record; execute() (US-012/
// FR-015) is the only method that does. Both methods query through the
// exact same RetentionSelector::query() call, which is what the parity
// test (tests/Feature/RetentionDryRunParityTest.php) asserts directly.
class RetentionExecutor
{
    // openapi.yaml's RetentionPreview schema documents sample_record_ids
    // as "a sample, not necessarily exhaustive for very large sets" —
    // affected_record_count (and the parity test) still reflect the full
    // candidate set via count(), independent of this cap.
    private const PREVIEW_SAMPLE_LIMIT = 100;

    public function __construct(private readonly RetentionSelector $selector) {}

    /**
     * @return array{execution: RetentionExecution, sampleIds: array<int, string>}
     */
    public function preview(RetentionPolicy $policy): array
    {
        $query = $this->selector->query($policy);
        $count = (clone $query)->count();
        $sampleIds = (clone $query)->limit(self::PREVIEW_SAMPLE_LIMIT)->pluck('id')->all();

        // ADR-0002's consequences are explicit that a dry run is not
        // "free": it leaves behind this row (mode=dry_run,
        // certificate_id=null) just as a real run leaves behind a
        // certificate, so both paths are auditable.
        $execution = RetentionExecution::create([
            'retention_policy_id' => $policy->id,
            'mode' => RetentionExecution::MODE_DRY_RUN,
            'affected_record_count' => $count,
            'certificate_id' => null,
            'executed_at' => now(),
        ]);

        return ['execution' => $execution, 'sampleIds' => $sampleIds];
    }

    public function execute(RetentionPolicy $policy): RetentionExecution
    {
        $records = $this->selector->query($policy)->get();
        $affectedCount = $records->count();

        foreach ($records as $record) {
            $this->apply($policy, $record);
        }

        $execution = RetentionExecution::create([
            'retention_policy_id' => $policy->id,
            'mode' => RetentionExecution::MODE_REAL,
            'affected_record_count' => $affectedCount,
            'certificate_id' => null,
            'executed_at' => now(),
        ]);

        // Certificate references the execution, then the execution is
        // updated to point back at the certificate (04-data-model.md's
        // ERD gives RETENTION_EXECUTION its own certificate_id, unlike
        // DSAR_REQUEST) — the same two-step, circular-reference pattern
        // consent_purposes.current_notice_id already established, since
        // neither row can name the other's id before both exist.
        $certificate = DeletionCertificate::create([
            'retention_execution_id' => $execution->id,
            'summary' => $this->summarise($policy, $affectedCount),
            'exceptions' => null,
            'issued_at' => now(),
        ]);

        $execution->forceFill(['certificate_id' => $certificate->id])->save();

        return $execution;
    }

    private function apply(RetentionPolicy $policy, ConsentRecord|DsarRequest $record): void
    {
        if ($policy->post_expiry_action === RetentionPolicy::ACTION_ANONYMISE) {
            $record->anonymise();

            return;
        }

        // ConsentRecord::delete() always throws (it guards the withdrawal
        // flow); retentionErase() is its documented, deliberate bypass for
        // exactly this call site. DsarRequest has no such guard.
        if ($record instanceof ConsentRecord) {
            $record->retentionErase();

            return;
        }

        $record->delete();
    }

    private function summarise(RetentionPolicy $policy, int $affectedCount): string
    {
        $actionWord = $policy->post_expiry_action === RetentionPolicy::ACTION_ANONYMISE ? 'anonymised' : 'erased';

        // FK guarantees a category row exists in practice (see
        // RetentionSelector::query()'s identical comment); explicit
        // === null check keeps phpstan's inference stable, same as there.
        $category = $policy->dataCategory;
        $categoryName = $category !== null ? $category->name : 'Unknown data category';

        return sprintf(
            '%d record(s) %s under retention policy %s (data category: %s, retention period: %d day(s)).',
            $affectedCount,
            $actionWord,
            $policy->id,
            $categoryName,
            $policy->retention_period_days,
        );
    }
}
