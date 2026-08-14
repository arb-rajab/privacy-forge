<?php

namespace App\Http\Resources;

use App\Models\DsarRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Field names/shape match components.schemas.DsarStatus in
// docs/architecture/openapi.yaml exactly. Deliberately excludes
// subject_identifier and status_token — this is returned to a caller who
// has proven ownership via a signed link or staff session, but the raw
// identity claim and the internal status token still have no reason to
// leave the server.
/**
 * @mixin DsarRequest
 */
class DsarStatusResource extends JsonResource
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
        ];
    }
}
