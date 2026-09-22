<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\EmployeeLeaveService;
use App\Application\Support\AccessScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeLeaveRequest;
use App\Http\Requests\UpdateEmployeeLeaveRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Leave recorded for an employee by management.
 *
 * Superseded by LeaveRequestController for asking and deciding; this is the
 * direct-entry tool for leave agreed outside the system, which is why these
 * routes accept a status and sit behind `leave.approve`.
 */
class EmployeeLeaveController extends Controller
{
    public function __construct(private EmployeeLeaveService $service) {}

    public function index(Request $request, int $employeeId): JsonResponse
    {
        if (! AccessScope::canAccessEmployee($request->user(), $employeeId, 'leave.view_all')) {
            return ApiResponse::forbidden('You are not authorized to view these leaves.');
        }

        $leaves = $this->service->listByEmployee($employeeId);
        $summary = $this->service->getSummary($employeeId);

        return ApiResponse::success([
            'leaves' => $leaves,
            'summary' => $summary,
        ], 'Leaves list retrieved successfully.');
    }

    public function store(StoreEmployeeLeaveRequest $request, int $employeeId): JsonResponse
    {
        if (! AccessScope::canAccessEmployee($request->user(), $employeeId, 'leave.view_all')) {
            return ApiResponse::forbidden('You can only submit leave for your own profile.');
        }

        $leave = $this->service->createLeave($employeeId, $request->validated());

        return ApiResponse::created($leave, 'Leave record created successfully.');
    }

    public function update(UpdateEmployeeLeaveRequest $request, int $employeeId, int $leaveId): JsonResponse
    {
        $leave = $this->service->updateLeave($leaveId, $request->validated());

        return ApiResponse::success($leave, 'Leave record updated successfully.');
    }

    public function destroy(int $employeeId, int $leaveId): JsonResponse
    {
        $this->service->deleteLeave($leaveId);

        return ApiResponse::success(null, 'Leave record deleted successfully.');
    }
}
