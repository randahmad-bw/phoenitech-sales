<?php

namespace App\Http\Requests;

/**
 * Validates giving an employee a schedule from a date onwards.
 *
 * The running assignment is closed the day before `effective_from`, never
 * edited, so each past date still resolves to exactly one schedule.
 */
class AssignWorkScheduleRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'work_schedule_id' => ['required', 'integer', 'exists:work_schedules,id'],
            'effective_from' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
