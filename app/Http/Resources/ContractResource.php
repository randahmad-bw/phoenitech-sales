<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for Contract model serialization.
 * Includes computed financial totals and nested relations.
 */
class ContractResource extends JsonResource
{
    /**
     * Transform contract model into API response array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_contract_id' => $this->parent_contract_id,
            'contract_number' => $this->contract_number,
            'company' => new CompanyResource($this->whenLoaded('company')),
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'service' => new ServiceResource($this->whenLoaded('service')),
            'contract_value' => (float) $this->contract_value,
            'currency' => $this->currency,
            'exchange_rate' => $this->exchange_rate ? (float) $this->exchange_rate : null,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'status' => $this->status,
            'progress_percentage' => $this->progress_percentage,
            'category' => $this->category,
            'category_custom' => $this->category_custom,
            'notes' => $this->notes,
            'total_paid' => $this->total_paid,
            'remaining_amount' => $this->remaining_amount,
            'collection_percentage' => $this->collection_percentage,
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'renewals' => ContractResource::collection($this->whenLoaded('renewals')),
            'renewals_count' => $this->renewals_count,
            'histories' => $this->whenLoaded('histories'),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
