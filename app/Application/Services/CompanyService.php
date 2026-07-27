<?php

namespace App\Application\Services;

use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Handles company CRUD operations and search.
 */
class CompanyService
{
    /**
     * Retrieve paginated companies with optional filters.
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = Company::with('employee')
            ->withCount(['contacts', 'contracts']);

        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('client_name', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%");
            });
        }

        if (!empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        $sortField = $filters['sort'] ?? 'created_at';
        $sortDir = $filters['direction'] ?? 'desc';
        $perPage = $filters['per_page'] ?? 25;

        return $query->orderBy($sortField, $sortDir)->paginate($perPage);
    }

    /**
     * Create a new company. Returns warning flag if name already exists.
     */
    public function create(array $data): array
    {
        $exists = Company::where('name', $data['name'])->exists();
        $company = Company::create($data);

        return [
            'company' => $company->load('employee'),
            'name_exists_warning' => $exists,
        ];
    }

    /**
     * Find company by ID with relations loaded.
     */
    public function find(int $id): Company
    {
        return Company::with(['employee', 'contacts', 'contracts'])
            ->withCount(['contacts', 'contracts'])
            ->findOrFail($id);
    }

    /**
     * Update an existing company record.
     */
    public function update(int $id, array $data): Company
    {
        $company = Company::findOrFail($id);
        $company->update($data);
        return $company->fresh()->load('employee');
    }

    /**
     * Delete a company. Blocks deletion if active contracts exist.
     */
    public function delete(int $id): bool
    {
        $company = Company::findOrFail($id);

        $hasActive = $company->contracts()
            ->whereIn('status', ['active', 'signed'])
            ->exists();

        if ($hasActive) {
            return false;
        }

        return $company->delete();
    }
}
