<?php

namespace App\Application\Support;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\Holiday;
use Carbon\CarbonImmutable;

/**
 * Answers "what was this person supposed to be doing on this date?".
 *
 * Every attendance calculation starts here, and it deliberately resolves the
 * schedule *as it was on that date* rather than the employee's current one —
 * which is what keeps a schedule change from rewriting last month's records.
 *
 * Holidays and approved leave are resolved here too, so the nightly close, the
 * check-in flow and the history reader all agree on what a given day was.
 */
class ScheduleResolver
{
    /**
     * The schedule and day definition in force for an employee on a date.
     */
    public function scheduledDay(Employee $employee, CarbonImmutable $date): ScheduledDay
    {
        $assignment = $employee->schedules()
            ->covering($date->toDateString())
            ->with('workSchedule.days')
            ->orderByDesc('effective_from')
            ->first();

        $schedule = $assignment?->workSchedule;

        if (! $schedule) {
            return new ScheduledDay;
        }

        return new ScheduledDay($schedule, $schedule->dayFor($date->dayOfWeek));
    }

    /**
     * The holiday falling on a date, if any.
     *
     * Recurring holidays are matched on month-and-day in PHP rather than with a
     * database date function, so the behaviour is identical on SQLite (tests)
     * and MariaDB (production). The table is tiny by nature.
     */
    public function holidayOn(CarbonImmutable $date): ?Holiday
    {
        $exact = Holiday::whereDate('date', $date->toDateString())->first();

        if ($exact) {
            return $exact;
        }

        return Holiday::where('is_recurring', true)
            ->get()
            ->first(fn (Holiday $holiday): bool => $holiday->date?->format('m-d') === $date->format('m-d'));
    }

    /**
     * The approved leave covering a date for an employee, if any.
     *
     * Only approved leave counts — a pending request does not excuse an absence.
     */
    public function leaveOn(Employee $employee, CarbonImmutable $date): ?EmployeeLeave
    {
        return EmployeeLeave::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->first();
    }

    /**
     * The status a date carries when no work is expected, or null on an
     * ordinary working day.
     *
     * Order matters: an approved leave on a public holiday is a holiday, and a
     * holiday on someone's day off is still their day off — the narrower fact
     * about the person wins over the company-wide one only where it changes
     * what is owed, which it does not here.
     *
     * @return Attendance::STATUS_*|null
     */
    public function nonWorkingStatus(Employee $employee, CarbonImmutable $date, ?ScheduledDay $scheduled = null): ?string
    {
        $scheduled ??= $this->scheduledDay($employee, $date);

        if (! $scheduled->isWorkingDay()) {
            return Attendance::STATUS_DAY_OFF;
        }

        if ($this->holidayOn($date)) {
            return Attendance::STATUS_HOLIDAY;
        }

        if ($this->leaveOn($employee, $date)) {
            return Attendance::STATUS_LEAVE;
        }

        return null;
    }

    /**
     * "Now" in the company timezone — the only clock the module trusts.
     *
     * Never the client's clock: a device with a wrong time (or a helpful one)
     * must not be able to shift a check-in.
     */
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now(config('attendance.timezone'));
    }

    /**
     * Today's date in the company timezone.
     *
     * The application stores timestamps in UTC; the *date* a check-in belongs
     * to is resolved here, otherwise a 22:00 Damascus check-in would be filed
     * under the following day.
     */
    public function today(): CarbonImmutable
    {
        return $this->now()->startOfDay();
    }
}
