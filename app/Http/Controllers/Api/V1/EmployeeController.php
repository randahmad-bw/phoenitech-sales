<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\EmployeeService;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeCollection;
use App\Http\Resources\EmployeeResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD operations for employee management.
 */
class EmployeeController extends Controller
{
    public function __construct(private EmployeeService $service) {}

    public function index(Request $request): JsonResponse
    {
        $employees = $this->service->list($request->all());
        return ApiResponse::paginated(new EmployeeCollection($employees));
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = $this->service->create($request->validated());
        return ApiResponse::created(new EmployeeResource($employee), 'Employee created.');
    }

    public function show(int $id): JsonResponse
    {
        $employee = $this->service->find($id);
        return ApiResponse::success(new EmployeeResource($employee));
    }

    public function update(UpdateEmployeeRequest $request, int $id): JsonResponse
    {
        $employee = $this->service->update($id, $request->validated());
        return ApiResponse::success(new EmployeeResource($employee), 'Employee updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->service->delete($id);
        if (!$deleted) {
            return ApiResponse::conflict('Cannot delete employee with active contracts.', 'EMPLOYEE_HAS_ACTIVE_CONTRACTS');
        }
        return ApiResponse::success(null, 'Employee deleted.');
    }

    /**
     * Retrieve per-employee aggregated statistics.
     */
    public function stats(int $id): JsonResponse
    {
        $stats = $this->service->getStats($id);
        return ApiResponse::success($stats, 'Employee stats retrieved.');
    }
}
