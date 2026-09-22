<?php

namespace App\Application\Services;

use App\Application\Support\ScheduledDay;
use App\Application\Support\ScheduleResolver;
use App\Application\Support\TodayAttendance;
use App\Exceptions\BusinessRuleException;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The employee-facing half of attendance: check in, check out, and read back
 * what happened.
 *
 * Two rules govern everything here:
 *
 *  1. **The server owns the clock.** Times come from the company timezone via
 *     ScheduleResolver, never from the request. An employee cannot post a
 *     flattering check-in time because there is nowhere to post one.
 *  2. **The database owns uniqueness.** Every write takes a row lock inside a
 *     transaction, and `unique(employee_id, work_date)` backs it up. Two taps
 *     on a slow connection cannot produce two rows.
 */
class AttendanceService
{
    public function __construct(
        private ScheduleResolver $resolver,
        // Closing the day and ticking off what the day contained are one act
        // for the person doing it, so they are one request here — rather than
        // two the employee can leave half-finished.
        private TaskService $tasks,
        // Management's phone. A mirror of what was just recorded, never a
        // participant in recording it.
        private TelegramNotifier $telegram,
    ) {}

    /**
     * The state of today for one employee, with the buttons already decided.
     */
    public function today(Employee $employee): TodayAttendance
    {
        return $this->stateFor($employee, $this->resolver->today());
    }

    /**
     * The same payload for an arbitrary date.
     *
     * Check-in and check-out answer with the state of *the day they touched*,
     * not of the current calendar day. It is the same thing on an ordinary
     * shift, but a night shift closed at 02:30 belongs to the day it started —
     * and the employee should see the shift they just finished, not tomorrow's
     * empty card.
     */
    public function stateFor(Employee $employee, CarbonImmutable $date): TodayAttendance
    {
        $scheduled = $this->resolver->scheduledDay($employee, $date);
        $nonWorking = $this->resolver->nonWorkingStatus($employee, $date, $scheduled);

        $attendance = $this->rowFor($employee, $date);
        $openRow = $attendance?->isOpen() ?? false;

        $state = match (true) {
            $openRow => TodayAttendance::STATE_WORKING,
            $attendance?->check_out_at !== null => TodayAttendance::STATE_COMPLETED,
            // Checked before day_off: an unconfigured employee is not on
            // holiday, and saying so would send them to the wrong person.
            $scheduled->hasNoSchedule() => TodayAttendance::STATE_NO_SCHEDULE,
            $nonWorking !== null => $nonWorking,
            default => TodayAttendance::STATE_NOT_CHECKED_IN,
        };

        return new TodayAttendance(
            state: $state,
            workDate: $date,
            scheduled: $scheduled,
            attendance: $attendance,
            canCheckIn: $employee->tracks_attendance && $this->mayCheckIn($scheduled, $nonWorking, $attendance),
            canCheckOut: $openRow,
            // The day is already finished, so checking in again would start
            // extra work. The UI says so before the employee taps.
            nextIsOvertime: $attendance !== null
                && ! $openRow
                && $attendance->sessions()->exists(),
            serverTime: $this->resolver->now(),
        );
    }

