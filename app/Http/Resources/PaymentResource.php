<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for Payment model serialization.
 */
class PaymentResource extends JsonResource
{
    /**
     * Transform payment model into API response array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contract_id' => $this->contract_id,
            'amount' => (float) $this->amount,
            'exchange_rate' => $this->exchange_rate ? (float) $this->exchange_rate : null,
            'payment_date' => $this->payment_date?->format('Y-m-d'),
            'method' => $this->method,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
