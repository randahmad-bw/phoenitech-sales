<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A work schedule template ("Office 09:00-17:00", "Evening shift").
 *
 * The template holds no working days of its own — those are WorkScheduleDay
 * rows, and a weekday without a row is not a working day. Employees attach to
 * a template through EmployeeSchedule, never directly.
 */
class WorkSchedule extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'name',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * The working days that make up this schedule.
     */
    public function days(): HasMany
    {
        return $this->hasMany(WorkScheduleDay::class)
            ->orderBy('weekday')
            ->orderBy('sort_order');
    }

    /**
     * Every assignment of this schedule to an employee, past and present.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeSchedule::class);
    }

    /**
     * Attendance rows that were recorded against this schedule.
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * The day definition for a given weekday, or null when it is not a working day.
     *
     * Reads the already-loaded `days` relation when present so callers looping
     * over a date range do not issue a query per day.
     */
    public function dayFor(int $weekday): ?WorkScheduleDay
    {
        return $this->days->firstWhere('weekday', $weekday);
    }
}
