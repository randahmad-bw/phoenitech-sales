<?php

namespace App\Http\Resources;

use App\Models\WorkScheduleDay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A schedule template with its working days.
 *
 * Days are returned in the order the company reads a week
 * (config('attendance.week_start'), Saturday by default) rather than in Carbon's
 * Sunday-first numbering — the stored index is an implementation detail, the
 * displayed order is what the schedule editor needs.
 */
class WorkScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_active' => (bool) $this->is_active,
            'notes' => $this->notes,
            'assignments_count' => $this->whenCounted('assignments'),
            'days' => $this->whenLoaded('days', fn () => $this->orderForDisplay()),
            'working_days_count' => $this->whenLoaded('days', fn () => $this->days->count()),
            'weekly_minutes' => $this->whenLoaded('days', fn () => (int) $this->days->sum('expected_minutes')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    /**
     * Day rows ordered from the configured first day of the week.
     */
    private function orderForDisplay(): array
    {
        $firstDay = (int) config('attendance.week_start');

        return $this->days
            ->sortBy(fn (WorkScheduleDay $day): int => ($day->weekday - $firstDay + 7) % 7)
            ->values()
            ->map(fn (WorkScheduleDay $day): array => [
                'id' => $day->id,
                'weekday' => $day->weekday,
                'sort_order' => $day->sort_order,
                'location' => $day->location,
                'start_time' => substr((string) $day->start_time, 0, 5),
                'end_time' => $day->end_time ? substr((string) $day->end_time, 0, 5) : null,
                'is_open_ended' => $day->isOpenEnded(),
                'break_minutes' => $day->break_minutes,
                'expected_minutes' => $day->expected_minutes,
            ])
            ->all();
    }
}
