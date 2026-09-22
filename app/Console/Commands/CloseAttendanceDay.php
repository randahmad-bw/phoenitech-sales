<?php

namespace App\Console\Commands;

use App\Application\Support\ScheduleResolver;
use App\Models\Attendance;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Closes a finished day: marks forgotten check-outs and records absences.
 *
 * Runs nightly for *yesterday*, once that day can no longer change.
 *
 * Two things happen, and deliberately only two:
 *
 *  1. A row that was opened and never closed becomes `incomplete`. The command
 *     does **not** invent a departure time — by default nothing is written to
 *     check_out_at, because only a human knows when the person actually left.
 *     Management corrects it, with a reason, on the record.
 *  2. A scheduled working day with no row at all becomes `absent`, written with
 *     source `system`.
 *
 * Days off, holidays and approved leave are skipped entirely: they are derived
 * on read, so materialising them would bloat the table with rows that say
 * nothing the schedule does not already say.
 */
class CloseAttendanceDay extends Command
{
    protected $signature = 'attendance:close-day
                            {--date= : The day to close (Y-m-d). Defaults to yesterday.}
                            {--dry-run : Report what would change without writing anything.}';

    protected $description = 'Mark forgotten check-outs as incomplete and record absences for a finished day';

    public function handle(ScheduleResolver $resolver): int
    {
        $date = $this->option('date')
            ? CarbonImmutable::parse($this->option('date'), config('attendance.timezone'))->startOfDay()
            : $resolver->today()->subDay();

        if ($date->gte($resolver->today())) {
            $this->error('Refusing to close '.$date->format('Y-m-d').': the day is not over yet.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $incomplete = 0;
        $absent = 0;

        $existing = Attendance::whereDate('work_date', $date->toDateString())
            ->get()
            ->keyBy('employee_id');

        foreach (Employee::tracksAttendance()->whereNotNull('user_id')->get() as $employee) {
            $row = $existing->get($employee->id);

            if ($row) {
                if ($row->isOpen()) {
                    $incomplete++;

                    if (! $dryRun) {
                        $this->closeOpenRow($row, $resolver, $date);
                    }
                }

                continue;
            }

            // No row: an absence, unless nothing was expected of them.
            if ($resolver->nonWorkingStatus($employee, $date) !== null) {
                continue;
            }

            $absent++;

            if (! $dryRun) {
                $this->recordAbsence($employee, $resolver, $date);
            }
        }

        $this->info(sprintf(
            '%s %s: %d incomplete, %d absent.',
            $dryRun ? 'Would close' : 'Closed',
            $date->format('Y-m-d'),
            $incomplete,
            $absent,
        ));

        return self::SUCCESS;
    }

    /**
     * A day that was started and never finished.
     */
    private function closeOpenRow(Attendance $row, ScheduleResolver $resolver, CarbonImmutable $date): void
    {
        $attributes = [
            'status' => Attendance::STATUS_INCOMPLETE,
            'source' => Attendance::SOURCE_SYSTEM,
        ];

        // Off by default: guessing a departure time puts a number management
        // did not choose onto someone's record.
        if (config('attendance.auto_checkout_on_close') && $row->scheduled_end) {
            // ->utc() matters: the scheduled end is a company-timezone wall
            // clock, and Eloquent stores a datetime's digits as given.
            $checkOut = $date->setTimeFromTimeString($row->scheduled_end)->utc();

            if ($checkOut->gt($row->check_in_at)) {
                $attributes['check_out_at'] = $checkOut;
                $attributes['worked_minutes'] = (int) max(
                    0,
                    (int) $row->check_in_at->diffInMinutes($checkOut, absolute: false) - (int) $row->break_minutes
                );
                $attributes['correction_reason'] = 'Auto-closed at the scheduled end time by attendance:close-day.';
            }
        }

        $row->update($attributes);
    }

    /**
     * A scheduled working day that nobody showed up for.
     */
    private function recordAbsence(Employee $employee, ScheduleResolver $resolver, CarbonImmutable $date): void
    {
        $scheduled = $resolver->scheduledDay($employee, $date);

        Attendance::create([
            'employee_id' => $employee->id,
            'work_date' => $date->toDateString(),
            'status' => Attendance::STATUS_ABSENT,
            'work_schedule_id' => $scheduled->schedule?->id,
            'scheduled_start' => $scheduled->startTime(),
            'scheduled_end' => $scheduled->endTime(),
            'location' => $scheduled->location(),
            'expected_minutes' => $scheduled->expectedMinutes(),
            'break_minutes' => $scheduled->breakMinutes(),
            'worked_minutes' => 0,
            'source' => Attendance::SOURCE_SYSTEM,
        ]);
    }
}
