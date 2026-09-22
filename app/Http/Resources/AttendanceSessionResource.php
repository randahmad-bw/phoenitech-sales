<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One sitting within a working day.
 *
 * Times are rendered in the company timezone; the client formats them for
 * display. `is_overtime` marks a sitting that began after the day had already
 * been finished — a deliberate return, not a shift that ran long.
 */
class AttendanceSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $timezone = config('attendance.timezone');

        return [
            'id' => $this->id,
            'sequence' => (int) $this->sequence,

            'check_in_at' => $this->check_in_at?->timezone($timezone)->toIso8601String(),
            'check_out_at' => $this->check_out_at?->timezone($timezone)->toIso8601String(),
            'check_in_time' => $this->check_in_at?->timezone($timezone)->format('H:i'),
            'check_out_time' => $this->check_out_at?->timezone($timezone)->format('H:i'),

            'worked_minutes' => (int) $this->worked_minutes,
            'is_open' => $this->isOpen(),

            'is_overtime' => (bool) $this->is_overtime,
            'overtime_status' => $this->overtime_status,
            'reviewed_by' => $this->whenLoaded('reviewedBy', fn () => $this->reviewedBy?->name),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'review_note' => $this->review_note,

            'source' => $this->source,
            'notes' => $this->notes,
        ];
    }
}
