<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublishConsentNoticeRequest;
use App\Http\Resources\ConsentNoticeResource;
use App\Models\ConsentNotice;
use App\Models\ConsentPurpose;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ConsentNoticeController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function store(PublishConsentNoticeRequest $request, string $purposeId): JsonResponse
    {
        $purpose = ConsentPurpose::query()->findOrFail($purposeId);

        $notice = DB::transaction(function () use ($purpose, $request) {
            $nextVersion = ((int) $purpose->notices()->max('version')) + 1;

            $notice = ConsentNotice::create([
                'purpose_id' => $purpose->id,
                'version' => $nextVersion,
                'body' => $request->validated()['body'],
                'published_at' => now(),
                'created_at' => now(),
            ]);

            // Existing consent records keep referencing the notice_id
            // they were actually shown (US-002 AC2) — only the purpose's
            // pointer to the *current* notice moves forward.
            $purpose->forceFill(['current_notice_id' => $notice->id])->save();

            return $notice;
        });

        $this->auditLogger->record(
            actorType: 'staff',
            actor: $request->user(),
            action: 'consent_notice.publish',
            resourceType: 'consent_notice',
            resourceId: $notice->id,
        );

        return (new ConsentNoticeResource($notice))->response()->setStatusCode(201);
    }
}
