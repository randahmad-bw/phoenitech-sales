<?php

namespace App\Http\Requests;

/**
 * Validates employee creation data.
 */
class StoreEmployeeRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'department' => ['nullable', 'string', 'max:50'],
            'employment_date' => ['nullable', 'date'],
        ];
    }
}
