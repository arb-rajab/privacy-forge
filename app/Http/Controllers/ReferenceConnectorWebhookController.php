<?php

namespace App\Http\Controllers;

use App\Models\DsarConnectorTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

// ADR-0004/FR-019's reference/stub connector — the inbound half. This
// plays the role of the external, third-party-operated connector that
// DispatchConnectorTaskJob's outbound webhook is addressed to: it
// receives that signed webhook over a genuinely separate real HTTP
// request (in the docker-compose demo, one that actually crosses from
// the `worker` container to the `app` container over the network — not
// Http::fake(), not a direct method call), and then calls back to
// /api/v1/connector-callback/{taskId} with its own independently signed
// response, over real HTTP again. This is what R-06
// (docs/project-memory/10-risk-register.md) found missing: without it, a
// fresh instance's first erasure DSAR could only ever settle on
// `partially_complete`, never `complete`, because nothing answered the
// webhook at all.
//
// It deliberately reimplements the HMAC-over-"timestamp.body" contract
// from docs/project-memory/05-api-contracts.md by hand, rather than
// reusing ConnectorSignatureService — matching what an actual
// third-party connector author would have to do, working only from the
// documented contract rather than this application's own source.
//
// Honest limitation, stated rather than glossed over: it reads the
// shared secret straight from the `connectors` table this application
// owns. A real, fully independent connector would instead have been
// told that secret out-of-band exactly once, at registration time
// (RegisterReferenceConnectorCommand's one-time printout), and would
// store it in its own separate configuration — not a shared database.
// That shortcut is accepted here because this is a same-repository
// reference/stub built to prove the wire contract (ADR-0004's own
// framing), not a template for a real connector integration.
//
// This route is reachable by anyone on a real deployment (it sits
// alongside /api/v1/connector-callback/{taskId}, not behind staff
// auth), so it follows that endpoint's own T-05-style rule: "unknown
// task" and "bad signature" must be indistinguishable to an
// unauthenticated caller, or the response itself becomes an existence
// oracle for task ids.
class ReferenceConnectorWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $body = $request->getContent();
        $payload = json_decode($body, true);
        $taskId = is_array($payload) ? ($payload['task_id'] ?? null) : null;

        if (! is_string($taskId) || $taskId === '') {
            return response()->json(['error' => 'malformed webhook body'], 400);
        }

        $signature = $request->header('X-Connector-Signature');
        $timestamp = $request->header('X-Connector-Timestamp');
        if (! is_string($signature) || ! is_string($timestamp)) {
            return $this->unauthorized();
        }

        $task = DsarConnectorTask::with('connector')->find($taskId);
        if ($task === null || $task->connector === null) {
            return $this->unauthorized();
        }

        $secret = $task->connector->secret_hash;
        $expectedSignature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);
        if (! hash_equals($expectedSignature, $signature)) {
            return $this->unauthorized();
        }

        // The stub's "work": a fictional third-party system has nothing
        // real to export or delete, so it unconditionally reports
        // success — proving the contract's happy path is exactly what
        // ADR-0004/FR-019 asks of a reference/stub connector, not a
        // simulation of real per-connector business logic.
        $callbackTimestamp = (string) time();
        $callbackBody = json_encode(['status' => 'success'], JSON_THROW_ON_ERROR);
        $callbackSignature = hash_hmac('sha256', $callbackTimestamp.'.'.$callbackBody, $secret);

        $baseUrl = config('connectors.reference_connector_base_url');
        Http::withHeaders([
            'X-Connector-Signature' => $callbackSignature,
            'X-Connector-Timestamp' => $callbackTimestamp,
        ])->withBody($callbackBody, 'application/json')
            ->post("{$baseUrl}/api/v1/connector-callback/{$taskId}");

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
