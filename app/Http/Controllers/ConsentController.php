<?php

namespace App\Http\Controllers;

use App\Http\Requests\CaptureConsentRequest;
use App\Http\Resources\ConsentNoticeResource;
use App\Http\Resources\ConsentRecordResource;
use App\Models\ConsentNotice;
use App\Models\ConsentPurpose;
use App\Models\ConsentRecord;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

// Public endpoints (FR-001..004, US-003, US-004) — no staff auth, matching
// the Consent tag in docs/architecture/openapi.yaml. Reachable by an
// embeddable widget on a third-party page, so intentionally stateless.
class ConsentController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function showNotice(string $purposeId): ConsentNoticeResource
    {
        $purpose = ConsentPurpose::query()->with('currentNotice')->findOrFail($purposeId);

        if (! $purpose->currentNotice) {
            abort(404, 'This purpose has no published notice yet.');
        }

        return new ConsentNoticeResource($purpose->currentNotice);
    }

    public function capture(CaptureConsentRequest $request): JsonResponse
    {
        $data = $request->validated();

        // The widget must send the notice version it actually displayed
        // (US-003 AC2) — not necessarily the current one — so any
        // historical version for this purpose is valid, not just the
        // latest.
        $notice = ConsentNotice::query()
            ->where('purpose_id', $data['purpose_id'])
            ->where('version', $data['notice_version'])
            ->first();

        if (! $notice) {
            throw ValidationException::withMessages([
                'notice_version' => 'No notice with this version exists for the given purpose.',
            ]);
        }

        $subjectHash = ConsentRecord::hashIdentifier($data['subject_identifier']);

        $record = ConsentRecord::create([
            'subject_identifier_hash' => $subjectHash,
            'purpose_id' => $data['purpose_id'],
            'notice_id' => $notice->id,
            'status' => 'active',
            'given_at' => now(),
        ]);

        $this->auditLogger->record(
            actorType: 'data_subject',
            actor: null,
            action: 'consent.capture',
            resourceType: 'consent_record',
            resourceId: $record->id,
        );

        return (new ConsentRecordResource($record->load('notice')))
            ->response()
            ->setStatusCode(201);
    }

    public function withdraw(string $consentId): ConsentRecordResource
    {
        $record = ConsentRecord::query()->findOrFail($consentId);

        if ($record->status === 'active') {
            $record->forceFill([
                'status' => 'withdrawn',
                'withdrawn_at' => now(),
            ])->save();

            $this->auditLogger->record(
                actorType: 'data_subject',
                actor: null,
                action: 'consent.withdraw',
                resourceType: 'consent_record',
                resourceId: $record->id,
            );
        }

        return new ConsentRecordResource($record->load('notice'));
    }
}
