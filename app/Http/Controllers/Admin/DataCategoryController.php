<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDataCategoryRequest;
use App\Http\Resources\DataCategoryResource;
use App\Models\DataCategory;
use App\Models\User;
use App\Services\PolicyEvaluator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

// US-010 (part 1 of the retention slice, ADR-0002). retention.policy.manage
// is the fourth registered sensitive action (ADR-0001 registry, following
// ADR-0006's policy.update precedent for adding a new one) — data
// categories and the retention policies governing them are staff-facing
// configuration, but the session brief explicitly scopes this to
// Owner/Privacy Manager via a real ABAC gate rather than the plain role
// check ConsentPurposeController uses, since retention data-category/
// policy CRUD is what ultimately controls what gets erased.
class DataCategoryController extends Controller
{
    // Same nil-UUID sentinel pattern as
    // Admin\PolicyController::COLLECTION_RESOURCE_ID — index/store both
    // act on the collection as a whole (or a not-yet-existing row), not a
    // single existing DataCategory id.
    private const COLLECTION_RESOURCE_ID = '00000000-0000-0000-0000-000000000000';

    public function __construct(private readonly PolicyEvaluator $policyEvaluator) {}

    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(401);
        }

        $decision = $this->policyEvaluator->evaluate(
            action: 'retention.policy.manage',
            actor: $actor,
            resourceType: 'data_category',
            resourceId: self::COLLECTION_RESOURCE_ID,
        );

        if (! $decision->allowed) {
            return $this->denied($decision->policyId);
        }

        return DataCategoryResource::collection(DataCategory::query()->orderBy('name')->get());
    }

    public function store(StoreDataCategoryRequest $request): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(401);
        }

        $decision = $this->policyEvaluator->evaluate(
            action: 'retention.policy.manage',
            actor: $actor,
            resourceType: 'data_category',
            resourceId: self::COLLECTION_RESOURCE_ID,
        );

        if (! $decision->allowed) {
            return $this->denied($decision->policyId);
        }

        $category = DataCategory::create($request->validated());

        return (new DataCategoryResource($category))->response()->setStatusCode(201);
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
