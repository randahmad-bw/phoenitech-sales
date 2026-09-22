<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleDay;
use Illuminate\Database\Seeder;

/**
 * The real working weeks, as management described them on 2026-09-20.
 *
 * Every schedule here is per person because no two are alike: the week starts
 * on Saturday for some and Sunday for others, and the place of work changes
 * from one day to the next for the same employee. That is exactly why
 * work_schedule_days carries a row per weekday with its own location, and why
 * nothing about the working week is hard-coded.
 *
 * Re-runnable: schedules are found-or-created by name, their days are rebuilt,
 * and an employee is only given an assignment if they do not already have one.
 *
 * Not included, by decision:
 *   - Management (الإدارة) is outside the attendance system entirely.
 *   - Sales work by field visits with no fixed hours or place.
 * Both are marked `tracks_attendance = false` rather than left unconfigured,
 * so the dashboard does not keep asking to set them up.
 */
class WorkScheduleSeeder extends Seeder
{
    private const OFFICE = WorkScheduleDay::LOCATION_OFFICE;

    private const REMOTE = WorkScheduleDay::LOCATION_REMOTE;

    // Carbon weekday numbering: 0 = Sunday … 6 = Saturday.
    private const SAT = 6;

    private const SUN = 0;

    private const MON = 1;

    private const TUE = 2;

    private const WED = 3;

    private const THU = 4;

    public function run(): void
    {
        $this->markUntracked();

        foreach ($this->schedules() as $employeeName => $definition) {
            $employee = Employee::where('name', $employeeName)->first();

            if (! $employee) {
                $this->command?->warn("Skipped {$employeeName}: no employee profile found.");

                continue;
            }

            $schedule = $this->syncSchedule($definition['name'], $definition['days']);

            $employee->forceFill(['tracks_attendance' => true])->save();

            $this->assign($employee, $schedule);
        }
    }

    /**
     * Who the attendance module does not apply to.
     *
     * Management is deliberately outside it. Sales are on the road: no fixed
     * hours and no fixed place, so a check-in would measure nothing.
     */
    private function markUntracked(): void
    {
        Employee::whereIn('department', ['management', 'sales'])
            ->update(['tracks_attendance' => false]);
    }

