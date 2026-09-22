<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\AttendanceManagementService;
use App\Application\Support\AccessScope;
use App\Application\Support\ScheduleResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceFilterRequest;
use App\Http\Requests\AttendanceHistoryRequest;
use App\Http\Requests\ReviewOvertimeRequest;
use App\Http\Requests\StoreAttendanceRequest;
use App\Http\Requests\TeamSummaryRequest;
use App\Http\Requests\UpdateAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use App\Http\Resources\AttendanceSessionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;

/**
 * Attendance for management: reading across people, and correcting.
 *
 * Separate from AttendanceController on purpose. The self-service endpoints
 * accept nothing but an optional note; these accept employees, times and
 * statuses — so they sit behind different permissions, and the split makes it
 * impossible to widen one by accident while editing the other.
 */
class AttendanceManagementController extends Controller
{
    public function __construct(
        private AttendanceManagementService $service,
        private ScheduleResolver $resolver,
    ) {}

    /**
     * GET attendance/overview — the headline counters for one day.
     */
    public function overview(AttendanceFilterRequest $request): JsonResponse
    {
        return ApiResponse::success(
            $this->service->overview($request->resolveDate($this->resolver)),
            'Attendance overview retrieved successfully.'
        );
    }

    /**
     * GET attendance — one day across the company, filterable.
     *
     * Days off, holidays and leave are derived rather than stored, so the grid
     * shows a complete company even though most of those rows do not exist.
     */
    public function index(AttendanceFilterRequest $request): JsonResponse
    {
        $date = $request->resolveDate($this->resolver);

        return ApiResponse::success([
            'date' => $date->format('Y-m-d'),
            'records' => AttendanceResource::collection(
                $this->service->dayGrid($date, $request->filters())
            ),
        ], 'Attendance records retrieved successfully.');
    }

    /**
     * GET attendance/summary — every tracked employee's totals over a range.
     *
     * One row per person, not per day: the week view answers "who is short of
     * hours and who was absent", which is a question about people.
     */
    public function summary(TeamSummaryRequest $request): JsonResponse
    {
        [$from, $to] = $request->range($this->resolver);

        return ApiResponse::success([
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'records' => $this->service->teamSummary($from, $to, $request->filters()),
        ], 'Team attendance summary retrieved successfully.');
    }

    /**
     * GET employees/{employee}/attendance — one person's calendar and totals.
     *
     * Readable by management, and by employees for their own profile — the
     * same view_own/view_all split every other module uses.
     */
    public function forEmployee(AttendanceHistoryRequest $request, Employee $employee): JsonResponse
    {
        if (! AccessScope::canAccessEmployee($request->user(), $employee->id, 'attendance.view_all')) {
            return ApiResponse::forbidden('You are not authorized to view this employee\'s attendance.');
        }

        [$from, $to] = $request->range($this->resolver);

        return ApiResponse::success([
            'employee' => ['id' => $employee->id, 'name' => $employee->name, 'department' => $employee->department],
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'records' => AttendanceResource::collection($this->service->employeeGrid($employee, $from, $to)),
            'summary' => $this->service->employeeSummary($employee, $from, $to),
        ], 'Employee attendance retrieved successfully.');
    }

    /**
     * POST attendance — enter a record by hand.
     */
    public function store(StoreAttendanceRequest $request): JsonResponse
    {
        $attendance = $this->service->createManually($request->validated(), $request->user()->id);

        return ApiResponse::created(
            new AttendanceResource($attendance->load('employee')),
            'Attendance record created successfully.'
        );
    }

    /**
     * PUT attendance/{attendance} — correct a record.
     *
     * The previous values are preserved in the audit trail, together with the
     * mandatory reason and who made the change.
     */
    public function update(UpdateAttendanceRequest $request, Attendance $attendance): JsonResponse
    {
        $corrected = $this->service->correct($attendance, $request->validated(), $request->user()->id);

        return ApiResponse::success(
            new AttendanceResource($corrected),
            'Attendance record corrected successfully.'
        );
    }

    /**
     * GET attendance/overtime — extra work awaiting a decision.
     *
     * A day worked in two sittings produces one of these: the employee had
     * finished, came back for something urgent, and the hours were recorded.
     * Whether they are owed is management's call.
     */
    public function pendingOvertime(): JsonResponse
    {
        return ApiResponse::success(
            AttendanceSessionResource::collection($this->service->pendingOvertime()),
            'Pending overtime retrieved successfully.'
        );
    }

    /**
     * PATCH attendance/sessions/{session}/overtime — approve or reject it.
     */
    public function reviewOvertime(ReviewOvertimeRequest $request, AttendanceSession $session): JsonResponse
    {
        $reviewed = $this->service->reviewOvertime($session, $request->validated(), $request->user()->id);

        return ApiResponse::success(
            new AttendanceSessionResource($reviewed),
            'Overtime reviewed successfully.'
        );
    }

    /**
     * DELETE attendance/{attendance}
     */
    public function destroy(Attendance $attendance): JsonResponse
    {
        $this->service->delete($attendance);

        return ApiResponse::success(null, 'Attendance record deleted successfully.');
    }
}
