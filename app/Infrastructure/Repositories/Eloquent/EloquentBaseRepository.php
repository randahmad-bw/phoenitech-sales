<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Infrastructure\Repositories\Contracts\RepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Base Eloquent repository providing default CRUD implementation.
 * Domain repositories extend this and add domain-specific query methods.
 */
abstract class EloquentBaseRepository implements RepositoryInterface
{
    /**
     * Create repository with injected Eloquent model instance.
     */
    public function __construct(protected Model $model)
    {
    }

    /**
     * Retrieve all records, optionally filtered.
     */
    public function all(array $filters = []): Collection
    {
        return $this->model->newQuery()->get();
    }

    /**
     * Retrieve paginated records with optional filters.
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->model->newQuery()->latest()->paginate($perPage);
    }

    /**
     * Find a single record by ID or return null.
     */
    public function find(int $id): ?Model
    {
        return $this->model->find($id);
    }

    /**
     * Find a single record by ID or throw ModelNotFoundException.
     */
    public function findOrFail(int $id): Model
    {
        return $this->model->findOrFail($id);
    }

    /**
     * Create a new record with the given data.
     */
    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    /**
     * Update an existing record by ID and return the updated model.
     */
    public function update(int $id, array $data): Model
    {
        $record = $this->findOrFail($id);
        $record->update($data);

        return $record->fresh();
    }

    /**
     * Delete a record by ID. Returns true on success.
     */
    public function delete(int $id): bool
    {
        $record = $this->findOrFail($id);

        return $record->delete();
    }
}
