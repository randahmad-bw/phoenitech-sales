<?php

namespace App\Http\Requests;

/**
 * Validates company update data. All fields optional.
 */
class UpdateCompanyRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'activity' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
