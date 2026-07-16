<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Company model representing a client organization.
 */
class Company extends Model
{
    use HasFactory;
    protected $fillable = [
        'employee_id',
        'name',
        'activity',
        'address',
        'notes',
    ];

    /**
     * The employee managing this company.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Contact persons belonging to this company.
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * Contracts associated with this company.
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }
}
