<?php

namespace App\Application\Services;

use App\Models\Employee;
use App\Models\EmployeeOvertime;
use Illuminate\Database\Eloquent\Collection;

class EmployeeOvertimeService
{
    /**
     * Get overtime records for a specific employee.
     */
    public function listByEmployee(int $employeeId): Collection
    {
        return EmployeeOvertime::where('employee_id', $employeeId)
            ->orderBy('overtime_date', 'desc')
            ->get();
    }

    /**
     * Store a new overtime record.
     */
    public function createOvertime(int $employeeId, array $data): EmployeeOvertime
    {
        $employee = Employee::findOrFail($employeeId);

        $hours = (float) ($data['hours'] ?? 0);
        $multiplier = (float) ($data['rate_multiplier'] ?? 1.5);

        if (empty($data['days_equivalent'])) {
            // Standard workday = 8 hours
            $data['days_equivalent'] = round(($hours * $multiplier) / 8, 2);
        }

        $data['employee_id'] = $employee->id;
        $data['status'] = $data['status'] ?? 'approved';

        return EmployeeOvertime::create($data);
    }

    /**
     * Update overtime record.
     */
    public function updateOvertime(int $overtimeId, array $data): EmployeeOvertime
    {
        $overtime = EmployeeOvertime::findOrFail($overtimeId);

        if (isset($data['hours']) || isset($data['rate_multiplier'])) {
            $hours = (float) ($data['hours'] ?? $overtime->hours);
            $multiplier = (float) ($data['rate_multiplier'] ?? $overtime->rate_multiplier);
            if (empty($data['days_equivalent'])) {
                $data['days_equivalent'] = round(($hours * $multiplier) / 8, 2);
            }
        }

        $overtime->update($data);

        return $overtime->fresh();
    }

    /**
     * Delete overtime record.
     */
    public function deleteOvertime(int $overtimeId): bool
    {
        $overtime = EmployeeOvertime::findOrFail($overtimeId);

        return (bool) $overtime->delete();
    }

    /**
     * Get aggregated overtime summary for an employee.
     */
    public function getSummary(int $employeeId): array
    {
        $employee = Employee::with('overtimes')->findOrFail($employeeId);

        $approvedOvertimes = $employee->overtimes->where('status', 'approved');

        $totalHours = (float) $approvedOvertimes->sum('hours');
        $totalDaysEquivalent = (float) $approvedOvertimes->sum('days_equivalent');

        $workdayHours = (float) $approvedOvertimes->where('overtime_type', 'workday')->sum('hours');
        $weekendHours = (float) $approvedOvertimes->where('overtime_type', 'weekend')->sum('hours');
        $holidayHours = (float) $approvedOvertimes->where('overtime_type', 'holiday')->sum('hours');

        return [
            'total_hours' => round($totalHours, 2),
            'total_days_equivalent' => round($totalDaysEquivalent, 2),
            'workday_hours' => round($workdayHours, 2),
            'weekend_hours' => round($weekendHours, 2),
            'holiday_hours' => round($holidayHours, 2),
            'total_overtimes_count' => $employee->overtimes->count(),
            'pending_count' => $employee->overtimes->where('status', 'pending')->count(),
        ];
    }
}
