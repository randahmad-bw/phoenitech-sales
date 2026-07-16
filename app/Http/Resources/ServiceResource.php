<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for Service model serialization.
 */
class ServiceResource extends JsonResource
{
    /**
     * Transform service model into API response array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'is_active' => $this->is_active,
            'contracts_count' => $this->whenCounted('contracts'),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
