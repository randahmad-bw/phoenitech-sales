<?php

namespace App\Http\Requests;

/**
 * Validates contract update data.
 * Accepts company_name (text) instead of company_id.
 */
class UpdateContractRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'company_name'   => ['sometimes', 'string', 'min:2', 'max:255'],
            'employee_id'    => ['nullable', 'integer', 'exists:employees,id'],
            'contract_value' => ['sometimes', 'numeric', 'min:0'],
            'currency'       => ['nullable', 'string', 'size:3'],
            'start_date'     => ['nullable', 'date'],
            'end_date'       => ['nullable', 'date'],
            'status'         => ['nullable', 'in:draft,signed,active,completed,cancelled,suspended,renewed'],
            'notes'          => ['nullable', 'string'],
        ];
    }
}
