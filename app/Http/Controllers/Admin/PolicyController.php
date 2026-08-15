<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePolicyDefinitionRequest;
use App\Http\Resources\PolicyDefinitionResource;
use App\Models\PolicyDefinition;
use App\Models\User;
use App\Services\PolicyEvaluator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

// ADR-0006 — policy.update, the sensitive action ADR-0006 named
// ("restricted to the Owner role and audit-logged like every other
// sensitive action") but which R-03 (docs/project-memory/10-risk-register.md)
// found had no controller, route, or PolicyDefinition row in use. Viewing
// and editing ABAC policy definitions both go through this one gate —
// ADR-0006 names exactly one action for this surface, so index/show are
// not split off into a separate, ungated "view" per ADR-0001's own
// rationale for why role checks alone aren't enough for a sensitive
// action (no policy_id to log).
class PolicyController extends Controller
{
    // audit_log_entries.resource_id is a uuid column (ADR-0003) — every
    // other sensitive action logs a real row's id, but index() acts on
    // the whole collection rather than one row, so this fixed nil uuid
    // stands in as "no single resource", not a real PolicyDefinition id.
    private const COLLECTION_RESOURCE_ID = '00000000-0000-0000-0000-000000000000';

    public function __construct(private readonly PolicyEvaluator $policyEvaluator) {}

    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(401);
        }

        $decision = $this->policyEvaluator->evaluate(
            action: 'policy.update',
            actor: $actor,
            resourceType: 'policy_definition',
            resourceId: self::COLLECTION_RESOURCE_ID,
        );

        if (! $decision->allowed) {
            return response()->json([
                'type' => 'about:blank',
                'title' => 'Denied by ABAC policy evaluation',
                'status' => 403,
                'detail' => 'The policy.update policy denied this request.',
                'policy_id' => $decision->policyId,
            ], 403);
        }

        return PolicyDefinitionResource::collection(
            PolicyDefinition::query()->orderBy('action_name')->orderByDesc('version')->get()
        );
    }

    public function show(Request $request, string $policyId): PolicyDefinitionResource|JsonResponse
    {
        $policy = PolicyDefinition::query()->findOrFail($policyId);

        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(401);
        }

        $decision = $this->policyEvaluator->evaluate(
            action: 'policy.update',
            actor: $actor,
            resourceType: 'policy_definition',
            resourceId: $policy->id,
            resourceAttributes: ['action_name' => $policy->action_name],
        );

        if (! $decision->allowed) {
            return response()->json([
                'type' => 'about:blank',
                'title' => 'Denied by ABAC policy evaluation',
                'status' => 403,
                'detail' => 'The policy.update policy denied this request.',
                'policy_id' => $decision->policyId,
            ], 403);
        }

        return new PolicyDefinitionResource($policy);
    }

    // Policies are versioned rows (04-data-model.md), never mutated in
    // place — the same pattern ConsentNotice already uses. Updating
    // supersedes the current row and creates the next version.
    public function update(UpdatePolicyDefinitionRequest $request, string $policyId): JsonResponse
    {
        $policy = PolicyDefinition::query()->findOrFail($policyId);

        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(401);
        }

        $decision = $this->policyEvaluator->evaluate(
            action: 'policy.update',
            actor: $actor,
            resourceType: 'policy_definition',
            resourceId: $policy->id,
            resourceAttributes: ['action_name' => $policy->action_name],
        );

        if (! $decision->allowed) {
            return response()->json([
                'type' => 'about:blank',
                'title' => 'Denied by ABAC policy evaluation',
                'status' => 403,
                'detail' => 'The policy.update policy denied this request.',
                'policy_id' => $decision->policyId,
            ], 403);
        }

        $data = $request->validated();

        $newVersion = DB::transaction(function () use ($policy, $data) {
            $policy->forceFill(['status' => 'superseded'])->save();

            return PolicyDefinition::create([
                'action_name' => $policy->action_name,
                'version' => $policy->version + 1,
                'subject_conditions' => $data['subject_conditions'] ?? [],
                'resource_conditions' => $data['resource_conditions'] ?? [],
                'environment_conditions' => $data['environment_conditions'] ?? [],
                'effect' => $data['effect'],
                'status' => 'active',
            ]);
        });

        // Explicit 200, not JsonResource's automatic 201-on-recently-
        // created: from the caller's perspective this is an update to an
        // existing policy, not the creation of a new one, even though a
        // new version row is what represents that update internally.
        return (new PolicyDefinitionResource($newVersion))->response()->setStatusCode(200);
    }
}
