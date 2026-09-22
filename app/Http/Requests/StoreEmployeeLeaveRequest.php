<?php

namespace App\Http\Requests;

use App\Models\EmployeeLeave;
use Illuminate\Validation\Rule;

/**
 * Validates leave recorded **for** an employee by management.
 *
 * Unlike StoreLeaveRequestRequest this one does accept a `status` and a
 * `days_count`, and that is the whole reason its route sits behind
 * `leave.approve` rather than `leave.create`: it is the tool for entering
 * leave that was agreed outside the system, not a way to ask for any.
 */
class StoreEmployeeLeaveRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            // Active rows only. A type the company has switched off is not
            // available for a new record, however it is being entered.
            'leave_type' => ['required', Rule::exists('leave_types', 'key')->where('is_active', true)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'days_count' => ['nullable', 'numeric', 'min:0.5'],
            'status' => ['nullable', Rule::in(EmployeeLeave::STATUSES)],
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
