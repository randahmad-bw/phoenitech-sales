<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One employee's attendance for one day.
 *
 * The row records facts — when they checked in, when they checked out, the
 * hours between — and passes no judgment: there is no lateness, early-leave or
 * overtime figure in V1. The schedule that was in force is snapshotted onto the
 * row, so any of those rules can be introduced later and applied to historical
 * data, and so editing a schedule template can never rewrite the past.
 *
 * Corrections by management go through the audit trail (Auditable) — the old
 * value is never silently overwritten.
 */
class Attendance extends Model
{
    use Auditable, HasFactory;

    /** Checked in and out on a scheduled working day. */
    public const STATUS_PRESENT = 'present';

    /** A scheduled working day that came and went with no check-in. */
    public const STATUS_ABSENT = 'absent';

    /** Checked in but never checked out, and the day is over. */
    public const STATUS_INCOMPLETE = 'incomplete';

    /** Not a working day for this employee's schedule. */
    public const STATUS_DAY_OFF = 'day_off';

    /** A company or public holiday. */
    public const STATUS_HOLIDAY = 'holiday';

    /** Covered by an approved leave request. */
    public const STATUS_LEAVE = 'leave';

    /**
     * A working day that has not happened yet — today, before the check-in.
     *
     * Derived only. It is never written to the database and is deliberately
     * absent from self::STATUSES, so it cannot be set through a manual entry
     * or a correction. It exists so the management grid can say "not checked
     * in yet" instead of pretending the day is already an absence, or a
     * presence with no arrival time.
     */
    public const STATUS_PENDING = 'pending';

    /**
     * The statuses a stored row may carry.
     */
    public const STATUSES = [
        self::STATUS_PRESENT,
        self::STATUS_ABSENT,
        self::STATUS_INCOMPLETE,
        self::STATUS_DAY_OFF,
        self::STATUS_HOLIDAY,
        self::STATUS_LEAVE,
    ];

    /** The employee pressed the button themselves. */
    public const SOURCE_SELF = 'self';

    /** Entered or corrected by management. */
    public const SOURCE_MANUAL = 'manual';

    /** Written by the attendance:close-day command. */
    public const SOURCE_SYSTEM = 'system';

    public const SOURCES = [
        self::SOURCE_SELF,
        self::SOURCE_MANUAL,
        self::SOURCE_SYSTEM,
    ];

    /**
     * Statuses that mean "no work was expected of this person today".
     *
     * Hours may still be recorded against such a day — people do come in on
     * their day off — but the day is never counted as an absence, and by
     * management decision it earns no overtime.
     */
    public const NON_WORKING_STATUSES = [
        self::STATUS_DAY_OFF,
        self::STATUS_HOLIDAY,
        self::STATUS_LEAVE,
    ];

    /** Worked from the office. */
    public const LOCATION_OFFICE = 'office';

    /** Worked from home. */
    public const LOCATION_REMOTE = 'remote';

    public const LOCATIONS = [
        self::LOCATION_OFFICE,
        self::LOCATION_REMOTE,
    ];

    /**
     * The day was worked somewhere other than the schedule expected.
     *
     * Both columns are snapshots taken when the day was recorded, so this stays
     * true to that day even after the employee's week is edited.
     */
    public function isLocationException(): bool
    {
        return $this->location !== null
            && $this->scheduled_location !== null
            && $this->location !== $this->scheduled_location;
    }

    protected $fillable = [
        'employee_id',
        'work_date',
        'check_in_at',
        'check_out_at',
        'status',
        'work_schedule_id',
        'scheduled_start',
        'scheduled_end',
        'location',
        'scheduled_location',
        'expected_minutes',
        'break_minutes',
        'worked_minutes',
        'source',
        'correction_reason',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'expected_minutes' => 'integer',
            'break_minutes' => 'integer',
            'worked_minutes' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * The sittings this day was worked in, earliest first.
     *
     * Session 1 is the working day; anything after it is a deliberate return
     * and counts as extra work. The columns on this row (check_in_at,
     * check_out_at, worked_minutes) stay as the day's summary so every
     * existing screen and report keeps working.
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class)->orderBy('sequence');
    }

    /**
     * The sitting currently in progress, if any.
     */
    public function openSession(): ?AttendanceSession
    {
        return $this->sessions()->whereNull('check_out_at')->orderByDesc('sequence')->first();
    }

    /**
     * Recompute the day's summary from its sessions.
     *
     * The summary columns are denormalised on purpose: the grid, the exports
     * and the nightly close all read them, and none of them should have to
     * know that a day can have more than one sitting.
     */
    public function refreshFromSessions(): void
    {
        $sessions = $this->sessions()->get();

        if ($sessions->isEmpty()) {
            return;
        }

        $this->forceFill([
            'check_in_at' => $sessions->first()->check_in_at,
            // The last session to have finished — an open one has no end yet.
            'check_out_at' => $sessions->every(fn (AttendanceSession $s): bool => ! $s->isOpen())
                ? $sessions->last()->check_out_at
                : null,
            'worked_minutes' => (int) $sessions->sum('worked_minutes'),
        ])->save();
    }

    /**
     * Extra-work minutes recorded on this day, whatever their review status.
     */
    public function overtimeMinutes(): int
    {
        return (int) $this->sessions->where('is_overtime', true)->sum('worked_minutes');
    }

    /**
     * Extra-work minutes management has agreed are owed.
     */
    public function approvedOvertimeMinutes(): int
    {
        return (int) $this->sessions
            ->where('is_overtime', true)
            ->where('overtime_status', AttendanceSession::OVERTIME_APPROVED)
            ->sum('worked_minutes');
    }

    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Checked in with no check-out yet — the employee is mid-shift.
     *
     * "Currently working" is deliberately not a stored status: a row in this
     * state on today's date means someone is at work, while the same row on an
     * older date means they forgot to check out. One flag cannot mean both, so
     * the distinction is drawn from the date instead.
     */
    public function isOpen(): bool
    {
        return $this->check_in_at !== null && $this->check_out_at === null;
    }

    /**
     * No work was expected on this day (day off, holiday or approved leave).
     */
    public function isNonWorkingDay(): bool
    {
        return in_array($this->status, self::NON_WORKING_STATUSES, true);
    }

    /**
     * Rows for a single calendar day.
     *
     * @param  Builder<Attendance>  $query
     */
    public function scopeOnDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('work_date', $date);
    }

    /**
     * Rows falling inside an inclusive date range.
     *
     * Compared with whereDate rather than whereBetween: SQLite stores a `date`
     * column as whatever string it is given, so a row written as
     * "2026-09-17 00:00:00" would fall outside a plain string range ending at
     * "2026-09-17". whereDate normalises both sides on every driver.
     *
     * @param  Builder<Attendance>  $query
     */
    public function scopeBetweenDates(Builder $query, string $from, string $to): Builder
    {
        return $query->whereDate('work_date', '>=', $from)
            ->whereDate('work_date', '<=', $to);
    }
}
