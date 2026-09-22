<?php

namespace App\Application\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleDay;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Schedule templates and their assignment to employees.
 *
 * The one rule worth stating: an assignment is **never edited in place**.
 * Giving someone a new schedule closes the running assignment the day before
 * the new one starts and inserts a new row. That is what keeps last March's
 * records meaningful after this month's reorganisation, and it makes a
 * temporary schedule change nothing more than a row with both ends set.
 */
class WorkScheduleService
{
    /**
     * @return Collection<int, WorkSchedule>
     */
    public function list(): Collection
    {
        return WorkSchedule::with('days')
            ->withCount('assignments')
            ->orderBy('name')
            ->get();
    }

    /**
     * Create a template and its working days in one transaction.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): WorkSchedule
    {
        return DB::transaction(function () use ($data): WorkSchedule {
            $schedule = WorkSchedule::create([
                'name' => $data['name'],
                'is_active' => $data['is_active'] ?? true,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncDays($schedule, $data['days'] ?? []);

            return $schedule->load('days');
        });
    }

    /**
     * Update a template, replacing its working days wholesale.
     *
     * Editing a template does not disturb history: every attendance row carries
     * its own snapshot of the times that applied on that date.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(WorkSchedule $schedule, array $data): WorkSchedule
    {
        return DB::transaction(function () use ($schedule, $data): WorkSchedule {
            $schedule->update(array_filter([
                'name' => $data['name'] ?? null,
                'notes' => $data['notes'] ?? null,
            ], fn ($value): bool => $value !== null) + (
                array_key_exists('is_active', $data) ? ['is_active' => $data['is_active']] : []
            ));

            if (array_key_exists('days', $data)) {
                $this->syncDays($schedule, $data['days']);
            }

            return $schedule->fresh(['days']);
        });
    }

    public function delete(WorkSchedule $schedule): void
    {
        if ($schedule->assignments()->exists()) {
            throw new BusinessRuleException(
                'This schedule is assigned to employees and cannot be deleted. Deactivate it instead, or move those employees to another schedule.',
                'WORK_SCHEDULE_IN_USE'
            );
        }

        $schedule->delete();
    }

    /**
     * Assign a schedule to an employee from a date onwards.
     *
     * The employee's open assignment is closed the day before, so the two never
     * overlap and every past date still resolves to exactly one schedule.
     */
    public function assign(Employee $employee, WorkSchedule $schedule, CarbonImmutable $from, ?string $notes = null): EmployeeSchedule
    {
        return DB::transaction(function () use ($employee, $schedule, $from, $notes): EmployeeSchedule {
            $current = $employee->schedules()
                ->whereNull('effective_to')
                ->orderByDesc('effective_from')
                ->first();

            if ($current) {
                if ($current->effective_from->gte($from)) {
                    throw new BusinessRuleException(
                        'The new schedule must start after the current one began ('.$current->effective_from->format('Y-m-d').').',
                        'SCHEDULE_OVERLAP'
                    );
                }

                $current->update(['effective_to' => $from->subDay()->toDateString()]);
            }

            return EmployeeSchedule::create([
                'employee_id' => $employee->id,
                'work_schedule_id' => $schedule->id,
                'effective_from' => $from->toDateString(),
                'effective_to' => null,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * An employee's assignment history, newest first.
     *
     * @return Collection<int, EmployeeSchedule>
     */
    public function assignmentsFor(Employee $employee): Collection
    {
        return $employee->schedules()->with('workSchedule.days')->get();
    }

    /**
     * The working week in force for one employee, or null if none is set.
     */
    public function weekFor(Employee $employee): ?WorkSchedule
    {
        return $employee->currentSchedule?->workSchedule?->load('days');
    }

    /**
     * Set an employee's working week directly.
     *
     * Management edits people, not templates — so this is the endpoint the UI
     * uses. Templates still exist underneath because they are what carries the
     * days, the assignment date range and the history; they are simply no
     * longer something anyone has to think about.
     *
     * Two paths:
     *   - the employee already has a schedule nobody else uses → edit it in
     *     place, which is what "change Ahmad's hours" should do;
     *   - the schedule is shared with other people (or there is none) → give
     *     this employee their own, leaving everyone else's week untouched.
     *
     * Editing in place cannot corrupt history: every attendance row carries
     * its own snapshot of the times that applied on that date.
     *
     * @param  array<int, array<string, mixed>>  $days
     */
    public function setEmployeeWeek(Employee $employee, array $days): WorkSchedule
    {
        return DB::transaction(function () use ($employee, $days): WorkSchedule {
            $current = $employee->schedules()->whereNull('effective_to')->first();
            $schedule = $current?->workSchedule;

            $isPrivate = $schedule !== null
                && $schedule->assignments()->whereNull('effective_to')->count() === 1;

            if ($isPrivate) {
                $this->syncDays($schedule, $days);

                return $schedule->fresh(['days']);
            }

            // Shared or absent — mint a personal one so changing this person's
            // week does not silently change their colleagues' too.
            $personal = WorkSchedule::create([
                'name' => $employee->name,
                'is_active' => true,
            ]);

            $this->syncDays($personal, $days);

            $today = CarbonImmutable::now(config('attendance.timezone'))->startOfDay();

            if ($current && $current->effective_from->gte($today)) {
                // The running assignment started today — repoint it rather than
                // closing it, which would leave a range ending before it began.
                $current->update(['work_schedule_id' => $personal->id]);
            } else {
                $this->assign($employee, $personal, $today);
            }

            return $personal->fresh(['days']);
        });
    }

    /**
     * Replace a template's day rows.
     *
     * A weekday simply absent from the payload becomes a non-working day —
     * that is how "this employee does not work Fridays" is expressed.
     *
     * @param  array<int, array<string, mixed>>  $days
     */
    private function syncDays(WorkSchedule $schedule, array $days): void
    {
        $schedule->days()->delete();

        foreach ($days as $day) {
            $schedule->days()->create([
                'weekday' => $day['weekday'],
                'sort_order' => $day['sort_order'] ?? 0,
                'location' => $day['location'] ?? WorkScheduleDay::LOCATION_OFFICE,
                'start_time' => $day['start_time'],
                // Absent or null means an open-ended day: the employee leaves
                // when the work is done, and the day expects no set hours.
                'end_time' => $day['end_time'] ?? null,
                'break_minutes' => $day['break_minutes'] ?? 0,
            ]);
        }
    }
}