    /**
     * Record the start of a working day.
     *
     * `$location` is the employee saying where they are working today, for the
     * days that differ from their usual place. Null means "as scheduled" — the
     * common case, and the one the button takes with a single tap.
     *
     * @throws BusinessRuleException
     */
    public function checkIn(Employee $employee, ?int $actorId = null, ?string $notes = null, ?string $location = null): Attendance
    {
        if (! $employee->tracks_attendance) {
            throw new BusinessRuleException(
                'Attendance is not tracked for this employee.',
                'ATTENDANCE_NOT_TRACKED'
            );
        }

        $today = $this->resolver->today();
        $scheduled = $this->resolver->scheduledDay($employee, $today);

        if ($scheduled->hasNoSchedule()) {
            throw new BusinessRuleException(
                'No work schedule has been assigned to this employee yet. Management must assign one before attendance can be recorded.',
                'NO_SCHEDULE_ASSIGNED'
            );
        }

        $nonWorking = $this->resolver->nonWorkingStatus($employee, $today, $scheduled);

        if ($nonWorking !== null && ! config('attendance.allow_check_in_on_day_off')) {
            throw new BusinessRuleException(
                'Today is not a working day for this employee.',
                'NOT_A_WORKING_DAY'
            );
        }

        $attendance = DB::transaction(function () use ($employee, $today, $scheduled, $nonWorking, $actorId, $notes, $location): Attendance {
            // Lock the day's row (if any) before deciding — this is what makes
            // two simultaneous taps resolve to one check-in and one 409.
            $existing = Attendance::where('employee_id', $employee->id)
                ->whereDate('work_date', $today->toDateString())
                ->lockForUpdate()
                ->first();

            // Already mid-shift: there is nothing to start.
            if ($existing?->openSession() !== null) {
                throw new BusinessRuleException(
                    'You are already checked in.',
                    'ALREADY_CHECKED_IN'
                );
            }

            /*
             * A finished day that is being resumed. The employee had checked
             * out, an urgent task arrived, and they came back — so this opens
             * a *new* session rather than refusing.
             *
             * Only a deliberate return counts as extra work. Running late on a
             * task inside session 1 does not, which is why overtime is never
             * derived from `worked - expected`.
             *
             * `$location` is deliberately not applied here. The place belongs
             * to the *day* and is fixed by the first check-in: an evening
             * return cannot retroactively move a morning spent in the office,
             * and the row has one `location` column, not one per sitting. The
             * employee screen therefore hides the picker on a resume rather
             * than offering a control this would ignore.
             */
            if ($existing !== null && $existing->sessions()->exists()) {
                $this->openSession($existing, $actorId, $notes);
                $existing->refreshFromSessions();

                return $existing->fresh(['sessions']);
            }

            // Hours on a non-working day are recorded, but nothing was expected
            // of the employee and — by management decision — nothing is owed.
            $expectsWork = $nonWorking === null;

            $attributes = [
                'employee_id' => $employee->id,
                'work_date' => $today->toDateString(),
                'check_in_at' => now(),
                'check_out_at' => null,
                'status' => $nonWorking ?? Attendance::STATUS_PRESENT,
                'work_schedule_id' => $scheduled->schedule?->id,
                'scheduled_start' => $scheduled->startTime(),
                'scheduled_end' => $scheduled->endTime(),
                // Where the day was worked, and where it was due to be — the
                // pair is what makes a one-off exception readable later.
                'location' => $expectsWork ? ($location ?? $scheduled->location()) : null,
                'scheduled_location' => $expectsWork ? $scheduled->location() : null,
                'expected_minutes' => $expectsWork ? $scheduled->expectedMinutes() : 0,
                'break_minutes' => $expectsWork ? $scheduled->breakMinutes() : 0,
                'worked_minutes' => 0,
                'source' => Attendance::SOURCE_SELF,
                'notes' => $notes,
            ];

            if ($existing) {
                // A row already made by the nightly close (absent) or by
                // management — reuse it rather than fighting the unique index.
                $existing->update($attributes + ['updated_by' => $actorId]);
                $this->openSession($existing, $actorId, $notes);
                $existing->refreshFromSessions();

                return $existing->fresh(['sessions']);
            }

            $attendance = Attendance::create($attributes + ['created_by' => $actorId]);
            $this->openSession($attendance, $actorId, $notes);

            return $attendance->fresh(['sessions']);
        });

        /*
         * Announced after the commit, never inside it. A transaction that is
         * still open has not happened yet, and a message about a check-in that
         * then rolls back is worse than no message at all.
         *
         * More than one session on the row means this was a return to work
         * rather than the start of the day.
         */
        $this->telegram->checkIn($employee, $attendance, $attendance->sessions->count() > 1);

        return $attendance;
    }

    /**
     * Record the end of a working day.
     *
     * @throws BusinessRuleException
     */
    public function checkOut(
        Employee $employee,
        User $actor,
        ?string $notes = null,
        array $completedItemIds = [],
    ): Attendance {
        $actorId = $actor->id;

        $attendance = DB::transaction(function () use ($employee, $actor, $actorId, $notes, $completedItemIds): Attendance {
            $open = $this->openRow($employee);

            if (! $open) {
                // Distinguish "never started" from "already finished" so the
                // employee is told something true rather than something generic.
                $todayRow = $this->rowFor($employee, $this->resolver->today());

                if ($todayRow?->check_out_at !== null) {
                    throw new BusinessRuleException(
                        'You have already checked out for today.',
                        'ALREADY_CHECKED_OUT'
                    );
                }

                throw new BusinessRuleException(
                    'You have not checked in yet.',
                    'NOT_CHECKED_IN'
                );
            }

            // An open row older than yesterday means a check-out was forgotten
            // days ago. Closing it now would record an impossible shift, so it
            // is left for management to correct — with a reason, on the record.
            if ($open->work_date->lt($this->resolver->today()->subDay())) {
                throw new BusinessRuleException(
                    'An unfinished attendance record from '.$open->work_date->format('Y-m-d').' is blocking this. Management must correct it first.',
                    'STALE_OPEN_ATTENDANCE'
                );
            }

            $session = $open->openSession();

            if (! $session) {
                throw new BusinessRuleException(
                    'You have not checked in yet.',
                    'NOT_CHECKED_IN'
                );
            }

            $checkOutAt = now();

            /*
             * Ticked inside the same transaction as the punch. If closing the
             * day fails — a stale open row from last week, say — the lines must
             * not be left marked finished by a check-out that never happened.
             */
            $ticked = $this->tasks->completeItemsFor($employee, $completedItemIds, $actor);

            /*
             * The scheduled break is deducted once, from the working day
             * itself — not from every sitting. Someone who returns in the
             * evening should not lose a lunch hour they already took.
             */
            $break = $session->sequence === 1 ? (int) $open->break_minutes : 0;

            $session->update([
                'check_out_at' => $checkOutAt,
                'worked_minutes' => $this->workedMinutes($session->check_in_at, $checkOutAt, $break),
                'overtime_status' => $session->is_overtime ? AttendanceSession::OVERTIME_PENDING : null,
                'notes' => $this->accountOfTheDay($ticked->pluck('title')->all(), $notes) ?? $session->notes,
            ]);

            $expectsWork = ! $open->isNonWorkingDay();

            $open->update([
                'status' => $expectsWork ? Attendance::STATUS_PRESENT : $open->status,
                'updated_by' => $actorId,
            ]);

            $open->refreshFromSessions();

            return $open->fresh(['sessions']);
        });

        $this->telegram->checkOut($employee, $attendance);

        return $attendance;
    }

