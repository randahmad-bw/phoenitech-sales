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
}

