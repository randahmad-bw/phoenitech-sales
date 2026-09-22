<?php

namespace App\Http\Requests;

use App\Models\Attendance;
use Illuminate\Validation\Rule;

/**
 * Validates a correction to an existing record.
 *
 * `reason` is required, always. A correction changes the record of what a
 * person did, so it must carry an explanation — and because the reason is a
 * stored column, the audit trail captures it in the same before/after diff as
 * the times themselves.
 *
 * Nothing here is silently overwritten: the old values live on in `audit_logs`.
 */
class UpdateAttendanceRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            // Present-but-null is meaningful: it clears the time. The service
            // distinguishes "key absent" (leave as-is) from "key null" (clear).
            'check_in_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'check_out_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'status' => ['nullable', Rule::in(Attendance::STATUSES)],
            // Where the day was actually worked. What the schedule expected is
            // never rewritten by a correction — that is what makes the row
            // still read as an exception afterwards.
            'location' => ['sometimes', 'nullable', Rule::in(Attendance::LOCATIONS)],
            'reason' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A reason is required: corrections are recorded in the audit trail alongside the original value.',
        ];
    }
}
