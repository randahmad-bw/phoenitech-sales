<?php

namespace App\Http\Requests;

/**
 * Validates contract creation data.
 * Accepts company_name (text) instead of company_id.
 */
class StoreContractRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'company_name'    => ['required', 'string', 'min:2', 'max:255'],
            'employee_id'     => ['nullable', 'integer', 'exists:employees,id'],
            'contract_value'  => ['required', 'numeric', 'min:0'],
            'currency'        => ['nullable', 'string', 'size:3'],
            'start_date'      => ['nullable', 'date'],
            'end_date'        => ['nullable', 'date', 'after_or_equal:start_date'],
            'status'          => ['nullable', 'in:draft,signed,active,completed,cancelled,suspended,renewed'],
            'notes'           => ['nullable', 'string'],
            'initial_payment' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
