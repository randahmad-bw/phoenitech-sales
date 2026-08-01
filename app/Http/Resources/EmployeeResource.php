<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for Employee model serialization.
 */
class EmployeeResource extends JsonResource
{
    /**
     * Transform employee model into API response array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'department' => $this->department ?? 'design',
            'job_title' => $this->job_title,
            'base_salary' => $this->base_salary ? (float) $this->base_salary : null,
            'annual_leave_allowance' => $this->annual_leave_allowance ?? 21,
            'job_description' => $this->job_description,
            'responsibilities' => $this->responsibilities ?? [],
            'employment_date' => $this->employment_date?->format('Y-m-d'),
            'companies_count' => $this->whenCounted('companies'),
            'contracts_count' => $this->whenCounted('contracts'),
            'leaves_count' => $this->whenCounted('leaves'),
            'overtimes_count' => $this->whenCounted('overtimes'),
            'approved_leaves_days' => $this->approved_leaves_days ?? $this->approved_annual_leave_days,
            'approved_overtime_hours' => $this->approved_overtime_hours ?? $this->total_approved_overtime_hours,
            'approved_overtime_days' => $this->approved_overtime_days ?? $this->total_approved_overtime_days,
            'remaining_leave_balance' => $this->remaining_leave_balance,
            'leaves' => $this->whenLoaded('leaves'),
            'overtimes' => $this->whenLoaded('overtimes'),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
