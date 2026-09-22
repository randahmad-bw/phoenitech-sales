<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The assignment of a work schedule to an employee over a date range.
 *
 * Schedules are never swapped in place: the running assignment is closed
 * (effective_to set to the day before) and a new row is opened. That is what
 * keeps "which schedule was this person on last March?" answerable, and it
 * makes a temporary schedule change just an assignment with both ends set.
 */
class EmployeeSchedule extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'employee_id',
        'work_schedule_id',
        'effective_from',
        'effective_to',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class);
    }

    /**
     * Assignments covering a given date — started on or before it, and either
     * still open or not yet ended.
     *
     * @param  Builder<EmployeeSchedule>  $query
     */
    public function scopeCovering(Builder $query, string $date): Builder
    {
        return $query->whereDate('effective_from', '<=', $date)
            ->where(function (Builder $q) use ($date): void {
                $q->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date);
            });
    }

    /**
     * Whether this assignment is still open-ended.
     */
    public function isCurrent(): bool
    {
        return $this->effective_to === null;
    }
}
