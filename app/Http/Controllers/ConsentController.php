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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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

    private function rateLimitResponse(): JsonResponse
    {
        return response()->json([
            'type' => 'about:blank',
            'title' => 'Too Many Requests',
            'status' => 429,
            'detail' => 'Too many requests from this address. Try again shortly.',
        ], 429);
    }

    public function capture(CaptureConsentRequest $request): JsonResponse
    {
        // T-03 (06-security-threat-model.md): IP-level rate limit — a
        // volumetric-abuse control, distinct from the per-subject DSAR
        // limit in NFR-006 (a subject may legitimately consent to many
        // purposes; an IP flooding this endpoint is the actual threat).
        $rateLimitKey = 'consent-capture:'.$request->ip();
        $maxPerMinute = (int) config('consent.capture_rate_limit_per_minute');

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxPerMinute)) {
            return $this->rateLimitResponse();
        }

        RateLimiter::hit($rateLimitKey, 60);

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

    public function withdraw(Request $request, string $consentId): ConsentRecordResource|JsonResponse
    {
        $rateLimitKey = 'consent-withdraw:'.$request->ip();
        $maxPerMinute = (int) config('consent.withdraw_rate_limit_per_minute');

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxPerMinute)) {
            return $this->rateLimitResponse();
        }

        RateLimiter::hit($rateLimitKey, 60);

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
