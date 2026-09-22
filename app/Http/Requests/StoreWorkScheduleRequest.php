<?php

namespace App\Http\Requests;

use App\Models\WorkScheduleDay;
use Illuminate\Validation\Rule;

/**
 * Validates a work schedule template and its working days.
 *
 * `days` is the whole working week: a weekday absent from the array is not a
 * working day. That is the only way the system expresses "no work on Friday",
 * which is why an empty array is allowed — a schedule with no days yet is a
 * draft, not an error.
 *
 * Weekdays use Carbon numbering (0 = Sunday … 6 = Saturday). Two rows may share
 * a weekday only with different sort_order values, which is the reserved shape
 * for split shifts.
 */
class StoreWorkScheduleRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],

            'days' => ['nullable', 'array', 'max:14'],
            'days.*.weekday' => ['required', 'integer', 'between:0,6'],
            'days.*.sort_order' => ['nullable', 'integer', 'between:0,3'],
            'days.*.start_time' => ['required', 'date_format:H:i'],
            // Nullable on purpose: an absent end time means the day is
            // open-ended — the employee leaves when the work is done.
            'days.*.end_time' => ['nullable', 'date_format:H:i'],
            'days.*.location' => ['nullable', Rule::in(WorkScheduleDay::LOCATIONS)],
            'days.*.break_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],
        ];
    }
}
