<?php

namespace App\Application\Services;

use App\Application\Support\ScheduleResolver;
use App\Exceptions\BusinessRuleException;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * The management half of attendance: reading across people, and correcting.
 *
 * The reader fills gaps rather than the writer creating rows. Days off,
 * holidays and approved leave are **not** stored — they are derived here into
 * unsaved Attendance instances so every screen sees a complete calendar while
 * the table stays proportional to actual work. Only `absent` is ever
 * materialised, by the nightly close, because it is a fact management acts on.
 */
class AttendanceManagementService
{
    public function __construct(private ScheduleResolver $resolver) {}

    /**
     * One day across the whole company: a row per employee, real or derived.
     *
     * @param  array{employee_id?: int|null, department?: string|null, status?: string|null}  $filters
     * @return Collection<int, Attendance>
     */
    public function dayGrid(CarbonImmutable $date, array $filters = []): Collection
    {
        $employees = Employee::query()
            ->tracksAttendance()
            ->when($filters['employee_id'] ?? null, fn ($q, $id) => $q->where('id', $id))
            ->when($filters['department'] ?? null, fn ($q, $d) => $q->where('department', $d))
            ->orderBy('name')
            ->get();

        // Sessions come along because the grid shows what each person said
        // they did — that answer lives on the sitting, not on the day's row.
        $rows = Attendance::whereDate('work_date', $date->toDateString())
            ->with('sessions')
            ->get()
            ->keyBy('employee_id');

        return $employees
            ->map(function (Employee $employee) use ($date, $rows): Attendance {
                $row = $rows->get($employee->id) ?? $this->derivedRow($employee, $date);
                $row->setRelation('employee', $employee);

                return $row;
            })
            ->when(
                $filters['status'] ?? null,
                fn (Collection $c, string $status) => $c->where('status', $status)
            )
            ->values();
    }

    /**
     * Headline counters for the management dashboard.
     *
     * @return array<string, int|string>
     */
    public function overview(CarbonImmutable $date): array
    {
        $grid = $this->dayGrid($date);

        $expectedToWork = $grid->filter(fn (Attendance $a): bool => ! $a->isNonWorkingDay());
        $checkedIn = $grid->filter(fn (Attendance $a): bool => $a->check_in_at !== null);

        return [
            'date' => $date->format('Y-m-d'),
            'total_employees' => $grid->count(),
            'expected_to_work' => $expectedToWork->count(),
            'checked_in' => $checkedIn->count(),
            'not_checked_in' => $expectedToWork->filter(fn (Attendance $a): bool => $a->check_in_at === null)->count(),
            'currently_working' => $grid->filter(fn (Attendance $a): bool => $a->isOpen())->count(),
            'completed' => $grid->filter(fn (Attendance $a): bool => $a->check_out_at !== null)->count(),
            'on_leave' => $grid->where('status', Attendance::STATUS_LEAVE)->count(),
            'day_off' => $grid->where('status', Attendance::STATUS_DAY_OFF)->count(),
            'holiday' => $grid->where('status', Attendance::STATUS_HOLIDAY)->count(),
            'absent' => $grid->where('status', Attendance::STATUS_ABSENT)->count(),
            'incomplete' => $grid->where('status', Attendance::STATUS_INCOMPLETE)->count(),
            // Not a status but the thing management most needs to act on:
            // nobody can record attendance until they have a schedule.
            'without_schedule' => $grid->filter(
                fn (Attendance $a): bool => $a->work_schedule_id === null && $a->id === null
            )->count(),
        ];
    }

    /**
     * One employee's calendar over a range, with derived days filled in.
     *
     * @return Collection<int, Attendance>
     */
    public function employeeGrid(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $rows = Attendance::where('employee_id', $employee->id)
            ->betweenDates($from->toDateString(), $to->toDateString())
            ->get()
            ->keyBy(fn (Attendance $a): string => $a->work_date->format('Y-m-d'));

        $grid = collect();

        for ($date = $from; $date->lte($to); $date = $date->addDay()) {
            $key = $date->format('Y-m-d');
            $row = $rows->get($key) ?? $this->derivedRow($employee, $date);
            $row->setRelation('employee', $employee);
            $grid->push($row);
        }

        return $grid->sortByDesc(fn (Attendance $a): string => $a->work_date->format('Y-m-d'))->values();
    }

