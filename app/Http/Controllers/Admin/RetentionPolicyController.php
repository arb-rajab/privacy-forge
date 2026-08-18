<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRetentionPolicyRequest;
use App\Http\Requests\UpdateRetentionPolicyRequest;
use App\Http\Resources\RetentionExecutionResource;
use App\Http\Resources\RetentionPolicyResource;
use App\Models\RetentionExecution;
use App\Models\RetentionPolicy;
use App\Models\User;
use App\Services\PolicyEvaluator;
use App\Services\RetentionExecutor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

// US-010/US-011 (ADR-0002). Every action here — including the dry-run
// preview — shares the single retention.policy.manage gate: US-011 is
// explicitly a Privacy Manager action ("As a Privacy Manager, I want to
// preview..."), so it belongs under the same staff-facing sensitive
// action as defining the policy in the first place, the same way
// Admin\PolicyController shares one gate across index/show/update rather
// than splitting a role-checked "view" from an ABAC-gated "edit."
//
// Scheduled real execution (US-012) is deliberately NOT gated here or
// anywhere else by PolicyEvaluator — see App\Console\Commands\
// ExecuteRetentionPoliciesCommand and docs/project-memory/09-decision-log.md
// for why that is a documented decision, not an oversight.
class RetentionPolicyController extends Controller
{
    private const COLLECTION_RESOURCE_ID = '00000000-0000-0000-0000-000000000000';

    public function __construct(
        private readonly PolicyEvaluator $policyEvaluator,
        private readonly RetentionExecutor $executor,
    ) {}

    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(401);
        }

        $decision = $this->policyEvaluator->evaluate(
            action: 'retention.policy.manage',
            actor: $actor,
            resourceType: 'retention_policy',
            resourceId: self::COLLECTION_RESOURCE_ID,
        );

        if (! $decision->allowed) {
            return $this->denied($decision->policyId);
        }

        return RetentionPolicyResource::collection(
            RetentionPolicy::query()->orderBy('data_category_id')->orderByDesc('version')->get()
        );
    }

    public function show(Request $request, string $policyId): RetentionPolicyResource|JsonResponse
    {
        $policy = RetentionPolicy::query()->findOrFail($policyId);

        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(401);
        }

        $decision = $this->policyEvaluator->evaluate(
            action: 'retention.policy.manage',
            actor: $actor,
            resourceType: 'retention_policy',
            resourceId: $policy->id,
        );

        if (! $decision->allowed) {
            return $this->denied($decision->policyId);
        }

        return new RetentionPolicyResource($policy);
    }

    public function store(StoreRetentionPolicyRequest $request): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(401);
        }

        $decision = $this->policyEvaluator->evaluate(
            action: 'retention.policy.manage',
            actor: $actor,
            resourceType: 'retention_policy',
            resourceId: self::COLLECTION_RESOURCE_ID,
        );

        if (! $decision->allowed) {
            return $this->denied($decision->policyId);
        }

        $policy = RetentionPolicy::create([
            ...$request->validated(),
            'status' => 'active',
            'version' => 1,
        ]);

        return (new RetentionPolicyResource($policy))->response()->setStatusCode(201);
    }

    // Versioned the same way PolicyController::update supersedes and
    // creates the next version rather than mutating in place —
    // data_category_id is carried over unchanged (see
    // UpdateRetentionPolicyRequest's comment on why it's not accepted here).
    public function update(UpdateRetentionPolicyRequest $request, string $policyId): JsonResponse
    {
        $policy = RetentionPolicy::query()->findOrFail($policyId);

        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(401);
        }

        $decision = $this->policyEvaluator->evaluate(
            action: 'retention.policy.manage',
            actor: $actor,
            resourceType: 'retention_policy',
            resourceId: $policy->id,
        );

        if (! $decision->allowed) {
            return $this->denied($decision->policyId);
        }

        $data = $request->validated();

        $newVersion = DB::transaction(function () use ($policy, $data) {
            $policy->forceFill(['status' => 'deprecated'])->save();

            return RetentionPolicy::create([
                'data_category_id' => $policy->data_category_id,
                'retention_period_days' => $data['retention_period_days'],
                'post_expiry_action' => $data['post_expiry_action'],
                'version' => $policy->version + 1,
                'status' => 'active',
            ]);
        });

        return (new RetentionPolicyResource($newVersion))->response()->setStatusCode(200);
    }

    // US-011/FR-012: no side effects on the underlying consent_records/
    // dsar_requests rows — RetentionExecutor::preview() only ever reads
    // via RetentionSelector and records a RetentionExecution(mode=dry_run)
    // row (ADR-0002: a dry run is not "free" either).
    public function dryRun(Request $request, string $policyId): JsonResponse
    {
        $policy = RetentionPolicy::query()->findOrFail($policyId);

        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(401);
        }

        $decision = $this->policyEvaluator->evaluate(
            action: 'retention.policy.manage',
            actor: $actor,
            resourceType: 'retention_policy',
            resourceId: $policy->id,
        );

        if (! $decision->allowed) {
            return $this->denied($decision->policyId);
        }

        $result = $this->executor->preview($policy);

        // Shape matches components.schemas.RetentionPreview exactly
        // (docs/architecture/openapi.yaml).
        return response()->json([
            'policy_id' => $policy->id,
            'affected_record_count' => $result['execution']->affected_record_count,
            'sample_record_ids' => $result['sampleIds'],
        ]);
    }

    // B-05: past dry-run and real executions of one policy, each with its
    // linked DeletionCertificate when one exists (real executions only —
    // RetentionExecutor::preview() never creates one). Shares the same
    // retention.policy.manage gate as every other action on this
    // controller, including the dry-run preview above — this is a read
    // endpoint on the same resource, not a new sensitive action, matching
    // the "index/show share the gate" reasoning this controller's class
    // comment already establishes for index()/show()/dryRun().
    public function executions(Request $request, string $policyId): AnonymousResourceCollection|JsonResponse
    {
        $policy = RetentionPolicy::query()->findOrFail($policyId);

        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(401);
        }

        $decision = $this->policyEvaluator->evaluate(
            action: 'retention.policy.manage',
            actor: $actor,
            resourceType: 'retention_policy',
            resourceId: $policy->id,
        );

        if (! $decision->allowed) {
            return $this->denied($decision->policyId);
        }

        $executions = RetentionExecution::query()
            ->where('retention_policy_id', $policy->id)
            ->with('deletionCertificate')
            ->orderByDesc('executed_at')
            ->get();

        return RetentionExecutionResource::collection($executions);
    }

    private function denied(?string $policyId): JsonResponse
    {
        return response()->json([
            'type' => 'about:blank',
            'title' => 'Denied by ABAC policy evaluation',
            'status' => 403,
            'detail' => 'The retention.policy.manage policy denied this request.',
            'policy_id' => $policyId,
        ], 403);
    }
}
