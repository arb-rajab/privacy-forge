<?php

namespace App\Http\Resources;

use App\Models\ConsentPurpose;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ConsentPurpose
 */
class ConsentPurposeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'lawful_basis' => $this->lawful_basis,
            'status' => $this->status,
            'version' => $this->version,
        ];
    }
}
