<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One attendance row as the client sees it.
 *
 * Timestamps are stored in UTC and rendered here in the company timezone —
 * the employee reads a wall clock, not an offset. `worked_hours` is the same
 * fact as `worked_minutes`, pre-formatted so every screen shows "7:20" the
 * same way instead of each doing its own division.
 */
class AttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $timezone = config('attendance.timezone');

        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee_name' => $this->whenLoaded('employee', fn () => $this->employee->name),
            'work_date' => $this->work_date?->format('Y-m-d'),
            'status' => $this->status,

            'check_in_at' => $this->check_in_at?->timezone($timezone)->toIso8601String(),
            'check_out_at' => $this->check_out_at?->timezone($timezone)->toIso8601String(),
            'check_in_time' => $this->check_in_at?->timezone($timezone)->format('H:i'),
            'check_out_time' => $this->check_out_at?->timezone($timezone)->format('H:i'),

            // The schedule as it was on this date, not as it is now.
            'scheduled_start' => $this->formatTime($this->scheduled_start),
            'scheduled_end' => $this->formatTime($this->scheduled_end),
            // Where the day was worked, snapshotted. Null on a day off, and on
            // a day whose schedule predates the location column.
            'location' => $this->location,
            // Where it was due to be worked, so a one-off "from home today"
            // still reads as an exception after the week itself is edited.
            'scheduled_location' => $this->scheduled_location,
            'is_location_exception' => $this->isLocationException(),
            'expected_minutes' => (int) $this->expected_minutes,
            'break_minutes' => (int) $this->break_minutes,

            'worked_minutes' => (int) $this->worked_minutes,
            'worked_hours' => $this->formatDuration((int) $this->worked_minutes),

            /*
             * What the employee said they did, gathered from the day's
             * sittings into one line.
             *
             * It is written at check-out and stored per session, so a day with
             * an evening return has two answers; management reads them as one
             * account of the day. Null when the sessions were not loaded —
             * absent data, not an empty day.
             */
            'tasks' => $this->whenLoaded('sessions', fn (): ?string => $this->sessions
                ->pluck('notes')
                ->filter()
                ->implode(' · ') ?: null),

            // A day can be worked in more than one sitting: the normal day,
            // then a deliberate return in the evening.
            'sessions' => AttendanceSessionResource::collection($this->whenLoaded('sessions')),
            'sessions_count' => $this->whenLoaded('sessions', fn () => $this->sessions->count()),
            'overtime_minutes' => $this->whenLoaded('sessions', fn () => $this->overtimeMinutes()),
            'approved_overtime_minutes' => $this->whenLoaded('sessions', fn () => $this->approvedOvertimeMinutes()),

            'is_open' => $this->isOpen(),
            'source' => $this->source,
            'correction_reason' => $this->correction_reason,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    /**
     * Times come back from the database as "09:00:00"; screens want "09:00".
     */
    private function formatTime(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }

    /**
     * Minutes as "7:20". Zero stays "0:00" rather than becoming an empty cell.
     */
    private function formatDuration(int $minutes): string
    {
        return intdiv($minutes, 60).':'.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);
    }
}
