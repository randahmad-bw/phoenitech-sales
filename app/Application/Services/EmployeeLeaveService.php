<?php

namespace App\Application\Services;

use App\Models\Employee;
use App\Models\EmployeeLeave;
use Illuminate\Database\Eloquent\Collection;

class EmployeeLeaveService
{
    /**
     * Get leaves for a specific employee.
     */
    public function listByEmployee(int $employeeId): Collection
    {
        return EmployeeLeave::where('employee_id', $employeeId)
            ->orderBy('start_date', 'desc')
            ->get();
    }

    /**
     * Store a new leave record.
     */
    public function createLeave(int $employeeId, array $data): EmployeeLeave
    {
        $employee = Employee::findOrFail($employeeId);

        // Auto calculate days count if not explicitly set
        if (empty($data['days_count']) && !empty($data['start_date']) && !empty($data['end_date'])) {
            $start = \Carbon\Carbon::parse($data['start_date']);
            $end = \Carbon\Carbon::parse($data['end_date']);
            $data['days_count'] = max(1, $start->diffInDays($end) + 1);
        }

        $data['employee_id'] = $employee->id;
        $data['status'] = $data['status'] ?? 'approved';

        return EmployeeLeave::create($data);
    }

    /**
     * Update leave status or details.
     */
    public function updateLeave(int $leaveId, array $data): EmployeeLeave
    {
        $leave = EmployeeLeave::findOrFail($leaveId);
        $leave->update($data);
        return $leave->fresh();
    }

    /**
     * Delete leave record.
     */
    public function deleteLeave(int $leaveId): bool
    {
        $leave = EmployeeLeave::findOrFail($leaveId);
        return (bool) $leave->delete();
    }

    /**
     * Get aggregated leave summary for an employee.
     */
    public function getSummary(int $employeeId): array
    {
        $employee = Employee::with('leaves')->findOrFail($employeeId);
        $allowance = $employee->annual_leave_allowance ?? 21;

        $approvedAnnual = (float) $employee->leaves
            ->where('leave_type', 'annual')
            ->where('status', 'approved')
            ->sum('days_count');

        $approvedSick = (float) $employee->leaves
            ->where('leave_type', 'sick')
            ->where('status', 'approved')
            ->sum('days_count');

        $approvedUnpaid = (float) $employee->leaves
            ->where('leave_type', 'unpaid')
            ->where('status', 'approved')
            ->sum('days_count');

        $approvedEmergency = (float) $employee->leaves
            ->where('leave_type', 'emergency')
            ->where('status', 'approved')
            ->sum('days_count');

        $pendingLeaves = $employee->leaves->where('status', 'pending')->count();

        return [
            'annual_allowance' => $allowance,
            'annual_used' => $approvedAnnual,
            'annual_remaining' => max(0, $allowance - $approvedAnnual),
            'sick_days' => $approvedSick,
            'unpaid_days' => $approvedUnpaid,
            'emergency_days' => $approvedEmergency,
            'total_leaves_count' => $employee->leaves->count(),
            'pending_count' => $pendingLeaves,
        ];
    }
}
