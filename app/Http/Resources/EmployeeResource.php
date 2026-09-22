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
            // No `?? 'design'` fallback: it meant anyone without a department
            // was reported as a designer, and the frontend repeated the same
            // mistake on top of it. Unset is a real state and says so.
            'department' => $this->department,
            'job_title' => $this->job_title,
            'base_salary' => $this->base_salary ? (float) $this->base_salary : null,
            'annual_leave_allowance' => $this->annual_leave_allowance ?? 21,
            'job_description' => $this->job_description,
            'responsibilities' => $this->responsibilities ?? [],
            'employment_date' => $this->employment_date?->format('Y-m-d'),
            // Whether the attendance module applies to this person at all.
            // Management and sales sit outside it, and the UI hides the
            // attendance screen from them entirely rather than showing a card
            // they can never use.
            'tracks_attendance' => (bool) $this->tracks_attendance,
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

            /*
             * The login this person signs in with.
             *
             * Every employee is a user; a few users are not employees. The two
             * used to be listed on separate screens with nothing stating how
             * they related, which is what made the system hard to reason about.
             * Carrying the account here lets one screen show the whole person.
             *
             * Gated on `users.view`: someone who may not see accounts should
             * not learn a colleague's role or login state from this endpoint
             * either.
             */
            'account' => $this->when(
                $request->user()?->can('users.view') && $this->relationLoaded('user'),
                fn () => $this->user ? [
                    'id' => $this->user->id,
                    'email' => $this->user->email,
                    'username' => $this->user->username,
                    'is_active' => (bool) $this->user->is_active,
                    'roles' => $this->user->roles->pluck('name'),
                    'last_login_at' => $this->user->last_login_at?->toISOString(),
                ] : null,
            ),
        ];
    }
}
