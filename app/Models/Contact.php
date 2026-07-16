<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Contact model representing a contact person at a company.
 */
class Contact extends Model
{
    use HasFactory;
    protected $fillable = [
        'company_id',
        'name',
        'position',
        'mobile',
        'notes',
    ];

    /**
     * The company this contact belongs to.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
