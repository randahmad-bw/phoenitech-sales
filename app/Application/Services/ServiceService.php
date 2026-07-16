<?php

namespace App\Application\Services;

use App\Models\Service;
use Illuminate\Database\Eloquent\Collection;

/**
 * Handles service type CRUD operations.
 */
class ServiceService
{
    /**
     * List all services, optionally filtering by active status.
     */
    public function list(bool $activeOnly = false): Collection
    {
        $query = Service::withCount('contracts');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('name_en')->get();
    }

    /**
     * Create a new service type.
     */
    public function create(array $data): Service
    {
        return Service::create($data);
    }

    /**
     * Update an existing service record.
     */
    public function update(int $id, array $data): Service
    {
        $service = Service::findOrFail($id);
        $service->update($data);
        return $service->fresh();
    }

    /**
     * Delete a service. Blocks deletion if contracts reference it.
     */
    public function delete(int $id): bool
    {
        $service = Service::findOrFail($id);

        if ($service->contracts()->exists()) {
            return false;
        }

        return $service->delete();
    }
}
