<?php

namespace App\Application\Support;

use App\Models\Attendance;
use Carbon\CarbonImmutable;

/**
 * Everything the employee's screen needs, decided on the server.
 *
 * `canCheckIn` / `canCheckOut` are computed here rather than inferred in the
 * browser: the frontend renders this, it does not reason about it. That keeps
 * one copy of the rules, and the API rejects a wrong call regardless.
 */
final readonly class TodayAttendance
{
    /** Nothing recorded yet on a working day — the check-in button is live. */
    public const STATE_NOT_CHECKED_IN = 'not_checked_in';

    /**
     * Management has not assigned this employee a schedule yet.
     *
     * Kept distinct from `day_off`: telling someone it is their day off when
     * nobody has configured their working week is a lie, and it sends them
     * looking for the wrong fix.
     */
    public const STATE_NO_SCHEDULE = 'no_schedule';

    /** Checked in, still at work. */
    public const STATE_WORKING = 'working';

    /** Checked in and out. */
    public const STATE_COMPLETED = 'completed';

    public function __construct(
        public string $state,
        public CarbonImmutable $workDate,
        public ScheduledDay $scheduled,
        public ?Attendance $attendance,
        public bool $canCheckIn,
        public bool $canCheckOut,
        /**
         * Checking in now would start a *second* sitting — the day is already
         * finished. Said plainly on the card, so nobody starts extra work by
         * accident and nobody wonders why the button came back.
         */
        public bool $nextIsOvertime,
        public CarbonImmutable $serverTime,
    ) {}

    public function workedMinutes(): int
    {
        return (int) ($this->attendance?->worked_minutes ?? 0);
    }
}
