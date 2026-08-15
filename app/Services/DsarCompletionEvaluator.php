<?php

namespace App\Services;

use App\Models\DsarConnectorTask;
use App\Models\DsarRequest;

// US-007/FR-009: rolls a DSAR's independently-tracked connector tasks up
// into the parent DSAR's own status. Called after every task state
// change (job exhaustion in DispatchConnectorTaskJob::failed(), or a
// connector callback in ConnectorCallbackController) — never assumes
// which caller triggered it, since either can be the one that completes
// the set.
class DsarCompletionEvaluator
{
    public function __construct(
        private readonly ExportBundleAssembler $exportBundleAssembler,
        private readonly DeletionCertificateGenerator $certificateGenerator,
    ) {}

    public function evaluate(DsarRequest $dsar): void
    {
        $tasks = DsarConnectorTask::query()->where('dsar_request_id', $dsar->id)->get();

        if ($tasks->isEmpty()) {
            return;
        }

        if ($tasks->contains(fn (DsarConnectorTask $task): bool => ! $task->isTerminal())) {
            return;
        }

        $allSuccess = $tasks->every(fn (DsarConnectorTask $task): bool => $task->status === 'success');

        $dsar->forceFill([
            'status' => $allSuccess ? 'complete' : 'partially_complete',
        ])->save();

        if (in_array($dsar->request_type, ['export', 'access'], true) && $allSuccess) {
            // Partial export/access: no bundle is assembled this session
            // (US-008's AC is scoped to "given all connector export tasks
            // succeed") — the failure is still visible via the task list
            // itself (FR-009), just not via a downloadable bundle.
            $this->exportBundleAssembler->assemble($dsar);
        }

        if ($dsar->request_type === 'erasure') {
            // Unlike export, a certificate is produced either way — US-009
            // explicitly requires the exception case to be stated, not
            // silently skipped (FR-011).
            $this->certificateGenerator->generate($dsar, $tasks);
        }
    }
}
