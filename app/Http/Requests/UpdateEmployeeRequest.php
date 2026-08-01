<?php

namespace App\Http\Requests;

/**
 * Validates employee update data. All fields optional.
 */
class UpdateEmployeeRequest extends BaseFormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'department' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'base_salary' => ['nullable', 'numeric', 'min:0'],
            'annual_leave_allowance' => ['nullable', 'integer', 'min:0', 'max:365'],
            'job_description' => ['nullable', 'string'],
            'responsibilities' => ['nullable', 'array'],
            'employment_date' => ['nullable', 'date'],
        ];
    }
}
