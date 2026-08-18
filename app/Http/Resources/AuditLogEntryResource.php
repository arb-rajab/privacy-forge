<?php

namespace App\Http\Resources;

use App\Models\AuditLogEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Field names/shape match components.schemas.AuditLogEntry in
// docs/architecture/openapi.yaml.
/**
 * @mixin AuditLogEntry
 */
class AuditLogEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'actor_type' => $this->actor_type,
            'action' => $this->action,
            'resource_type' => $this->resource_type,
            'resource_id' => $this->resource_id,
            'policy_id' => $this->policy_id,
            'decision' => $this->decision,
            'reason_code' => $this->reason_code,
            'entry_hash' => $this->entry_hash,
            'created_at' => $this->created_at,
        ];
    }
}
