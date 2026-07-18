<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Employee model representing a sales team member.
 */
class Employee extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'email',
        'employment_date',
    ];

    /**
     * Attribute type casting definitions.
     */
    protected function casts(): array
    {
        return [
            'employment_date' => 'date',
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
}
