<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\LeaveRequestService;
use App\Application\Support\AccessScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewLeaveRequestRequest;
use App\Http\Requests\StoreLeaveRequestRequest;
use App\Http\Resources\EmployeeLeaveResource;
use App\Http\Responses\ApiResponse;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Leave: requesting it, and deciding on it.
 *
 * The two are separate endpoints behind separate permissions, because they are
 * separate acts by separate people. An employee submits (`leave.create`);
 * someone else decides (`leave.approve`). Nothing an employee can send sets a
 * status — approved leave rewrites their own attendance calendar, so the
 * ability to grant it must never sit with the person it benefits.
 */
class LeaveRequestController extends Controller
{
    public function __construct(private LeaveRequestService $service) {}

    // ─── Self-service ───

    /**
     * GET leaves/my — the employee's own requests and their balance.
     */
    public function my(Request $request): JsonResponse
    {
        $employee = $this->employeeFor($request);

        if ($employee instanceof JsonResponse) {
            return $employee;
        }

        return ApiResponse::success([
            'requests' => EmployeeLeaveResource::collection($this->service->forEmployee($employee)),
            'balance' => $this->service->balance($employee),
        ], 'Leave requests retrieved successfully.');
    }

    /**
     * POST leaves/my — submit a request. It always enters as pending.
     */
    public function store(StoreLeaveRequestRequest $request): JsonResponse
    {
        $employee = $this->employeeFor($request);

        if ($employee instanceof JsonResponse) {
            return $employee;
        }

        $leave = $this->service->request($employee, $request->validated(), $request->user()->id);

        return ApiResponse::created(
            new EmployeeLeaveResource($leave),
            'Leave request submitted. It is now awaiting a decision.'
        );
    }

    /**
     * DELETE leaves/my/{leave} — withdraw a request not yet decided.
     */
    public function cancel(Request $request, EmployeeLeave $leave): JsonResponse
    {
        $employee = $this->employeeFor($request);

        if ($employee instanceof JsonResponse) {
            return $employee;
        }

        if ($leave->employee_id !== $employee->id) {
            return ApiResponse::forbidden('You can only withdraw your own leave requests.');
        }

        $this->service->cancel($leave);

        return ApiResponse::success(null, 'Leave request withdrawn.');
    }

    // ─── Management ───

    /**
     * GET leaves — every request, pending first.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(
            EmployeeLeaveResource::collection($this->service->list([
                'status' => $request->input('status'),
                'employee_id' => $request->integer('employee_id') ?: null,
            ])),
            'Leave requests retrieved successfully.'
        );
    }

    /**
     * GET leaves/pending-count — how many requests await a decision.
     *
     * Separate from `index` for the same reason the bell has its own count
     * endpoint: the sidebar polls this while the leave screen is shut, and a
     * page of rows is an expensive way to learn a single number.
     */
    public function pendingCount(): JsonResponse
    {
        return ApiResponse::success(
            ['pending' => $this->service->pendingCount()],
            'Pending leave request count retrieved successfully.'
        );
    }

    /**
     * GET employees/{employee}/leave-requests — one person's history.
     */
    public function forEmployee(Request $request, Employee $employee): JsonResponse
    {
        if (! AccessScope::canAccessEmployee($request->user(), $employee->id, 'leave.view_all')) {
            return ApiResponse::forbidden('You are not authorized to view these leave requests.');
        }

        return ApiResponse::success([
            'requests' => EmployeeLeaveResource::collection($this->service->forEmployee($employee)),
            'balance' => $this->service->balance($employee),
        ], 'Leave requests retrieved successfully.');
    }

    /**
     * PATCH leaves/{leave}/review — approve or reject.
     */
    public function review(ReviewLeaveRequestRequest $request, EmployeeLeave $leave): JsonResponse
    {
        $decided = $this->service->review($leave, $request->validated(), $request->user()->id);

        return ApiResponse::success(
            new EmployeeLeaveResource($decided),
            'Leave request reviewed successfully.'
        );
    }

    /**
     * The employee profile behind the signed-in account.
     */
    private function employeeFor(Request $request): Employee|JsonResponse
    {
        $employee = $request->user()?->employee;

        if (! $employee) {
            return ApiResponse::conflict(
                'This account is not linked to an employee profile, so leave cannot be requested for it.',
                'NO_EMPLOYEE_PROFILE'
            );
        }

        return $employee;
    }
}
