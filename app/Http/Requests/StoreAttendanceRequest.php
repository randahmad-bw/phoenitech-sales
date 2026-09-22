<?php

namespace App\Http\Requests;

use App\Models\Attendance;
use Illuminate\Validation\Rule;

/**
 * Validates a record entered by management on an employee's behalf.
 *
 * Unlike the self-service punch, this one *does* accept an employee and times —
 * that is its whole purpose. It is therefore gated behind `attendance.edit`,
 * and a `reason` is mandatory so the audit trail records why a record exists
 * that nobody punched.
 *
 * Times are "HH:mm" in the company timezone; the service combines them with
 * the work date. The client never assembles a UTC timestamp.
 */
class StoreAttendanceRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'work_date' => ['required', 'date'],
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i', 'required_with:check_in_time'],
            // `pending` is deliberately not accepted: it is a derived status for
            // a day that has not happened, never something to store.
            'status' => ['nullable', Rule::in(Attendance::STATUSES)],
            'reason' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A reason is required so the record shows why it was created by hand.',
            'check_out_time.required_with' => 'A manually entered record with a check-in needs a check-out; leave both empty to record an absence.',
        ];
    }
}
