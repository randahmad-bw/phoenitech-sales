<?php

namespace App\Http\Requests;

/**
 * Validates employee creation data.
 */
class StoreEmployeeRequest extends BaseFormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('employment_date') && $this->input('employment_date') === '') {
            $this->merge(['employment_date' => null]);
        }
        if ($this->has('email') && $this->input('email') === '') {
            $this->merge(['email' => null]);
        }
        if ($this->has('phone') && $this->input('phone') === '') {
            $this->merge(['phone' => null]);
        }
    }

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
