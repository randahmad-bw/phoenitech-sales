<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A company or public holiday.
 */
class HolidayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date?->format('Y-m-d'),
            'name' => $this->name,
            'is_recurring' => (bool) $this->is_recurring,
            'notes' => $this->notes,
        ];
    }
}
