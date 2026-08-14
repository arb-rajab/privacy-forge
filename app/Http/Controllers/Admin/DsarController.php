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
}
