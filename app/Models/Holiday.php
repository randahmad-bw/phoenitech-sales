<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A company or public holiday — a day nobody is expected to work.
 *
 * A holiday is never an absence: attendance:close-day skips these dates and
 * the reader renders them as status `holiday`.
 */
class Holiday extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'date',
        'name',
        'is_recurring',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_recurring' => 'boolean',
        ];
    }

    /**
     * The audit trail labels a holiday by its name rather than its id.
     */
    protected string $auditLabelAttribute = 'name';
}
