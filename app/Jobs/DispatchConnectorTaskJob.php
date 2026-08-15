<?php

namespace App\Jobs;

use App\Models\DsarConnectorTask;
use App\Services\ConnectorSignatureService;
use App\Services\DsarCompletionEvaluator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

// ADR-0004 outbound half: one job per (dsar_request, connector) task,
// keyed by the task's own id — a restarted worker re-processing this job
// looks the task up fresh rather than carrying stale state, so a crash
// mid-delivery can't double-dispatch against a task that's already moved
// on (03-architecture.md, "Queue worker crashes mid-task").
//
// Retries model *delivery* failure only (T-07's threat is a forged
// inbound callback; this job's failure mode is "the connector never
// received the webhook at all"). Exhausting retries marks the task
// failed outright — the connector never got a chance to report anything,
// so there's nothing to wait on (FR-009).
class DispatchConnectorTaskJob implements ShouldQueue
{
    use Queueable;

    public int $tries;

    public function __construct(public readonly string $dsarConnectorTaskId)
    {
        $this->tries = max(1, (int) config('connectors.webhook_max_retry_attempts'));
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        $attempts = max(1, $this->tries - 1);

        // 15s, 30s, 60s, 120s, ... capped at 5 minutes. Laravel reuses the
        // array's last value for any retry beyond its length, so this
        // only needs one entry per retry (tries - 1), not per attempt.
        return array_map(
            static fn (int $n): int => min(300, 15 * (2 ** ($n - 1))),
            range(1, $attempts),
        );
    }

    public function handle(ConnectorSignatureService $signer): void
    {
        $task = DsarConnectorTask::with(['connector', 'dsarRequest'])->find($this->dsarConnectorTaskId);

        // Idempotent no-op: already resolved (by a callback that beat this
        // job to it, or a prior attempt) or the row is simply gone.
        if ($task === null || $task->isTerminal()) {
            return;
        }

        $connector = $task->connector;

        if ($connector === null || $connector->status !== 'active') {
            // Disabled mid-flight (e.g. T-09 auto-disable on a sibling
            // task) — don't deliver to a connector no longer trusted.
            // Leaves the task 'pending'; it is not this connector's fault
            // in a way that should count against it as a delivery failure.
            return;
        }

        // FK guarantees this in practice; guards a task orphaned by direct
        // DB tampering.
        $dsarRequest = $task->dsarRequest;
        if ($dsarRequest === null) {
            return;
        }

        // Shape matches 05-api-contracts.md's documented outbound webhook
        // contract exactly: subject_identifier is what lets the connector
        // actually locate the subject in its own system (without it, the
        // webhook alone can't be acted on), and schema_version is the
        // independent versioning hook that document commits to, since
        // connectors are external, third-party-operated systems that can't
        // be forced to upgrade in lockstep with this application.
        $payload = [
            'task_id' => $task->id,
            'dsar_id' => $task->dsar_request_id,
            'task_type' => $task->task_type,
            'subject_identifier' => $dsarRequest->subject_identifier,
            'schema_version' => 1,
        ];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $signature = $signer->sign($connector->secret_hash, $timestamp, $body);

        $task->increment('attempt_count');
        $task->forceFill(['dispatched_at' => now()])->save();

        $response = Http::withHeaders([
            'X-Connector-Signature' => $signature,
            'X-Connector-Timestamp' => $timestamp,
        ])->withBody($body, 'application/json')->post($connector->webhook_url);

        if (! $response->successful()) {
            throw new RuntimeException("Connector webhook delivery failed with HTTP status {$response->status()}.");
        }
    }

    public function failed(?Throwable $exception): void
    {
        $task = DsarConnectorTask::with('dsarRequest')->find($this->dsarConnectorTaskId);

        $dsarRequest = $task?->dsarRequest;

        if ($task === null || $task->isTerminal() || $dsarRequest === null) {
            return;
        }

        $task->forceFill([
            'status' => 'failed',
            'failure_reason' => 'Webhook delivery failed after exhausting retries: '.($exception?->getMessage() ?? 'unknown error'),
            'completed_at' => now(),
        ])->save();

        app(DsarCompletionEvaluator::class)->evaluate($dsarRequest);
    }
}
