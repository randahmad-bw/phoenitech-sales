<?php

namespace App\Http\Requests;

use App\Models\EmployeeLeave;
use Illuminate\Validation\Rule;

/**
 * Validates a correction to a leave record made by management.
 */
class UpdateEmployeeLeaveRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'leave_type' => ['sometimes', Rule::exists('leave_types', 'key')->where('is_active', true)],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
            'days_count' => ['nullable', 'numeric', 'min:0.5'],
            'status' => ['sometimes', Rule::in(EmployeeLeave::STATUSES)],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'leave_type.exists' => 'That leave type does not exist, or is no longer in use.',
        ];
    }
}
