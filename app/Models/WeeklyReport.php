<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WeeklyReport model representing a weekly sales report.
 */
class WeeklyReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'week_start_date',
        'kpis',
        'pipeline',
        'next_plan',
        'notes',
    ];

    /**
     * Attribute type casting definitions.
     */
    protected function casts(): array
    {
        return [
            'week_start_date' => 'date',
            'kpis' => 'array',
            'pipeline' => 'array',
            'next_plan' => 'array',
        ];
    }

    /**
     * The employee who submitted this report.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
