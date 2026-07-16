<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Service model representing a type of service offered.
 * Stores bilingual names for AR/EN support.
 */
class Service extends Model
{
    use HasFactory;
    protected $fillable = [
        'name_ar',
        'name_en',
        'is_active',
    ];

    /**
     * Attribute type casting definitions.
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Contracts using this service type.
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }
}
