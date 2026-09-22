<?php

namespace App\Http\Requests;

use App\Application\Support\ScheduleResolver;
use Carbon\CarbonImmutable;

/**
 * Validates and normalises the range for an attendance history request.
 *
 * Accepts either a `period` shortcut (today / week / month) or an explicit
 * from/to pair. The range is resolved in the company timezone so "this month"
 * means the employee's month, not UTC's.
 */
class AttendanceHistoryRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'period' => ['nullable', 'in:today,week,month'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'month' => ['nullable', 'date_format:Y-m'],
        ];
    }

    /**
     * The inclusive range to report on.
     *
     * Precedence: an explicit month, then an explicit from/to, then the period
     * shortcut, then the current month.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function range(ScheduleResolver $resolver): array
    {
        $today = $resolver->today();

        if ($month = $this->input('month')) {
            $start = CarbonImmutable::createFromFormat('Y-m-d', $month.'-01', config('attendance.timezone'))->startOfDay();

            return [$start, $start->endOfMonth()->startOfDay()];
        }

        if ($this->filled('from')) {
            $from = CarbonImmutable::parse($this->input('from'))->startOfDay();
            $to = $this->filled('to')
                ? CarbonImmutable::parse($this->input('to'))->startOfDay()
                : $today;

            return [$from, $to];
        }

        return match ($this->input('period')) {
            'today' => [$today, $today],
            // The displayed week starts where config says, not where Carbon
            // defaults to — see config('attendance.week_start').
            'week' => [$this->weekStart($today), $today],
            default => [$today->startOfMonth(), $today->endOfMonth()->startOfDay()],
        };
    }

    /**
     * The start of the week containing a date, per the configured first day.
     */
    private function weekStart(CarbonImmutable $date): CarbonImmutable
    {
        $firstDay = (int) config('attendance.week_start');
        $daysSinceStart = ($date->dayOfWeek - $firstDay + 7) % 7;

        return $date->subDays($daysSinceStart)->startOfDay();
    }
}
