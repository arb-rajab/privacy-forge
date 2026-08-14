<?php

namespace App\Http\Resources;

use App\Models\ConsentRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Field names/shape match components.schemas.ConsentRecord in
// docs/architecture/openapi.yaml exactly — notice_version (an integer),
// not notice_id, is what the contract exposes.
/**
 * @mixin ConsentRecord
 */
class ConsentRecordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $notice = $this->notice;

        if ($notice === null) {
            throw new \LogicException('ConsentRecordResource requires the notice relation to be loaded.');
        }

        return [
            'id' => $this->id,
            'purpose_id' => $this->purpose_id,
            'notice_version' => $notice->version,
            'status' => $this->status,
            'given_at' => $this->given_at->toJSON(),
            'withdrawn_at' => $this->withdrawn_at?->toJSON(),
        ];
    }
}
