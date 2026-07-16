<?php

namespace App\Http\Requests;

/**
 * Validates company creation data.
 */
class StoreCompanyRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'activity' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
