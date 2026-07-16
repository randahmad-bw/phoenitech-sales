<?php

namespace App\Http\Requests;

/**
 * Validates employee update data. All fields optional.
 */
class UpdateEmployeeRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'employment_date' => ['nullable', 'date'],
        ];
    }
}
