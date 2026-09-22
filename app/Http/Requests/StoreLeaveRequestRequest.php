<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * Validates a leave request.
 *
 * Note what is missing: **there is no `status` field, and no `days_count`.**
 *
 * An earlier version accepted both, which let an employee post
 * `status: approved` and grant themselves leave — turning their own absences
 * into approved days on the attendance calendar. The fix is not a stricter
 * rule; it is that the client has nowhere to put the value. The status is set
 * by the service, and the day count is derived from the employee's own
 * working week.
 */
class StoreLeaveRequestRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            // Against the catalogue, and only its active rows: a type the
            // company has switched off must not come back through a
            // hand-crafted request.
            'leave_type' => ['required', Rule::exists('leave_types', 'key')->where('is_active', true)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A reason is required so whoever reviews the request knows what it is for.',
            'leave_type.exists' => 'That leave type does not exist, or is no longer in use.',
        ];
    }
}
