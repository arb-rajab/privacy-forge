<?php

namespace App\Http\Resources;

use App\Models\PolicyDefinition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Field names/shape match components.schemas.PolicyDefinition in
// docs/architecture/openapi.yaml. Reachable only through the policy.update
// ABAC gate (App\Http\Controllers\Admin\PolicyController) — this is the
// raw shape of an ABAC policy row, so it is not exposed to anyone the
// evaluator hasn't already allowed.
/**
 * @mixin PolicyDefinition
 */
class PolicyDefinitionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action_name' => $this->action_name,
            'version' => $this->version,
            'subject_conditions' => $this->subject_conditions,
            'resource_conditions' => $this->resource_conditions,
            'environment_conditions' => $this->environment_conditions,
            'effect' => $this->effect,
            'status' => $this->status,
        ];
    }
}
