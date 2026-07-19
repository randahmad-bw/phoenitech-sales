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
            'company_name'        => ['sometimes', 'string', 'min:2', 'max:255'],
            'company_id'          => ['nullable', 'integer', 'exists:companies,id'],
            'employee_id'         => ['nullable', 'integer', 'exists:employees,id'],
            'service_id'          => ['nullable', 'integer', 'exists:services,id'],
            'contract_value'      => ['sometimes', 'numeric', 'min:0'],
            'currency'            => ['nullable', 'string', 'size:3'],
            'start_date'          => ['nullable', 'date'],
            'end_date'            => ['nullable', 'date'],
            'status'              => ['nullable', 'in:draft,signed,active,completed,cancelled,suspended,renewed'],
            'progress_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'notes'               => ['nullable', 'string'],
            'category'            => ['nullable', 'string', 'in:social,menu,visual_identity,accounting_software,hardware,custom_dev,other'],
            'category_custom'     => ['nullable', 'string', 'max:255'],
            'exchange_rate'       => ['nullable', 'numeric', 'min:0.0001'],
        ];
    }
}
