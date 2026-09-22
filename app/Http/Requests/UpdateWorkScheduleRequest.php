<?php

namespace App\Http\Requests;

use App\Models\WorkScheduleDay;
use Illuminate\Validation\Rule;

/**
 * Validates an edit to a schedule template.
 *
 * Omitting `days` leaves the working week untouched; sending it replaces the
 * week wholesale, which is how a weekday is removed.
 *
 * Editing a template never rewrites history — every attendance row carries its
 * own snapshot of the times that applied on that date.
 */
class UpdateWorkScheduleRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],

            'days' => ['sometimes', 'array', 'max:14'],
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
