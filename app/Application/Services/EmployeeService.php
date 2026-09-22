<?php

namespace App\Application\Services;

use App\Helpers\CurrencyHelper;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\EmployeeOvertime;
use App\Models\LeaveType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Handles employee CRUD operations, job details, and comprehensive statistics.
 */
class EmployeeService
{
    /**
     * Retrieve paginated employees with optional search filter and relation counts.
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = Employee::with(['user' => fn ($q) => $q->with('roles:id,name')])
            ->withCount(['companies', 'contracts', 'leaves', 'overtimes'])
            // Whichever types the catalogue marks as spending the allowance -
            // not the literal word `annual`, which stopped being the only one
            // the moment the types became editable.
            ->withSum(['leaves as approved_leaves_days' => function ($q) {
                $q->where('status', 'approved')->whereIn('leave_type', LeaveType::deductingKeys());
            }], 'days_count')
            ->withSum(['overtimes as approved_overtime_hours' => function ($q) {
                $q->where('status', 'approved');
            }], 'hours')
            ->withSum(['overtimes as approved_overtime_days' => function ($q) {
                $q->where('status', 'approved');
            }], 'days_equivalent');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('job_title', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['department']) && $filters['department'] !== 'all') {
            $query->where('department', $filters['department']);
        }

        // Data scoping: when the caller may only view their own record, restrict
        // the listing to that employee id (set by the controller via AccessScope).
        if (array_key_exists('self_employee_id', $filters) && $filters['self_employee_id'] !== null) {
            $query->where('id', $filters['self_employee_id']);
        }

        $sortField = $filters['sort'] ?? 'created_at';
        $sortDir = $filters['direction'] ?? 'desc';
        $perPage = $filters['per_page'] ?? 25;

        return $query->orderBy($sortField, $sortDir)->paginate($perPage);
    }

    /**
     * Create a new employee record.
     */
    public function create(array $data): Employee
    {
        return Employee::create($data);
    }

    /**
     * Find employee by ID with relation counts and detailed info loaded.
     */
    public function find(int $id): Employee
    {
        return Employee::withCount(['companies', 'contracts', 'leaves', 'overtimes'])
            ->with(['leaves' => function ($q) {
                $q->orderBy('start_date', 'desc')->take(20);
            }, 'overtimes' => function ($q) {
                $q->orderBy('overtime_date', 'desc')->take(20);
            }])
            ->findOrFail($id);
    }

    /**
     * Update an existing employee record.
     */
    public function update(int $id, array $data): Employee
    {
        $employee = Employee::findOrFail($id);
        $employee->update($data);

        return $employee->fresh();
    }

    /**
     * Delete an employee. Blocks deletion if active contracts exist.
     */
    public function delete(int $id): bool
    {
        $employee = Employee::findOrFail($id);

        $hasActive = $employee->contracts()
            ->whereIn('status', ['active', 'signed'])
            ->exists();

        if ($hasActive) {
            return false;
        }

        return (bool) $employee->delete();
    }

    /**
     * Get aggregated statistics for a specific employee.
     */
    public function getStats(int $id): array
    {
        $employee = Employee::with(['contracts', 'leaves', 'overtimes'])->findOrFail($id);

        $contracts = $employee->contracts;
        $totalValue = $contracts->sum(fn ($c) => CurrencyHelper::toUsd((float) $c->contract_value, $c->currency));
        $totalPaid = $contracts->sum(fn ($c) => CurrencyHelper::toUsd((float) $c->total_paid, $c->currency));

        $annualAllowance = $employee->annual_leave_allowance ?? 21;
        $approvedAnnualLeaves = (float) $employee->leaves
            ->whereIn('leave_type', LeaveType::deductingKeys())
            ->where('status', 'approved')
            ->sum('days_count');

        $totalOvertimeHours = (float) $employee->overtimes
            ->where('status', 'approved')
            ->sum('hours');

        $totalOvertimeDays = (float) $employee->overtimes
            ->where('status', 'approved')
            ->sum('days_equivalent');

        return [
            'total_companies' => $employee->companies()->count(),
            'total_contracts' => $contracts->count(),
            'total_value' => round($totalValue, 2),
            'total_paid' => round($totalPaid, 2),
            'remaining' => round($totalValue - $totalPaid, 2),
            'avg_value' => $contracts->count() > 0 ? round($totalValue / $contracts->count(), 2) : 0,
            'annual_leave_allowance' => $annualAllowance,
            'annual_leaves_taken' => $approvedAnnualLeaves,
            'annual_leaves_remaining' => max(0, $annualAllowance - $approvedAnnualLeaves),
            'total_overtime_hours' => round($totalOvertimeHours, 2),
            'total_overtime_days' => round($totalOvertimeDays, 2),
        ];
    }

    /**
     * Get overall company employee statistics (Dashboard header cards).
     */
    public function getOverallStats(): array
    {
        $totalEmployees = Employee::count();

        $departments = [
            'design' => Employee::where('department', 'design')->count(),
            'photography' => Employee::where('department', 'photography')->count(),
            'sales' => Employee::where('department', 'sales')->count(),
            'dev' => Employee::where('department', 'dev')->count(),
            'management' => Employee::where('department', 'management')->count(),
        ];

        $totalAnnualLeavesTaken = (float) EmployeeLeave::whereIn('leave_type', LeaveType::deductingKeys())
            ->where('status', 'approved')
            ->sum('days_count');

        $totalOvertimeHours = (float) EmployeeOvertime::where('status', 'approved')
            ->sum('hours');

        $totalOvertimeDays = (float) EmployeeOvertime::where('status', 'approved')
            ->sum('days_equivalent');

        $pendingLeavesCount = EmployeeLeave::where('status', 'pending')->count();
        $pendingOvertimeCount = EmployeeOvertime::where('status', 'pending')->count();

        return [
            'total_employees' => $totalEmployees,
            'departments' => $departments,
            'total_annual_leaves_taken' => round($totalAnnualLeavesTaken, 1),
            'total_overtime_hours' => round($totalOvertimeHours, 1),
            'total_overtime_days' => round($totalOvertimeDays, 1),
            'pending_leaves_count' => $pendingLeavesCount,
            'pending_overtime_count' => $pendingOvertimeCount,
        ];
    }
}
