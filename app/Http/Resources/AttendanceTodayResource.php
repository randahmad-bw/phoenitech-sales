<?php

namespace App\Http\Resources;

use App\Application\Support\TodayAttendance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The single payload behind the employee's screen.
 *
 * One request produces the status, the schedule, the times and — crucially —
 * `can_check_in` / `can_check_out`. The button is rendered from those two
 * booleans, so the browser never re-implements the rules and can never get
 * them subtly wrong.
 *
 * `server_time` is included so a live "you have been working for 3:12" counter
 * measures against the server's clock rather than the device's, which may be
 * wrong by minutes or deliberately set.
 *
 * @property TodayAttendance $resource
 */
class AttendanceTodayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $today = $this->resource;
        $scheduled = $today->scheduled;

        return [
            'state' => $today->state,
            'work_date' => $today->workDate->format('Y-m-d'),
            'server_time' => $today->serverTime->toIso8601String(),

            'has_schedule' => ! $scheduled->hasNoSchedule(),
            'is_working_day' => $scheduled->isWorkingDay(),
            'schedule' => $scheduled->hasNoSchedule() ? null : [
                'name' => $scheduled->schedule->name,
                'start' => $this->formatTime($scheduled->startTime()),
                'end' => $this->formatTime($scheduled->endTime()),
                'location' => $scheduled->location(),
                'is_open_ended' => $scheduled->isOpenEnded(),
                'expected_minutes' => $scheduled->expectedMinutes(),
                'break_minutes' => $scheduled->breakMinutes(),
            ],

            'attendance' => $today->attendance
                ? new AttendanceResource($today->attendance->loadMissing('sessions'))
                : null,

            'can_check_in' => $today->canCheckIn,
            'can_check_out' => $today->canCheckOut,
            // Checking in now would open a second sitting — extra work.
            'next_is_overtime' => $today->nextIsOvertime,
        ];
    }

    private function formatTime(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }
}
