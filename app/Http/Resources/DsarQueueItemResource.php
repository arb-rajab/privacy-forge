<?php

namespace App\Http\Resources;

use App\Models\DsarConnectorTask;
use App\Models\DsarRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Staff-facing DSAR queue view (Session 10, closing the gap flagged after
// Session 8: per-connector task status was previously visible only via
// direct DB access). Unlike DsarStatusResource (the data-subject-facing
// shape, which deliberately hides subject_identifier/status_token), this
// is only ever returned to an authenticated staff session, so it also
// exposes who verified/approved and when — useful triage context that
// has no reason to reach a data subject.
/**
 * @mixin DsarRequest
 */
class DsarQueueItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_type' => $this->request_type,
            'status' => $this->status,
            'identity_verified_by' => $this->identity_verified_by,
            'identity_verified_at' => $this->identity_verified_at?->toIso8601String(),
            'erasure_approved_by' => $this->erasure_approved_by,
            'erasure_approved_at' => $this->erasure_approved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'tasks' => $this->connectorTasks->map(fn (DsarConnectorTask $task): array => [
                'connector_id' => $task->connector_id,
                'connector_name' => $task->connector?->name,
                'task_type' => $task->task_type,
                'status' => $task->status,
                'attempt_count' => $task->attempt_count,
                'failure_reason' => $task->failure_reason,
                'dispatched_at' => $task->dispatched_at?->toIso8601String(),
                'completed_at' => $task->completed_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
