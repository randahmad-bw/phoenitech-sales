<?php

namespace App\Application\Support;

use App\Models\WorkSchedule;
use App\Models\WorkScheduleDay;

/**
 * What a given date looks like for one employee: the schedule in force, and the
 * day definition inside it — or null when that weekday is not a working day.
 *
 * Returned by ScheduleResolver so callers stop juggling two nullable models and
 * re-deriving "is this a working day?" in three different places.
 */
final readonly class ScheduledDay
{
    public function __construct(
        public ?WorkSchedule $schedule = null,
        public ?WorkScheduleDay $day = null,
    ) {}

    /**
     * No schedule has been assigned to this employee for this date at all.
     *
     * Distinct from a day off: management has simply not configured them yet,
     * and attendance cannot be recorded until they do.
     */
    public function hasNoSchedule(): bool
    {
        return $this->schedule === null;
    }

    /**
     * The employee is expected at work on this date.
     */
    public function isWorkingDay(): bool
    {
        return $this->day !== null;
    }

    public function startTime(): ?string
    {
        return $this->day?->start_time;
    }

    public function endTime(): ?string
    {
        return $this->day?->end_time;
    }

    public function expectedMinutes(): int
    {
        return (int) ($this->day?->expected_minutes ?? 0);
    }

    public function breakMinutes(): int
    {
        return (int) ($this->day?->break_minutes ?? 0);
    }

    /**
     * Where this day is worked — 'office' or 'remote' — or null on a day off.
     *
     * Per day, not per employee: someone may be in the office on Saturday and
     * at home on Monday.
     */
    public function location(): ?string
    {
        return $this->day?->location;
    }

    /**
     * The day has a start but no scheduled finish: the employee leaves when
     * the work is done, and `expectedMinutes()` is therefore 0.
     */
    public function isOpenEnded(): bool
    {
        return $this->day !== null && $this->day->isOpenEnded();
    }
}
