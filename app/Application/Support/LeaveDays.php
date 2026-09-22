<?php

namespace App\Application\Support;

use App\Models\Employee;
use Carbon\CarbonImmutable;

/**
 * How many days a stretch of leave actually costs an employee.
 *
 * Leave is spent on **working days**. A request covering a weekend, one of the
 * employee's own days off, or a public holiday should not take those days from
 * their balance — and the schedule already knows which they are.
 *
 * This lives here because two services need the same answer and were giving
 * different ones: the request flow counted working days, while management
 * entering leave by hand counted calendar days. The same three-day stretch
 * therefore cost two days or three depending on who typed it in.
 */
class LeaveDays
{
    public function __construct(private ScheduleResolver $resolver) {}

    /**
     * Working days between two dates, inclusive.
     *
     * Falls back to the calendar length when the employee has no schedule at
     * all — better a rough number than a zero that reads as a free fortnight.
     */
    public function between(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): float
    {
        $days = 0;
        $hasSchedule = false;

        for ($date = $from; $date->lte($to); $date = $date->addDay()) {
            $scheduled = $this->resolver->scheduledDay($employee, $date);

            if ($scheduled->hasNoSchedule()) {
                continue;
            }

            $hasSchedule = true;

            if ($scheduled->isWorkingDay() && ! $this->resolver->holidayOn($date)) {
                $days++;
            }
        }

        if (! $hasSchedule) {
            return (float) ($from->diffInDays($to) + 1);
        }

        return (float) $days;
    }
}
