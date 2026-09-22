<?php

namespace App\Http\Resources;

use App\Models\Employee;
use App\Models\WorkScheduleDay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One employee together with their working week.
 *
 * This is the shape the schedule screen works in: management edits *people*,
 * not templates. The template underneath is an implementation detail and is
 * deliberately not named here.
 *
 * @property Employee $resource
 */
class EmployeeWeekResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $employee = $this->resource;
        $schedule = $employee->currentSchedule?->workSchedule;
        $days = $schedule?->days ?? collect();

        return [
            'employee_id' => $employee->id,
            'name' => $employee->name,
            'department' => $employee->department,
            'tracks_attendance' => (bool) $employee->tracks_attendance,
            'has_account' => $employee->user_id !== null,

            'has_schedule' => $schedule !== null && $days->isNotEmpty(),
            'working_days_count' => $days->count(),
            'weekly_minutes' => (int) $days->sum('expected_minutes'),

            // Ordered by weekday; the client decides which day the week starts on.
            'days' => $days
                ->sortBy('weekday')
                ->values()
                ->map(fn (WorkScheduleDay $day): array => [
                    'weekday' => $day->weekday,
                    'location' => $day->location,
                    'start_time' => substr((string) $day->start_time, 0, 5),
                    'end_time' => $day->end_time ? substr((string) $day->end_time, 0, 5) : null,
                    'is_open_ended' => $day->isOpenEnded(),
                    'break_minutes' => (int) $day->break_minutes,
                    'expected_minutes' => (int) $day->expected_minutes,
                ])
                ->all(),
        ];
    }
}
