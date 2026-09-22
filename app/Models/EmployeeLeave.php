<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A leave request, and the decision on it.
 *
 * A request is a claim; an approval is a judgement. They are deliberately not
 * the same act: an employee submits, and someone holding `leave.approve`
 * decides. Approved leave then shows on the attendance calendar as `leave`
 * rather than an absence, which is exactly why an employee must never be able
 * to approve their own.
 */
class EmployeeLeave extends Model
{
    use Auditable, HasFactory;

    /** Submitted, awaiting a decision. */
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    protected $fillable = [
        'employee_id',
        'leave_type',
        'start_date',
        'end_date',
        'days_count',
        'status',
        'reason',
        'notes',
        'approved_by',
        'decided_at',
        'decision_note',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'days_count' => 'float',
            'decided_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * The kind of leave. Not a constant any more: the catalogue lives in
     * `leave_types` so the company can switch a type off or add one from the
     * settings screen. `leave_type` stores that table's `key`.
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type', 'key');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Whether this request covers a given date.
     */
    public function covers(string $date): bool
    {
        return $this->start_date->toDateString() <= $date
            && $this->end_date->toDateString() >= $date;
    }
}
