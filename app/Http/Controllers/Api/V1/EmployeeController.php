<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\EmployeeService;
use App\Application\Support\AccessScope;
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
        $filters = $request->all();
        // Users without employees.view_all see only their own profile.
        $filters['self_employee_id'] = AccessScope::ownEmployeeId($request->user(), 'employees.view_all');

        $employees = $this->service->list($filters);
        return ApiResponse::paginated(new EmployeeCollection($employees));
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = $this->service->create($request->validated());
        return ApiResponse::created(new EmployeeResource($employee), 'Employee created.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        // Without employees.view_all, a user may only view their own profile.
        if (! AccessScope::canAccessEmployee($request->user(), $id, 'employees.view_all')) {
            return ApiResponse::forbidden('You are not authorized to view this employee.');
        }

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
    public function stats(Request $request, int $id): JsonResponse
    {
        if (! AccessScope::canAccessEmployee($request->user(), $id, 'employees.view_all')) {
            return ApiResponse::forbidden('You are not authorized to view these stats.');
        }

        $stats = $this->service->getStats($id);
        return ApiResponse::success($stats, 'Employee stats retrieved.');
    }

    /**
     * Retrieve overall company employee statistics.
     */
    public function overallStats(): JsonResponse
    {
        $stats = $this->service->getOverallStats();
        return ApiResponse::success($stats, 'Overall employee stats retrieved.');
    }
}
