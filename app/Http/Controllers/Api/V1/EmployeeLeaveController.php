<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\EmployeeLeaveService;
use App\Application\Support\AccessScope;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function store(Request $request, int $employeeId): JsonResponse
    {
        if (! AccessScope::canAccessEmployee($request->user(), $employeeId, 'leave.view_all')) {
            return ApiResponse::forbidden('You can only submit leave for your own profile.');
        }

        $validated = $request->validate([
            'leave_type' => 'required|in:annual,sick,unpaid,emergency,special',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'days_count' => 'nullable|numeric|min:0.5',
            'status' => 'nullable|in:pending,approved,rejected',
            'reason' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $leave = $this->service->createLeave($employeeId, $validated);
        return ApiResponse::created($leave, 'Leave record created successfully.');
    }

    public function update(Request $request, int $employeeId, int $leaveId): JsonResponse
    {
        $validated = $request->validate([
            'leave_type' => 'sometimes|in:annual,sick,unpaid,emergency,special',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'days_count' => 'nullable|numeric|min:0.5',
            'status' => 'sometimes|in:pending,approved,rejected',
            'reason' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $leave = $this->service->updateLeave($leaveId, $validated);
        return ApiResponse::success($leave, 'Leave record updated successfully.');
    }

    public function destroy(int $employeeId, int $leaveId): JsonResponse
    {
        $this->service->deleteLeave($leaveId);
        return ApiResponse::success(null, 'Leave record deleted successfully.');
    }
}
