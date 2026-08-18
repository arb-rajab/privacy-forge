<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogEntryResource;
use App\Models\AuditLogEntry;
use App\Models\User;
use App\Services\PolicyEvaluator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

// US-014/US-015/FR-013/FR-014 (NFR-009). GET /admin/audit-log has been
// documented in docs/architecture/openapi.yaml (the "Admin — RoPA and
// Audit" tag) since Session 3 but had no implementation at all until now
// — see docs/project-memory/11-backlog.md B-04 and
// docs/project-memory/12-session-handoff.md (Session 21) for how this was
// found (scoping the audit-log UI stretch item, not a regression).
//
// audit.log.view is the sixth registered sensitive action (ADR-0001's
// registry, following ropa.export's Session 12 precedent for adding a
// new one). Gating decision, made explicitly here rather than left
// implicit: 02-requirements.md's roles matrix already names this
// capability twice, with two *different* scopes —
//   - Owner: "view full audit log"
//   - Privacy Manager: "view audit log entries related to their actions"
//   - Support Staff: explicitly cannot "view the audit log" at all
// PolicyEvaluator's ABAC conditions decide *whether* a request is allowed
// at all (Owner or Privacy Manager; Support Staff denied, matching every
// other sensitive action's shape), but they do not filter *which rows* a
// list endpoint returns — no existing sensitive action needed that until
// now, since every other list endpoint here (retention policies, ABAC
// policies, RoPA) returns the same rows to every allowed role. The
// row-level scoping the matrix asks for ("full" vs. "related to their
// actions") is therefore applied in this controller, after the ABAC gate
// allows the request: an Owner's query is unfiltered; a Privacy Manager's
// query is additionally scoped to actor_user_id = their own id. This is a
// controller-level query decision, not a new PolicyEvaluator condition
// type — the existing "in"/"equals"/"not_equals_attribute" operators
// evaluate a single access decision, not a per-row list filter, and
// stretching them to do row-level filtering would be a real ABAC engine
// change (out of this session's scope per its own ground rules, which bar
// reopening ADR-0001).
class AuditLogController extends Controller
{
    // Same nil-UUID "no single resource" sentinel every other collection
    // endpoint in this codebase uses (Admin\PolicyController,
    // Admin\DataCategoryController, Admin\RopaController).
    private const COLLECTION_RESOURCE_ID = '00000000-0000-0000-0000-000000000000';

    public function __construct(private readonly PolicyEvaluator $policyEvaluator) {}

    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(401);
        }

        $decision = $this->policyEvaluator->evaluate(
            action: 'audit.log.view',
            actor: $actor,
            resourceType: 'audit_log_entry',
            resourceId: self::COLLECTION_RESOURCE_ID,
        );

        if (! $decision->allowed) {
            return $this->denied($decision->policyId);
        }

        $request->validate([
            'since' => ['nullable', 'date'],
            'until' => ['nullable', 'date'],
        ]);

        $query = AuditLogEntry::query()->orderBy('sequence');

        // Row-level scoping per the roles matrix — see the class comment
        // above for why this lives here rather than in a policy condition.
        // Owner is the only role with no narrowing applied ("full audit
        // log"); every other role reaching this point is a Privacy
        // Manager (Support Staff was already denied by the gate above),
        // scoped to entries their own actions produced.
        if (! $actor->isPrivilegedFor('owner')) {
            $query->where('actor_user_id', $actor->id);
        }

        if ($request->filled('resourceType')) {
            $query->where('resource_type', $request->query('resourceType'));
        }

        if ($request->filled('resourceId')) {
            $query->where('resource_id', $request->query('resourceId'));
        }

        if ($request->filled('since')) {
            $query->where('created_at', '>=', $request->query('since'));
        }

        if ($request->filled('until')) {
            $query->where('created_at', '<=', $request->query('until'));
        }

        return AuditLogEntryResource::collection($query->get());
    }

    private function denied(?string $policyId): JsonResponse
    {
        return response()->json([
            'type' => 'about:blank',
            'title' => 'Denied by ABAC policy evaluation',
            'status' => 403,
            'detail' => 'The audit.log.view policy denied this request.',
            'policy_id' => $policyId,
        ], 403);
    }
}
