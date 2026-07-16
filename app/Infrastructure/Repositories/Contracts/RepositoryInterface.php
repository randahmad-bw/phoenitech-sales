<?php

namespace App\Infrastructure\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Base repository contract defining standard CRUD operations.
 * All domain-specific repositories must extend this interface.
 */
interface RepositoryInterface
{
    /**
     * Retrieve all records, optionally filtered.
     */
    public function all(array $filters = []): Collection;

    /**
     * Retrieve paginated records with optional filters.
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator;

    /**
     * Find a single record by ID or return null.
     */
    public function find(int $id): ?Model;

    /**
     * Find a single record by ID or throw ModelNotFoundException.
     */
    public function findOrFail(int $id): Model;

    /**
     * Create a new record with the given data.
     */
    public function create(array $data): Model;

    /**
     * Update an existing record by ID.
     */
    public function update(int $id, array $data): Model;

    /**
     * Delete a record by ID.
     */
    public function delete(int $id): bool;
}
