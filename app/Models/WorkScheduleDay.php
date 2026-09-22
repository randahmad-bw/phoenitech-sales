<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One working day inside a schedule.
 *
 * Weekdays use Carbon's numbering (0 = Sunday … 6 = Saturday) so a date can be
 * matched without conversion. There is no global "week start" — a weekday with
 * no row simply is not a working day, which is how one employee works
 * Saturday–Wednesday while another works Sunday–Thursday.
 */
class WorkScheduleDay extends Model
{
    use HasFactory;

    public const SUNDAY = 0;

    public const MONDAY = 1;

    public const TUESDAY = 2;

    public const WEDNESDAY = 3;

    public const THURSDAY = 4;

    public const FRIDAY = 5;

    public const SATURDAY = 6;

    /** Worked from the company office. */
    public const LOCATION_OFFICE = 'office';

    /** Worked remotely — from home or online. */
    public const LOCATION_REMOTE = 'remote';

    public const LOCATIONS = [
        self::LOCATION_OFFICE,
        self::LOCATION_REMOTE,
    ];

    protected $fillable = [
        'work_schedule_id',
        'weekday',
        'sort_order',
        'location',
        'start_time',
        'end_time',
        'break_minutes',
        'expected_minutes',
    ];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'sort_order' => 'integer',
            'break_minutes' => 'integer',
            'expected_minutes' => 'integer',
        ];
    }

    /**
     * Keep expected_minutes derived from the times, never hand-entered.
     */
    protected static function booted(): void
    {
        static::saving(function (self $day): void {
            $day->expected_minutes = $day->calculateExpectedMinutes();
        });
    }

    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class);
    }

    /**
     * Paid minutes this day is expected to produce: the span minus the break.
     */
    public function calculateExpectedMinutes(): int
    {
        // An open-ended day expects nothing: the person starts at a set hour
        // and leaves when the work is done. There is no figure to measure the
        // day against, which is fine — the module records hours, it does not
        // grade them.
        if (! $this->start_time || $this->isOpenEnded()) {
            return 0;
        }

        return (int) max(0, $this->spanMinutes() - (int) $this->break_minutes);
    }

    /**
     * No scheduled finish — the employee checks out when the work is done.
     */
    public function isOpenEnded(): bool
    {
        return $this->end_time === null;
    }

    public function isRemote(): bool
    {
        return $this->location === self::LOCATION_REMOTE;
    }

    /**
     * Minutes between start_time and end_time.
     *
     * An end time at or before the start time is read as crossing midnight
     * (a 22:00–06:00 night shift), so the span gains 24 hours rather than
     * going negative.
     */
    public function spanMinutes(): int
    {
        $start = CarbonImmutable::parse('2000-01-01 '.$this->start_time);
        $end = CarbonImmutable::parse('2000-01-01 '.$this->end_time);

        if ($end <= $start) {
            $end = $end->addDay();
        }

        return (int) $start->diffInMinutes($end);
    }
}