    /**
     * The working weeks themselves.
     *
     * A weekday absent from a `days` array is simply not a working day — that
     * is the whole mechanism behind "Saturday to Wednesday" versus "Sunday to
     * Thursday".
     *
     * @return array<string, array{name: string, days: array<int, array{0: int, 1: string, 2: string|null, 3: string}>}>
     */
    private function schedules(): array
    {
        // [weekday, start, end (null = open-ended), location]
        $eightToOne = fn (int $weekday, string $location): array => [$weekday, '08:00', '13:00', $location];

        return [
            // Saturday–Wednesday, 08:00–13:00, all from the office.
            'كمال' => [
                'name' => 'مكتبي — السبت إلى الأربعاء 08:00-13:00',
                'days' => [
                    $eightToOne(self::SAT, self::OFFICE),
                    $eightToOne(self::SUN, self::OFFICE),
                    $eightToOne(self::MON, self::OFFICE),
                    $eightToOne(self::TUE, self::OFFICE),
                    $eightToOne(self::WED, self::OFFICE),
                ],
            ],

            // Same week as كمال — shares the template.
            'مروان' => [
                'name' => 'مكتبي — السبت إلى الأربعاء 08:00-13:00',
                'days' => [
                    $eightToOne(self::SAT, self::OFFICE),
                    $eightToOne(self::SUN, self::OFFICE),
                    $eightToOne(self::MON, self::OFFICE),
                    $eightToOne(self::TUE, self::OFFICE),
                    $eightToOne(self::WED, self::OFFICE),
                ],
            ],

            // Sunday–Thursday, 08:00–13:00, office.
            'عمر' => [
                'name' => 'مكتبي — الأحد إلى الخميس 08:00-13:00',
                'days' => [
                    $eightToOne(self::SUN, self::OFFICE),
                    $eightToOne(self::MON, self::OFFICE),
                    $eightToOne(self::TUE, self::OFFICE),
                    $eightToOne(self::WED, self::OFFICE),
                    $eightToOne(self::THU, self::OFFICE),
                ],
            ],

            // Same week as عمر — shares the template.
            'حلا' => [
                'name' => 'مكتبي — الأحد إلى الخميس 08:00-13:00',
                'days' => [
                    $eightToOne(self::SUN, self::OFFICE),
                    $eightToOne(self::MON, self::OFFICE),
                    $eightToOne(self::TUE, self::OFFICE),
                    $eightToOne(self::WED, self::OFFICE),
                    $eightToOne(self::THU, self::OFFICE),
                ],
            ],

            // Office Saturday and Sunday, from home Monday to Wednesday.
            'زين' => [
                'name' => 'مختلط — السبت والأحد مكتبي، الاثنين إلى الأربعاء عن بُعد',
                'days' => [
                    $eightToOne(self::SAT, self::OFFICE),
                    $eightToOne(self::SUN, self::OFFICE),
                    $eightToOne(self::MON, self::REMOTE),
                    $eightToOne(self::TUE, self::REMOTE),
                    $eightToOne(self::WED, self::REMOTE),
                ],
            ],

            // Online Monday–Thursday, office on Sunday.
            'سابين' => [
                'name' => 'مختلط — الأحد مكتبي، الاثنين إلى الخميس أونلاين',
                'days' => [
                    $eightToOne(self::SUN, self::OFFICE),
                    $eightToOne(self::MON, self::REMOTE),
                    $eightToOne(self::TUE, self::REMOTE),
                    $eightToOne(self::WED, self::REMOTE),
                    $eightToOne(self::THU, self::REMOTE),
                ],
            ],

            // Online Monday–Thursday; Sunday in the office from 10:00 with no
            // fixed finish — she checks out when the work is done, which is why
            // the end time is null and the day expects no set number of hours.
            'نوال' => [
                'name' => 'مختلط — الأحد مكتبي 10:00 مفتوح، الاثنين إلى الخميس أونلاين',
                'days' => [
                    [self::SUN, '10:00', null, self::OFFICE],
                    $eightToOne(self::MON, self::REMOTE),
                    $eightToOne(self::TUE, self::REMOTE),
                    $eightToOne(self::WED, self::REMOTE),
                    $eightToOne(self::THU, self::REMOTE),
                ],
            ],
        ];
    }

    /**
     * Find-or-create a template and rebuild its days.
     *
     * @param  array<int, array{0: int, 1: string, 2: string|null, 3: string}>  $days
     */
    private function syncSchedule(string $name, array $days): WorkSchedule
    {
        $schedule = WorkSchedule::firstOrCreate(['name' => $name], ['is_active' => true]);

        $schedule->days()->delete();

        foreach ($days as [$weekday, $start, $end, $location]) {
            $schedule->days()->create([
                'weekday' => $weekday,
                'location' => $location,
                'start_time' => $start,
                'end_time' => $end,
                // No break is deducted: a 08:00–13:00 day is worked straight
                // through. Set this per schedule if that changes.
                'break_minutes' => 0,
            ]);
        }

        return $schedule->fresh(['days']);
    }

    /**
     * Give the employee this schedule, unless they already hold it.
     *
     * Assignments are append-only, so re-running the seeder must not stack up
     * duplicate rows or close a perfectly good assignment.
     */
    private function assign(Employee $employee, WorkSchedule $schedule): void
    {
        $current = $employee->schedules()->whereNull('effective_to')->first();

        if ($current?->work_schedule_id === $schedule->id) {
            return;
        }

        $effectiveFrom = now(config('attendance.timezone'))->startOfMonth()->toDateString();

        if ($current) {
            $current->update([
                'effective_to' => now(config('attendance.timezone'))->startOfMonth()->subDay()->toDateString(),
            ]);
        }

        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'effective_from' => $effectiveFrom,
        ]);
    }
}
