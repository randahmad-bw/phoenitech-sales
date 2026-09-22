<?php

namespace App\Application\Services;

use App\Application\Support\LeaveDays;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\LeaveType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class EmployeeLeaveService
{
    public function __construct(private LeaveDays $days) {}

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

        /*
         * Working days, not calendar days — the same count the employee's own
         * request flow uses. This counted calendar days until 2026-09-22, so
         * leave entered by management over a weekend charged days the employee
         * was never due to work.
         */
        if (empty($data['days_count']) && ! empty($data['start_date']) && ! empty($data['end_date'])) {
            $data['days_count'] = $this->days->between(
                $employee,
                CarbonImmutable::parse($data['start_date'])->startOfDay(),
                CarbonImmutable::parse($data['end_date'])->startOfDay(),
            );
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
     *
     * This used to name four types in four near-identical sums, which meant a
     * type the catalogue gained was invisible here and a type it lost still had
     * a key in the payload. It now reads the catalogue: `by_type` covers every
     * type there is, and the allowance is spent by whichever ones are marked
     * `deducts_from_allowance`.
     *
     * @return array<string, mixed>
     */
    public function getSummary(int $employeeId): array
    {
        $employee = Employee::with('leaves')->findOrFail($employeeId);
        $allowance = (int) ($employee->annual_leave_allowance ?? 21);

        $approved = $employee->leaves->where('status', 'approved');
        $used = (float) $approved->whereIn('leave_type', LeaveType::deductingKeys())->sum('days_count');

        return [
            'annual_allowance' => $allowance,
            'annual_used' => $used,
            'annual_remaining' => max(0, $allowance - $used),
            'total_leaves_count' => $employee->leaves->count(),
            'pending_count' => $employee->leaves->where('status', 'pending')->count(),

            'by_type' => LeaveType::query()->ordered()->get()
                ->map(fn (LeaveType $type): array => [
                    'key' => $type->key,
                    'name_ar' => $type->name_ar,
                    'name_en' => $type->name_en,
                    'is_active' => $type->is_active,
                    'deducts_from_allowance' => $type->deducts_from_allowance,
                    'days' => (float) $approved->where('leave_type', $type->key)->sum('days_count'),
                ])
                ->values()
                ->all(),
        ];
    }
}
