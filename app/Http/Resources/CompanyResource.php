<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for Company model serialization.
 */
class CompanyResource extends JsonResource
{
    /**
     * Transform company model into API response array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'client_name' => $this->client_name,
            'phone' => $this->phone,
            'activity' => $this->activity,
            'address' => $this->address,
            'notes' => $this->notes,
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'contacts' => ContactResource::collection($this->whenLoaded('contacts')),
            'contacts_count' => $this->whenCounted('contacts'),
            'contracts_count' => $this->whenCounted('contracts'),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
