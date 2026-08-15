<?php

namespace App\Services;

use App\Models\DeletionCertificate;
use App\Models\DsarConnectorTask;
use App\Models\DsarRequest;
use Illuminate\Database\Eloquent\Collection;

// US-009/FR-011: "the system must never overstate what it achieved."
// Generated whenever every erasure task for a DSAR reaches a terminal
// state — success, failed, or partial alike (DsarCompletionEvaluator) —
// so an incomplete erasure still produces evidence, just honest evidence,
// rather than no certificate at all.
class DeletionCertificateGenerator
{
    /**
     * @param  Collection<int, DsarConnectorTask>  $tasks
     */
    public function generate(DsarRequest $dsar, Collection $tasks): DeletionCertificate
    {
        $tasks->loadMissing('connector');

        $confirmed = $tasks->where('status', 'success');
        $exceptions = $tasks->whereIn('status', ['failed', 'partial']);

        $summaryLines = $confirmed
            ->map(fn (DsarConnectorTask $task): string => sprintf(
                '%s confirmed erasure at %s.',
                $this->connectorName($task),
                $task->completed_at?->toIso8601String() ?? 'unknown time',
            ))
            ->values()
            ->all();

        $summary = $summaryLines === []
            ? 'No connector confirmed erasure.'
            : implode(' ', $summaryLines);

        $exceptionLines = $exceptions
            ->map(fn (DsarConnectorTask $task): string => sprintf(
                '%s did not confirm erasure (status: %s%s).',
                $this->connectorName($task),
                $task->status,
                $task->failure_reason !== null ? "; {$task->failure_reason}" : '',
            ))
            ->values()
            ->all();

        return DeletionCertificate::create([
            'dsar_request_id' => $dsar->id,
            'summary' => $summary,
            'exceptions' => $exceptionLines === [] ? null : implode(' ', $exceptionLines),
            'issued_at' => now(),
        ]);
    }

    // FK guarantees a connector row exists in practice; the explicit null
    // check (rather than ?->/??) is what keeps phpstan's inference stable
    // for this property, unlike the nullsafe form.
    private function connectorName(DsarConnectorTask $task): string
    {
        $connector = $task->connector;

        return $connector !== null ? $connector->name : 'Unknown connector';
    }
}
