<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\EmployeeOvertimeService;
use App\Application\Support\AccessScope;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeOvertimeController extends Controller
{
    public function __construct(private EmployeeOvertimeService $service) {}

    public function index(Request $request, int $employeeId): JsonResponse
    {
        if (! AccessScope::canAccessEmployee($request->user(), $employeeId, 'overtime.view_all')) {
            return ApiResponse::forbidden('You are not authorized to view these overtime records.');
        }

        $overtimes = $this->service->listByEmployee($employeeId);
        $summary = $this->service->getSummary($employeeId);
        return ApiResponse::success([
            'overtimes' => $overtimes,
            'summary' => $summary,
        ], 'Overtime list retrieved successfully.');
    }

    public function store(Request $request, int $employeeId): JsonResponse
    {
        if (! AccessScope::canAccessEmployee($request->user(), $employeeId, 'overtime.view_all')) {
            return ApiResponse::forbidden('You can only submit overtime for your own profile.');
        }

        $validated = $request->validate([
            'overtime_date' => 'required|date',
            'hours' => 'required|numeric|min:0.5|max:24',
            'days_equivalent' => 'nullable|numeric|min:0',
            'rate_multiplier' => 'nullable|numeric|min:1.0',
            'overtime_type' => 'required|in:workday,weekend,holiday',
            'reason' => 'required|string|max:255',
            'status' => 'nullable|in:pending,approved,rejected',
            'notes' => 'nullable|string',
        ]);

        $overtime = $this->service->createOvertime($employeeId, $validated);
        return ApiResponse::created($overtime, 'Overtime record created successfully.');
    }

    public function update(Request $request, int $employeeId, int $overtimeId): JsonResponse
    {
        $validated = $request->validate([
            'overtime_date' => 'sometimes|date',
            'hours' => 'sometimes|numeric|min:0.5|max:24',
            'days_equivalent' => 'nullable|numeric|min:0',
            'rate_multiplier' => 'nullable|numeric|min:1.0',
            'overtime_type' => 'sometimes|in:workday,weekend,holiday',
            'reason' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:pending,approved,rejected',
            'notes' => 'nullable|string',
        ]);

        $overtime = $this->service->updateOvertime($overtimeId, $validated);
        return ApiResponse::success($overtime, 'Overtime record updated successfully.');
    }

    public function destroy(int $employeeId, int $overtimeId): JsonResponse
    {
        $this->service->deleteOvertime($overtimeId);
        return ApiResponse::success(null, 'Overtime record deleted successfully.');
    }
}
