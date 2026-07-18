<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for WeeklyReport model serialization.
 */
class WeeklyReportResource extends JsonResource
{
    /**
     * Transform weekly report model into API response array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'week_start_date' => $this->week_start_date->format('Y-m-d'),
            'kpis' => $this->kpis,
            'pipeline' => $this->pipeline,
            'next_plan' => $this->next_plan,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
