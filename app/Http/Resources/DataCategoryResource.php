<?php

namespace App\Http\Resources;

use App\Models\DataCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Field names/shape match components.schemas.DataCategory in
// docs/architecture/openapi.yaml.
/**
 * @mixin DataCategory
 */
class DataCategoryResource extends JsonResource
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
            'sensitivity' => $this->sensitivity,
            'subject_table' => $this->subject_table,
        ];
    }
}
