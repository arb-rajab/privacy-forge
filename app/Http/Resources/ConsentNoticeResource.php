<?php

namespace App\Http\Resources;

use App\Models\ConsentNotice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ConsentNotice
 */
class ConsentNoticeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'purpose_id' => $this->purpose_id,
            'version' => $this->version,
            'body' => $this->body,
            'published_at' => $this->published_at->toJSON(),
        ];
    }
}
