<?php

namespace App\Http\Requests;

/**
 * Validates payment creation data.
 */
class StorePaymentRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['nullable', 'date'],
            'method' => ['nullable', 'in:cash,bank_transfer,check,other'],
            'status' => ['nullable', 'in:paid,pending'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
