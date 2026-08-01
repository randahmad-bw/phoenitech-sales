<?php

namespace App\Models;

use App\Models\SocialMedia\ContentItem;
use App\Models\SocialMedia\PhotoSession;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Employee model representing a team member.
 */
class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'email',
        'department',
        'job_title',
        'base_salary',
        'annual_leave_allowance',
        'job_description',
        'responsibilities',
        'employment_date',
    ];

    /**
     * Attribute type casting definitions.
     */
    protected function casts(): array
    {
        return [
            'employment_date' => 'date',
            'base_salary' => 'float',
            'annual_leave_allowance' => 'integer',
            'responsibilities' => 'array',
        ];
    }

    /**
     * The user account linked to this employee.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Companies managed by this employee.
     */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    /**
     * Contracts assigned to this employee.
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * Weekly reports submitted by this employee.
     */
    public function weeklyReports(): HasMany
    {
        return $this->hasMany(WeeklyReport::class);
    }

    /**
     * Content items assigned to this employee (design tasks).
     */
    public function assignedItems(): HasMany
    {
        return $this->hasMany(ContentItem::class, 'assigned_to');
    }

    /**
     * Photo sessions where this employee is the photographer.
     */
    public function photoSessions(): HasMany
    {
        return $this->hasMany(PhotoSession::class, 'photographer_id');
    }

    /**
     * Leaves requested / taken by this employee.
     */
    public function leaves(): HasMany
    {
        return $this->hasMany(EmployeeLeave::class);
    }

    /**
     * Overtime hours / days worked by this employee.
     */
    public function overtimes(): HasMany
    {
        return $this->hasMany(EmployeeOvertime::class);
    }

    /**
     * Calculated total approved annual leave days taken.
     */
    public function getApprovedAnnualLeaveDaysAttribute(): float
    {
        return (float) $this->leaves()
            ->where('leave_type', 'annual')
            ->where('status', 'approved')
            ->sum('days_count');
    }

    /**
     * Calculated remaining annual leave balance.
     */
    public function getRemainingLeaveBalanceAttribute(): float
    {
        $allowance = $this->annual_leave_allowance ?? 21;
        return max(0, $allowance - $this->approved_annual_leave_days);
    }

    /**
     * Calculated total approved overtime hours.
     */
    public function getTotalApprovedOvertimeHoursAttribute(): float
    {
        return (float) $this->overtimes()
            ->where('status', 'approved')
            ->sum('hours');
    }

    /**
     * Calculated total approved overtime equivalent days.
     */
    public function getTotalApprovedOvertimeDaysAttribute(): float
    {
        return (float) $this->overtimes()
            ->where('status', 'approved')
            ->sum('days_equivalent');
    }
}


