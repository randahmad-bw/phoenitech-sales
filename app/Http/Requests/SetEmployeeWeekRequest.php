<?php

namespace App\Http\Requests;

use App\Models\WorkScheduleDay;
use Illuminate\Validation\Rule;

/**
 * Validates one employee's working week, set directly against that employee.
 *
 * `days` is the whole week. A weekday absent from the array is not a working
 * day — that is the only way the system expresses "does not work Fridays", and
 * it is why an empty array is allowed: someone can be on the attendance system
 * with no working days set yet.
 *
 * Weekdays use Carbon numbering (0 = Sunday … 6 = Saturday). `end_time` may be
 * null, meaning the day is open-ended: a fixed start, and the employee leaves
 * when the work is done.
 */
class SetEmployeeWeekRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'days' => ['present', 'array', 'max:7'],
            'days.*.weekday' => ['required', 'integer', 'between:0,6', 'distinct'],
            'days.*.location' => ['required', Rule::in(WorkScheduleDay::LOCATIONS)],
            'days.*.start_time' => ['required', 'date_format:H:i'],
            'days.*.end_time' => ['nullable', 'date_format:H:i'],
            'days.*.break_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],

            // Lets management take someone off the attendance system entirely
            // from the same screen — the case this exists for is people whose
            // work has no fixed hours or place.
            'tracks_attendance' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'days.*.weekday.distinct' => 'Each weekday may only appear once in a working week.',
        ];
    }
}
