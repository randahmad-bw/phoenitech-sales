<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\SocialMedia\ContentItem;
use App\Models\SocialMedia\PhotoSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Employee model representing a team member.
 */
class Employee extends Model
{
    use Auditable, HasFactory;

    /**
     * The departments someone can belong to.
     *
     * A department says what a person *does*; their role says what their
     * account may *reach*. Most departments need no role of their own —
     * design, development, photography and video all sit on `team` — which is
     * exactly why the two are separate fields rather than one.
     *
     * The list is here, in one place, because it used to live in three that
     * disagreed: the employees screen knew nothing of `video`, the attendance
     * screen knew nothing of `dev`, and the API validated neither, so a typo
     * quietly created a sixth department that no filter could ever match.
     */
    public const DEPARTMENTS = [
        'sales',
        'marketing',
        'design',
        'dev',
        'photography',
        'video',
        'management',
    ];

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
        'tracks_attendance',
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
            'tracks_attendance' => 'boolean',
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
     * Employees the attendance module applies to.
     *
     * Management sits outside it by decision, and sales work by field visits
     * with no fixed hours or place. Excluding them here keeps them out of the
     * dashboard, the nightly close and the "needs a schedule" prompt — rather
     * than leaving them looking like people nobody has configured yet.
     *
     * @param  Builder<Employee>  $query
     */
    public function scopeTracksAttendance(Builder $query): Builder
    {
        return $query->where('tracks_attendance', true);
    }

    /**
     * Daily attendance records for this employee.
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Every work-schedule assignment, newest first.
     *
     * Assignments are date-ranged and append-only: changing someone's schedule
     * closes the running row and opens a new one, so this is their schedule
     * history, not just their current setting.
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(EmployeeSchedule::class)->orderByDesc('effective_from');
    }

    /**
     * The open-ended assignment, i.e. the schedule in force from now on.
     *
     * Use EmployeeSchedule::scopeCovering() to resolve the schedule that
     * applied on some *past* date — that is what attendance calculations need.
     */
    public function currentSchedule(): HasOne
    {
        return $this->hasOne(EmployeeSchedule::class)
            ->whereNull('effective_to')
            ->latestOfMany('effective_from');
    }

    /**
     * Approved leave days that have been taken out of the yearly allowance.
     *
     * Which types those are is a property of the type, not a hard-coded name:
     * see LeaveType::deductingKeys().
     */
    public function getApprovedAnnualLeaveDaysAttribute(): float
    {
        return (float) $this->leaves()
            ->whereIn('leave_type', LeaveType::deductingKeys())
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
