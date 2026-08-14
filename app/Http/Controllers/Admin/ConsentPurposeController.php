<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConsentPurposeRequest;
use App\Http\Resources\ConsentPurposeResource;
use App\Models\ConsentPurpose;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Staff-only (Admin — Purposes and Policies tag). Not one of ADR-0001's
// enumerated ABAC "sensitive actions" (DSAR verification/erasure
// approval, retention execution, audit log access) — gated by a plain
// role check per the roles matrix in 02-requirements.md instead. Full
// ABAC PolicyEvaluator infrastructure remains Session 7 scope.
class ConsentPurposeController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function store(StoreConsentPurposeRequest $request): JsonResponse
    {
        $purpose = ConsentPurpose::create([
            ...$request->validated(),
            'status' => 'active',
            'version' => 1,
        ]);

        $this->auditLogger->record(
            actorType: 'staff',
            actor: $request->user(),
            action: 'consent_purpose.create',
            resourceType: 'consent_purpose',
            resourceId: $purpose->id,
        );

        return (new ConsentPurposeResource($purpose))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, string $purposeId): Response
    {
        abort_unless($request->user()?->isPrivilegedFor('privacy_manager') === true, 403);

        $purpose = ConsentPurpose::query()->findOrFail($purposeId);

        if ($purpose->hasActiveConsentRecords()) {
            return response()->json([
                'type' => 'about:blank',
                'title' => 'Purpose has active consent records',
                'status' => 409,
                'detail' => 'This purpose has active consent records and cannot be deleted; deprecate it instead.',
            ], 409);
        }

        $purpose->delete();

        $this->auditLogger->record(
            actorType: 'staff',
            actor: $request->user(),
            action: 'consent_purpose.delete',
            resourceType: 'consent_purpose',
            resourceId: $purposeId,
        );

        return response()->noContent();
    }
}
