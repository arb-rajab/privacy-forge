<?php

namespace App\Http\Resources;

use App\Models\RetentionPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Field names/shape match components.schemas.RetentionPolicy in
// docs/architecture/openapi.yaml.
/**
 * @mixin RetentionPolicy
 */
class RetentionPolicyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'data_category_id' => $this->data_category_id,
            'retention_period_days' => $this->retention_period_days,
            'post_expiry_action' => $this->post_expiry_action,
            'status' => $this->status,
            'version' => $this->version,
        ];
    }
}
