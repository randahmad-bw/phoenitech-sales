<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for Employee model serialization.
 */
class EmployeeResource extends JsonResource
{
    /**
     * Transform employee model into API response array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'department' => $this->department,
            'employment_date' => $this->employment_date?->format('Y-m-d'),
            'companies_count' => $this->whenCounted('companies'),
            'contracts_count' => $this->whenCounted('contracts'),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
