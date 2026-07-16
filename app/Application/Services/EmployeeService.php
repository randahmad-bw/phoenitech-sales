<?php

namespace App\Application\Services;

use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Handles employee CRUD operations and statistics.
 */
class EmployeeService
{
    /**
     * Retrieve paginated employees with optional search filter.
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = Employee::withCount(['companies', 'contracts']);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $sortField = $filters['sort'] ?? 'created_at';
        $sortDir = $filters['direction'] ?? 'desc';
        $perPage = $filters['per_page'] ?? 15;

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
     * Find employee by ID with relation counts loaded.
     */
    public function find(int $id): Employee
    {
        return Employee::withCount(['companies', 'contracts'])->findOrFail($id);
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

        return $employee->delete();
    }

    /**
     * Get aggregated statistics for a specific employee.
     */
    public function getStats(int $id): array
    {
        $employee = Employee::findOrFail($id);

        $contracts = $employee->contracts;
        $totalValue = $contracts->sum('contract_value');
        $totalPaid = 0;

        foreach ($contracts as $contract) {
            $totalPaid += $contract->total_paid;
        }

        return [
            'total_companies' => $employee->companies()->count(),
            'total_contracts' => $contracts->count(),
            'total_value' => round($totalValue, 2),
            'total_paid' => round($totalPaid, 2),
            'remaining' => round($totalValue - $totalPaid, 2),
            'avg_value' => $contracts->count() > 0 ? round($totalValue / $contracts->count(), 2) : 0,
        ];
    }
}
