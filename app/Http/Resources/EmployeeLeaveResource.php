<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A leave request and the decision on it.
 */
class EmployeeLeaveResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee_name' => $this->whenLoaded('employee', fn () => $this->employee->name, null),

            'leave_type' => $this->leave_type,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            // Working days only — a request spanning a weekend does not spend
            // leave on days the employee was never due to work.
            'days_count' => (float) $this->days_count,

            'status' => $this->status,
            'is_pending' => $this->isPending(),
            'reason' => $this->reason,
            'notes' => $this->notes,

            // Third argument gives a stable shape: the key is always present,
            // null before a decision, rather than vanishing from the payload.
            'decided_by' => $this->whenLoaded('approver', fn () => $this->approver?->name, null),
            'decided_at' => $this->decided_at?->toISOString(),
            'decision_note' => $this->decision_note,

            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
