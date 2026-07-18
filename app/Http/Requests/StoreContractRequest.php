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
            'company_name'        => ['required_without:company_id', 'string', 'min:2', 'max:255'],
            'company_id'          => ['required_without:company_name', 'integer', 'exists:companies,id'],
            'employee_id'         => ['nullable', 'integer', 'exists:employees,id'],
            'service_id'          => ['nullable', 'integer', 'exists:services,id'],
            'contract_value'      => ['required', 'numeric', 'min:0'],
            'currency'            => ['nullable', 'string', 'size:3'],
            'start_date'          => ['nullable', 'date'],
            'end_date'            => ['nullable', 'date', 'after_or_equal:start_date'],
            'status'              => ['nullable', 'in:draft,signed,active,completed,cancelled,suspended,renewed'],
            'progress_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'notes'               => ['nullable', 'string'],
            'initial_payment'     => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
