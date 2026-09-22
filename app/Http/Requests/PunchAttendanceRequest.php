<?php

namespace App\Http\Requests;

use App\Models\Attendance;
use Illuminate\Validation\Rule;

/**
 * Validates a self-service check-in or check-out.
 *
 * There is deliberately almost nothing here. The request carries no time, no
 * date, no employee id and no status: the employee is taken from the
 * authenticated token and the clock from the server, so there is nothing a
 * hand-crafted request can use to shift its own hours.
 *
 * `location` is the one exception, and it is a narrow one. Where someone
 * worked is a *fact only they can report* — the server cannot derive it — and
 * it changes nothing that is owed: the expected minutes, the status and the
 * times are all untouched by it. It is recorded beside what the schedule
 * expected, so management sees the exception in the grid and can correct it.
 */
class PunchAttendanceRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:500'],
            // Absent means "wherever I was due to be" — the schedule decides.
            'location' => ['sometimes', Rule::in(Attendance::LOCATIONS)],
        ];
    }
}
