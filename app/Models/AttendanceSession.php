<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One sitting within a working day.
 *
 * Session 1 is the day itself. A later session is a deliberate return — the
 * employee had finished, something urgent came up, and they came back. That is
 * what counts as extra work, and it is why overtime is **not** derived from
 * `worked - expected`: staying twenty minutes late on a task inside the normal
 * day is not overtime and must never produce a payable record.
 */
class AttendanceSession extends Model
{
    use Auditable, HasFactory;

    /** Recorded, awaiting management's decision. */
    public const OVERTIME_PENDING = 'pending';

    /** Management agreed the extra work is owed. */
    public const OVERTIME_APPROVED = 'approved';

    /** Management decided it is not owed. */
    public const OVERTIME_REJECTED = 'rejected';

    public const OVERTIME_STATUSES = [
        self::OVERTIME_PENDING,
        self::OVERTIME_APPROVED,
        self::OVERTIME_REJECTED,
    ];

    protected $fillable = [
        'attendance_id',
        'sequence',
        'check_in_at',
        'check_out_at',
        'worked_minutes',
        'is_overtime',
        'overtime_status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'source',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'sequence' => 'integer',
            'worked_minutes' => 'integer',
            'is_overtime' => 'boolean',
        ];
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Started and not yet finished.
     */
    public function isOpen(): bool
    {
        return $this->check_out_at === null;
    }

    /**
     * Extra work that has been recorded but not yet ruled on.
     */
    public function awaitsReview(): bool
    {
        return $this->is_overtime && $this->overtime_status === self::OVERTIME_PENDING;
    }

    /**
     * Minutes this session is owed, which is nothing until it is approved.
     */
    public function approvedOvertimeMinutes(): int
    {
        return $this->is_overtime && $this->overtime_status === self::OVERTIME_APPROVED
            ? $this->worked_minutes
            : 0;
    }

    /**
     * Extra-work sessions only.
     *
     * @param  Builder<AttendanceSession>  $query
     */
    public function scopeOvertime(Builder $query): Builder
    {
        return $query->where('is_overtime', true);
    }
}
