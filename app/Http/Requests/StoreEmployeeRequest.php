<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Validation\Rule;

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
            // Constrained to Employee::DEPARTMENTS: an unlisted value used
            // to be accepted silently and then match no filter anywhere.
            'department' => ['nullable', 'string', Rule::in(Employee::DEPARTMENTS)],
            'job_title' => ['nullable', 'string', 'max:255'],
            'base_salary' => ['nullable', 'numeric', 'min:0'],
            'annual_leave_allowance' => ['nullable', 'integer', 'min:0', 'max:365'],
            'job_description' => ['nullable', 'string'],
            'responsibilities' => ['nullable', 'array'],
            'employment_date' => ['nullable', 'date'],
        ];
    }
}
