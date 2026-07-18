<?php

namespace App\Http\Requests;

/**
 * Validates contract renewal data.
 */
class RenewContractRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'contract_value'  => ['required', 'numeric', 'min:0'],
            'start_date'      => ['required', 'date'],
            'end_date'        => ['required', 'date', 'after_or_equal:start_date'],
            'initial_payment' => ['nullable', 'numeric', 'min:0'],
            'notes'           => ['nullable', 'string'],
            'exchange_rate'   => ['nullable', 'numeric', 'min:0.0001'],
        ];
    }
}