    /**
     * An employee's records over an inclusive date range, newest first.
     *
     * @return Collection<int, Attendance>
     */
    public function history(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Attendance::where('employee_id', $employee->id)
            ->betweenDates($from->toDateString(), $to->toDateString())
            ->orderByDesc('work_date')
            ->get();
    }

    /**
     * Totals for a range: the numbers the employee's summary strip shows.
     *
     * Deliberately free of judgments — days present, days absent, hours worked.
     * No lateness, no overtime.
     *
     * @return array<string, int>
     */
    public function summary(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->history($employee, $from, $to);

        return [
            'present_days' => $rows->where('status', Attendance::STATUS_PRESENT)->count(),
            'absent_days' => $rows->where('status', Attendance::STATUS_ABSENT)->count(),
            'incomplete_days' => $rows->where('status', Attendance::STATUS_INCOMPLETE)->count(),
            'leave_days' => $rows->where('status', Attendance::STATUS_LEAVE)->count(),
            'worked_minutes' => (int) $rows->sum('worked_minutes'),
            'expected_minutes' => (int) $rows->sum('expected_minutes'),
        ];
    }

    /**
     * Whether the check-in button should be live.
     */
    private function mayCheckIn(ScheduledDay $scheduled, ?string $nonWorking, ?Attendance $attendance): bool
    {
        if ($scheduled->hasNoSchedule()) {
            return false;
        }

        // Mid-shift: there is nothing to start.
        if ($attendance?->isOpen()) {
            return false;
        }

        /*
         * A finished day is NOT the end of it. An urgent task can arrive in the
         * evening, and checking in again opens a new session recorded as extra
         * work — so the button stays live.
         */
        return $nonWorking === null || (bool) config('attendance.allow_check_in_on_day_off');
    }

    /**
     * The employee's row for a given date, if one exists.
     */
    private function rowFor(Employee $employee, CarbonImmutable $date): ?Attendance
    {
        return Attendance::where('employee_id', $employee->id)
            ->whereDate('work_date', $date->toDateString())
            ->first();
    }

    /**
     * The most recent row that was started and never finished.
     *
     * Found by open-ness rather than by today's date, so a shift that runs past
     * midnight can still be closed by the person who started it.
     */
    private function openRow(Employee $employee): ?Attendance
    {
        return Attendance::where('employee_id', $employee->id)
            ->whereHas('sessions', fn ($q) => $q->whereNull('check_out_at'))
            ->orderByDesc('work_date')
            ->lockForUpdate()
            ->first();
    }

    /**
     * Start a new sitting on a day.
     *
     * Sequence 1 is the working day; anything after it is a return, and is
     * marked as extra work the moment it opens. The flag is stored rather than
     * derived so management can correct it — someone who checks out by mistake
     * and straight back in has not worked extra.
     */
    private function openSession(Attendance $attendance, ?int $actorId, ?string $notes): AttendanceSession
    {
        $sequence = (int) $attendance->sessions()->max('sequence') + 1;

        return $attendance->sessions()->create([
            'sequence' => $sequence,
            'check_in_at' => now(),
            'check_out_at' => null,
            'worked_minutes' => 0,
            'is_overtime' => $sequence > 1,
            // Set when the session closes and its duration is known.
            'overtime_status' => null,
            'source' => Attendance::SOURCE_SELF,
            'notes' => $notes,
        ]);
    }

    /**
     * Worked minutes between two instants, less the scheduled break.
     *
     * Never negative: a correction that puts check-out before check-in is a
     * data problem, not a negative working day.
     */
    /**
     * What the day says about itself: the lines that were ticked, then anything
     * typed beside them.
     *
     * Composed here rather than in the browser so that the sentence management
     * reads beside the hours — and exports, and searches — is the same sentence
     * however the day was closed.
     *
     * @param  array<int, string>  $ticked
     */
    private function accountOfTheDay(array $ticked, ?string $notes): ?string
    {
        $lines = array_map(fn (string $title): string => '• '.$title, $ticked);

        if (filled($notes)) {
            $lines[] = trim((string) $notes);
        }

        return $lines === [] ? null : implode("\n", $lines);
    }

    private function workedMinutes(?CarbonInterface $checkIn, ?CarbonInterface $checkOut, int $breakMinutes): int
    {
        if (! $checkIn || ! $checkOut) {
            return 0;
        }

        $minutes = (int) $checkIn->diffInMinutes($checkOut, absolute: false);

        return (int) max(0, $minutes - $breakMinutes);
    }
}
