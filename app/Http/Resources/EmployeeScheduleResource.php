<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One assignment of a schedule to an employee.
 *
 * `is_current` marks the open-ended row. Past assignments are kept and returned
 * deliberately: they are the record of what this person's week used to be, and
 * they are why a correction to an old date still resolves correctly.
 */
class EmployeeScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'work_schedule_id' => $this->work_schedule_id,
            'work_schedule' => $this->whenLoaded('workSchedule', fn () => new WorkScheduleResource($this->workSchedule)),
            'effective_from' => $this->effective_from?->format('Y-m-d'),
            'effective_to' => $this->effective_to?->format('Y-m-d'),
            'is_current' => $this->isCurrent(),
            'notes' => $this->notes,
        ];
    }
}
