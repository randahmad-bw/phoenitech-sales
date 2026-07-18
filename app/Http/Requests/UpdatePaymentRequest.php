<?php

namespace App\Http\Requests;

/**
 * Validates payment update data.
 */
class UpdatePaymentRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'payment_date' => ['nullable', 'date'],
            'method' => ['nullable', 'in:cash,bank_transfer,check,other'],
            'status' => ['nullable', 'in:paid,pending'],
            'notes' => ['nullable', 'string'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0.0001'],
        ];
    }
}