    /**
     * Monthly totals for one employee.
     *
     * @return array<string, int>
     */
    public function employeeSummary(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $grid = $this->employeeGrid($employee, $from, $to);

        return [
            'working_days' => $grid->filter(fn (Attendance $a): bool => ! $a->isNonWorkingDay())->count(),
            'present_days' => $grid->where('status', Attendance::STATUS_PRESENT)->count(),
            'absent_days' => $grid->where('status', Attendance::STATUS_ABSENT)->count(),
            'incomplete_days' => $grid->where('status', Attendance::STATUS_INCOMPLETE)->count(),
            'leave_days' => $grid->where('status', Attendance::STATUS_LEAVE)->count(),
            'day_off_days' => $grid->where('status', Attendance::STATUS_DAY_OFF)->count(),
            'holiday_days' => $grid->where('status', Attendance::STATUS_HOLIDAY)->count(),
            'worked_minutes' => (int) $grid->sum('worked_minutes'),
            'expected_minutes' => (int) $grid->sum('expected_minutes'),
        ];
    }

    /**
     * Every tracked employee's totals over a range — the week or month view.
     *
     * One row per person rather than per day: reviewing a week by stepping
     * through seven daily grids is seven screens to hold in your head, and the
     * question being asked ("who is short of hours, who was absent") is a
     * question about people.
     *
     * Built on `employeeSummary()` so a figure here can never disagree with the
     * same figure in one employee's own calendar.
     *
     * @param  array{department?: string|null}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function teamSummary(CarbonImmutable $from, CarbonImmutable $to, array $filters = []): Collection
    {
        return Employee::query()
            ->tracksAttendance()
            ->when($filters['department'] ?? null, fn ($q, $d) => $q->where('department', $d))
            ->orderBy('name')
            ->get()
            ->map(fn (Employee $employee): array => [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'department' => $employee->department,
            ] + $this->employeeSummary($employee, $from, $to))
            ->values();
    }

    /**
     * Create a record by hand — a day the employee could not punch themselves.
     *
     * @param  array<string, mixed>  $data
     */
    public function createManually(array $data, int $actorId): Attendance
    {
        $employee = Employee::findOrFail($data['employee_id']);
        $date = CarbonImmutable::parse($data['work_date'], config('attendance.timezone'))->startOfDay();

        $exists = Attendance::where('employee_id', $employee->id)
            ->whereDate('work_date', $date->toDateString())
            ->exists();

        if ($exists) {
            throw new BusinessRuleException(
                'This employee already has a record for '.$date->format('Y-m-d').'. Edit that record instead.',
                'ATTENDANCE_ALREADY_EXISTS'
            );
        }

        $scheduled = $this->resolver->scheduledDay($employee, $date);
        $nonWorking = $this->resolver->nonWorkingStatus($employee, $date, $scheduled);
        $expectsWork = $nonWorking === null;

        $checkIn = $this->combine($date, $data['check_in_time'] ?? null);
        $checkOut = $this->combine($date, $data['check_out_time'] ?? null, $checkIn);

        $attendance = Attendance::create([
            'employee_id' => $employee->id,
            'work_date' => $date->toDateString(),
            'check_in_at' => $checkIn,
            'check_out_at' => $checkOut,
            'status' => $data['status'] ?? ($nonWorking ?? Attendance::STATUS_PRESENT),
            'work_schedule_id' => $scheduled->schedule?->id,
            'scheduled_start' => $scheduled->startTime(),
            'scheduled_end' => $scheduled->endTime(),
            // Management may enter a day that was worked somewhere other than
            // the schedule expected; what was expected is recorded either way.
            'location' => $expectsWork ? ($data['location'] ?? $scheduled->location()) : null,
            'scheduled_location' => $expectsWork ? $scheduled->location() : null,
            'expected_minutes' => $expectsWork ? $scheduled->expectedMinutes() : 0,
            'break_minutes' => $expectsWork ? $scheduled->breakMinutes() : 0,
            'worked_minutes' => $this->workedMinutes($checkIn, $checkOut, $expectsWork ? $scheduled->breakMinutes() : 0),
            'source' => Attendance::SOURCE_MANUAL,
            'correction_reason' => $data['reason'],
            'notes' => $data['notes'] ?? null,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        /*
         * A hand-entered day still needs its sitting, or it would exist as a
         * summary with nothing underneath — invisible to check-out, which
         * looks for an open session, and impossible to total correctly.
         */
        if ($checkIn) {
            $attendance->sessions()->create([
                'sequence' => 1,
                'check_in_at' => $checkIn,
                'check_out_at' => $checkOut,
                'worked_minutes' => (int) $attendance->worked_minutes,
                'is_overtime' => false,
                'overtime_status' => null,
                'source' => Attendance::SOURCE_MANUAL,
            ]);
        }

        return $attendance->fresh(['sessions']);
    }

    /**
     * Correct an existing record.
     *
     * The original value is never quietly replaced: the Auditable trait writes
     * the before/after diff to the audit trail, `correction_reason` is required
     * by the FormRequest and therefore lands in that diff, and `source` becomes
     * `manual` so the row itself shows it was touched by hand.
     *
     * @param  array<string, mixed>  $data
     */
    public function correct(Attendance $attendance, array $data, int $actorId): Attendance
    {
        $date = CarbonImmutable::parse($attendance->work_date->format('Y-m-d'), config('attendance.timezone'));

        $checkIn = array_key_exists('check_in_time', $data)
            ? $this->combine($date, $data['check_in_time'])
            : $attendance->check_in_at;

        $checkOut = array_key_exists('check_out_time', $data)
            ? $this->combine($date, $data['check_out_time'], $checkIn)
            : $attendance->check_out_at;

        if ($checkOut && ! $checkIn) {
            throw new BusinessRuleException(
                'A record cannot have a check-out without a check-in.',
                'CHECK_OUT_WITHOUT_CHECK_IN'
            );
        }

        $attendance->update([
            'check_in_at' => $checkIn,
            'check_out_at' => $checkOut,
            'status' => $data['status'] ?? $this->statusAfterCorrection($attendance, $checkIn, $checkOut),
            // Only where the day was *worked*. `scheduled_location` is what the
            // week expected and is never corrected, or the row would stop
            // showing that the day had been an exception at all.
            'location' => array_key_exists('location', $data) ? $data['location'] : $attendance->location,
            'worked_minutes' => $this->workedMinutes($checkIn, $checkOut, (int) $attendance->break_minutes),
            'correction_reason' => $data['reason'],
            'notes' => $data['notes'] ?? $attendance->notes,
            'source' => Attendance::SOURCE_MANUAL,
            'updated_by' => $actorId,
        ]);

        $this->syncSessionsWithCorrection($attendance, $checkIn, $checkOut);

        return $attendance->fresh(['employee', 'sessions']);
    }

    public function delete(Attendance $attendance): void
    {
        $attendance->delete();
    }

    /**
     * Extra work awaiting a decision, oldest first.
     *
     * @return Collection<int, AttendanceSession>
     */
    public function pendingOvertime(): Collection
    {
        return AttendanceSession::query()
            ->overtime()
            ->where('overtime_status', AttendanceSession::OVERTIME_PENDING)
            ->whereNotNull('check_out_at')
            ->with('attendance.employee')
            ->orderBy('check_in_at')
            ->get();
    }

    /**
     * Rule on a piece of extra work.
     *
     * The hours were a fact the moment they were worked; this records whether
     * they are owed. Who decided and when is kept on the row, and the change
     * also lands in the audit trail through the model's Auditable trait.
     *
     * @param  array<string, mixed>  $data
     */
    public function reviewOvertime(AttendanceSession $session, array $data, int $actorId): AttendanceSession
    {
        if (! $session->is_overtime) {
            throw new BusinessRuleException(
                'This session is part of the normal working day, so there is nothing to approve.',
                'NOT_OVERTIME'
            );
        }

        if ($session->isOpen()) {
            throw new BusinessRuleException(
                'This extra work is still in progress and cannot be reviewed yet.',
                'OVERTIME_IN_PROGRESS'
            );
        }

        $session->update([
            'overtime_status' => $data['status'],
            'review_note' => $data['review_note'] ?? null,
            'reviewed_by' => $actorId,
            'reviewed_at' => now(),
        ]);

        return $session->fresh(['attendance.employee', 'reviewedBy']);
    }

    /**
     * An unsaved row standing in for a day that was never recorded.
     *
     * `id` stays null, which is how callers tell a derived day from a real one.
     */
    private function derivedRow(Employee $employee, CarbonImmutable $date): Attendance
    {
        $scheduled = $this->resolver->scheduledDay($employee, $date);
        $nonWorking = $this->resolver->nonWorkingStatus($employee, $date, $scheduled);

        // A working day already past with nothing recorded is an absence.
        // Today is not an absence yet — the day is not over — and it is not a
        // presence either, so it gets the derived-only `pending`.
        $status = $nonWorking ?? ($date->lt($this->resolver->today())
            ? Attendance::STATUS_ABSENT
            : Attendance::STATUS_PENDING);

        $expectsWork = $nonWorking === null;

        $row = new Attendance([
            'employee_id' => $employee->id,
            'work_date' => $date->toDateString(),
            'status' => $status,
            'work_schedule_id' => $scheduled->schedule?->id,
            'scheduled_start' => $scheduled->startTime(),
            'scheduled_end' => $scheduled->endTime(),
            'location' => $expectsWork ? $scheduled->location() : null,
            'scheduled_location' => $expectsWork ? $scheduled->location() : null,
            'expected_minutes' => $expectsWork ? $scheduled->expectedMinutes() : 0,
            'break_minutes' => $expectsWork ? $scheduled->breakMinutes() : 0,
            'worked_minutes' => 0,
        ]);

        // Nothing was ever recorded, so there are no sittings. Setting the
        // relation empty rather than leaving it unloaded keeps the payload the
        // same shape as a real row: `tasks` present and null, not missing.
        $row->setRelation('sessions', new EloquentCollection);

        return $row;
    }

    /**
     * Push a corrected check-in and check-out down into the day's sessions.
     *
     * The row's two timestamps are the day's *summary*: first arrival and last
     * departure. So a correction moves the first session's start and the last
     * session's end — any sitting in between is left alone, because the
     * correction says nothing about it.
     *
     * Without this the row and its sessions would drift apart, and the next
     * `refreshFromSessions()` would quietly undo the correction.
     */
    private function syncSessionsWithCorrection(Attendance $attendance, mixed $checkIn, mixed $checkOut): void
    {
        $sessions = $attendance->sessions()->get();

        if ($sessions->isEmpty()) {
            // A record that never had a sitting — an absence being turned into
            // a worked day. Give it one.
            if ($checkIn) {
                $attendance->sessions()->create([
                    'sequence' => 1,
                    'check_in_at' => $checkIn,
                    'check_out_at' => $checkOut,
                    'worked_minutes' => $this->workedMinutes($checkIn, $checkOut, (int) $attendance->break_minutes),
                    'is_overtime' => false,
                    'source' => Attendance::SOURCE_MANUAL,
                ]);
            }

            return;
        }

        if (! $checkIn) {
            // The day has been emptied — there is nothing left to have sat for.
            $attendance->sessions()->delete();
            $attendance->forceFill(['worked_minutes' => 0])->save();

            return;
        }

        $first = $sessions->first();
        $last = $sessions->last();

        $first->forceFill(['check_in_at' => $checkIn, 'source' => Attendance::SOURCE_MANUAL]);
        $last->forceFill(['check_out_at' => $checkOut, 'source' => Attendance::SOURCE_MANUAL]);

        foreach ([$first, $last] as $session) {
            // The scheduled break belongs to the working day, not to a return.
            $break = $session->sequence === 1 ? (int) $attendance->break_minutes : 0;
            $session->worked_minutes = $this->workedMinutes($session->check_in_at, $session->check_out_at, $break);
            $session->save();
        }

        $attendance->refreshFromSessions();
    }

    /**
     * What the status becomes once times are corrected.
     *
     * A non-working day keeps its status — correcting the hours of a day off
     * does not turn it into a working day.
     */
    private function statusAfterCorrection(Attendance $attendance, mixed $checkIn, mixed $checkOut): string
    {
        if ($attendance->isNonWorkingDay()) {
            return $attendance->status;
        }

        return match (true) {
            $checkIn && $checkOut => Attendance::STATUS_PRESENT,
            $checkIn && ! $checkOut => Attendance::STATUS_INCOMPLETE,
            default => Attendance::STATUS_ABSENT,
        };
    }

    /**
     * Turn "HH:mm" in the company timezone into a stored UTC timestamp.
     *
     * The conversion at the end is not decoration. Eloquent writes a datetime
     * by formatting its wall-clock digits, so handing it a +03:00 instant would
     * store "09:00" and read it back as 09:00 *UTC* — noon in Damascus. The
     * time must be converted, not merely constructed in the right zone.
     *
     * When an end time lands on or before the start it is read as the small
     * hours of the following day, so a night shift corrects sensibly instead
     * of producing a negative span.
     */
    private function combine(CarbonImmutable $date, ?string $time, mixed $after = null): ?CarbonImmutable
    {
        if (! $time) {
            return null;
        }

        [$hour, $minute] = array_pad(explode(':', $time), 2, '0');

        $moment = $date->setTime((int) $hour, (int) $minute);

        if ($after && $moment->lte($after)) {
            $moment = $moment->addDay();
        }

        return $moment->utc();
    }

    private function workedMinutes(mixed $checkIn, mixed $checkOut, int $breakMinutes): int
    {
        if (! $checkIn || ! $checkOut) {
            return 0;
        }

        return (int) max(0, (int) $checkIn->diffInMinutes($checkOut, absolute: false) - $breakMinutes);
    }
}
