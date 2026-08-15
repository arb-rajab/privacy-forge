<?php

namespace App\Http\Controllers;

use App\Models\Connector;
use App\Models\DsarConnectorTask;
use App\Services\AuditLogger;
use App\Services\ConnectorSignatureService;
use App\Services\DsarCompletionEvaluator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

// ADR-0004 inbound half (B2 in 06-security-threat-model.md). Every
// failure path here returns 401 — including "task id doesn't exist" —
// because the OpenAPI contract only documents 200/401 for this endpoint,
// and distinguishing "invalid signature" from "unknown task" would hand
// an unauthenticated caller an existence oracle (the same T-05 reasoning
// already applied to the DSAR status endpoint).
class ConnectorCallbackController extends Controller
{
    public function __construct(
        private readonly ConnectorSignatureService $signer,
        private readonly AuditLogger $auditLogger,
        private readonly DsarCompletionEvaluator $completionEvaluator,
    ) {}

    public function handle(Request $request, string $taskId): JsonResponse
    {
        $signature = $request->header('X-Connector-Signature');
        $timestamp = $request->header('X-Connector-Timestamp');

        if (! is_string($signature) || $signature === '' || ! is_string($timestamp) || $timestamp === '') {
            return $this->unauthorized();
        }

        $task = DsarConnectorTask::with(['connector', 'dsarRequest'])->find($taskId);

        // The FK constraints guarantee both relations exist for a real
        // row; this also guards a task orphaned by direct DB tampering.
        if ($task === null || $task->connector === null || $task->dsarRequest === null) {
            return $this->unauthorized();
        }

        $connector = $task->connector;
        $dsarRequest = $task->dsarRequest;

        // T-07: signature computed over the exact raw body bytes, not a
        // re-serialisation of parsed input — matches the outbound side in
        // DispatchConnectorTaskJob.
        if (! $this->signer->verify($connector->secret_hash, $timestamp, $request->getContent(), $signature)) {
            return $this->unauthorized();
        }

        // T-08: reject replay of a validly-signed request outside the
        // tolerance window, independent of signature validity.
        $toleranceSeconds = (int) config('connectors.callback_signature_tolerance_seconds');
        if (! $this->signer->isTimestampFresh($timestamp, $toleranceSeconds)) {
            return $this->unauthorized();
        }

        if ($connector->status !== 'active') {
            // A disabled connector (e.g. auto-disabled by a prior T-09
            // anomaly) has no standing to submit further callbacks.
            return $this->unauthorized();
        }

        $validated = Validator::make($request->all(), [
            'status' => ['required', 'string', 'in:success,failed,partial'],
            'failure_reason' => ['nullable', 'string'],
        ])->validate();

        $newStatus = $validated['status'];
        $failureReason = $validated['failure_reason'] ?? null;

        if ($task->isTerminal()) {
            if ($task->status === $newStatus) {
                // T-08 idempotency: a legitimate connector re-sending the
                // same status for the same task is a no-op, not an anomaly.
                return response()->json([], 200);
            }

            // T-09: a *conflicting* terminal status for an already-terminal
            // task is a security anomaly, not a benign retry. The task
            // keeps its original terminal state and the connector is
            // auto-disabled pending manual review.
            $connector->forceFill(['status' => 'disabled'])->save();

            $this->auditLogger->record(
                actorType: 'connector',
                actor: null,
                action: 'connector.callback.anomaly',
                resourceType: 'dsar_connector_task',
                resourceId: $task->id,
                decision: 'deny',
                reasonCode: 'connector_status_conflict',
            );

            return response()->json([], 200);
        }

        $task->forceFill([
            'status' => $newStatus,
            'failure_reason' => $failureReason,
            'completed_at' => now(),
        ])->save();

        $this->auditLogger->record(
            actorType: 'connector',
            actor: null,
            action: 'connector.callback.received',
            resourceType: 'dsar_connector_task',
            resourceId: $task->id,
            decision: 'allow',
        );

        $this->completionEvaluator->evaluate($dsarRequest);

        return response()->json([], 200);
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json([
            'type' => 'about:blank',
            'title' => 'Unauthorized',
            'status' => 401,
            'detail' => 'Invalid or missing connector signature.',
        ], 401);
    }
}
