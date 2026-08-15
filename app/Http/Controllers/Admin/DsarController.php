<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\DsarStatusResource;
use App\Models\DsarRequest;
use App\Models\User;
use App\Services\PolicyEvaluator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Staff-only (Admin — Purposes and Policies tag). US-006/FR-007 — the
// first real ADR-0001 sensitive action: gated by PolicyEvaluator, not a
// role check, per ADR-0001's own rationale for why role gates alone
// aren't enough for a sensitive action (no policy_id to log).
class DsarController extends Controller
{
    public function __construct(private readonly PolicyEvaluator $policyEvaluator) {}

    public function verifyIdentity(Request $request, string $dsarId): DsarStatusResource|JsonResponse
    {
        $dsar = DsarRequest::query()->findOrFail($dsarId);

        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(401);
        }

        $decision = $this->policyEvaluator->evaluate(
            action: 'dsar.identity.verify',
            actor: $actor,
            resourceType: 'dsar_request',
            resourceId: $dsar->id,
        );

        if (! $decision->allowed) {
            return response()->json([
                'type' => 'about:blank',
                'title' => 'Denied by ABAC policy evaluation',
                'status' => 403,
                'detail' => 'The dsar.identity.verify policy denied this request.',
                'policy_id' => $decision->policyId,
            ], 403);
        }

        if ($dsar->status === 'pending_verification') {
            $dsar->forceFill([
                'status' => 'in_progress',
                'identity_verified_by' => $actor->id,
                'identity_verified_at' => now(),
            ])->save();
        }

        return new DsarStatusResource($dsar);
    }

    // US-006's remaining half. Gated by the dsar.erasure.approve policy
    // (ADR-0001), whose resource_conditions require status=in_progress
    // (FR-007/US-006 AC2 — no verification, no approval) and whose
    // subject_conditions require the approver's id to differ from the
    // DSAR's identity_verified_by (separation-of-duties, ADR-0007). Both
    // gates are policy data, not controller code — see ADR-0007.
    public function approveErasure(Request $request, string $dsarId): DsarStatusResource|JsonResponse
    {
        $dsar = DsarRequest::query()->findOrFail($dsarId);

        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(401);
        }

        $decision = $this->policyEvaluator->evaluate(
            action: 'dsar.erasure.approve',
            actor: $actor,
            resourceType: 'dsar_request',
            resourceId: $dsar->id,
            resourceAttributes: [
                'status' => $dsar->status,
                'request_type' => $dsar->request_type,
                'identity_verified_by' => $dsar->identity_verified_by,
            ],
        );

        if (! $decision->allowed) {
            return response()->json([
                'type' => 'about:blank',
                'title' => 'Denied by ABAC policy evaluation',
                'status' => 403,
                'detail' => 'The dsar.erasure.approve policy denied this request.',
                'policy_id' => $decision->policyId,
            ], 403);
        }

        // Connector task dispatch (ADR-0004) is out of scope this session
        // (see docs/project-memory/12-session-handoff.md) — recording the
        // approval is the hook a future session dispatches from
        // (erasure_approved_at IS NOT NULL). Status stays in_progress:
        // there is no "dispatching" status value yet, and in_progress
        // already means "work ongoing, not yet complete."
        if ($dsar->erasure_approved_by === null) {
            $dsar->forceFill([
                'erasure_approved_by' => $actor->id,
                'erasure_approved_at' => now(),
            ])->save();
        }

        return new DsarStatusResource($dsar);
    }
}
