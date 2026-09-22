<?php

namespace App\Application\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Holiday;
use Illuminate\Database\Eloquent\Collection;

/**
 * Company and public holidays.
 *
 * Small, deliberately: a holiday is a date and a name. Matching (including the
 * month-and-day match for recurring entries) lives in ScheduleResolver, where
 * every reader of a day already goes.
 */
class HolidayService
{
    /**
     * @return Collection<int, Holiday>
     */
    public function list(?int $year = null): Collection
    {
        return Holiday::query()
            ->when($year, fn ($q, $y) => $q->where(function ($q) use ($y): void {
                $q->whereYear('date', $y)->orWhere('is_recurring', true);
            }))
            ->orderBy('date')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Holiday
    {
        $this->guardDuplicate($data['date']);

        return Holiday::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Holiday $holiday, array $data): Holiday
    {
        if (isset($data['date']) && $data['date'] !== $holiday->date->format('Y-m-d')) {
            $this->guardDuplicate($data['date']);
        }

        $holiday->update($data);

        return $holiday->fresh();
    }

    public function delete(Holiday $holiday): void
    {
        $holiday->delete();
    }

    /**
     * The date column is unique; catching it here turns a 500 into a 409 with
     * a message that says which date is already taken.
     */
    private function guardDuplicate(string $date): void
    {
        if (Holiday::whereDate('date', $date)->exists()) {
            throw new BusinessRuleException(
                'A holiday is already recorded for '.$date.'.',
                'HOLIDAY_ALREADY_EXISTS'
            );
        }
    }
}
