<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\AttendanceService;
use App\Application\Support\ScheduleResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceHistoryRequest;
use App\Http\Requests\CheckOutAttendanceRequest;
use App\Http\Requests\PunchAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use App\Http\Resources\AttendanceTodayResource;
use App\Http\Responses\ApiResponse;
use App\Models\Attendance;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Self-service attendance: the four endpoints behind the employee's screen.
 *
 * Every action resolves the employee from the authenticated token. No endpoint
 * here accepts an employee id, a time or a status, so there is nothing for a
 * hand-crafted request to tamper with — the management endpoints (Phase 3)
 * are where corrections live, behind `attendance.edit`.
 */
class AttendanceController extends Controller
{
    public function __construct(
        private AttendanceService $service,
        private ScheduleResolver $resolver,
    ) {}

    /**
     * GET attendance/today — the whole employee screen in one call.
     */
    public function today(Request $request): JsonResponse
    {
        $employee = $this->employeeFor($request);

        if ($employee instanceof JsonResponse) {
            return $employee;
        }

        return ApiResponse::success(
            new AttendanceTodayResource($this->service->today($employee)),
            'Today\'s attendance retrieved successfully.'
        );
    }

    /**
     * POST attendance/check-in
     */
    public function checkIn(PunchAttendanceRequest $request): JsonResponse
    {
        $employee = $this->employeeFor($request);

        if ($employee instanceof JsonResponse) {
            return $employee;
        }

        $attendance = $this->service->checkIn(
            $employee,
            $request->user()->id,
            $request->validated('notes'),
            $request->validated('location'),
        );

        return ApiResponse::created(
            new AttendanceTodayResource($this->service->stateFor($employee, $this->dayOf($attendance))),
            'Checked in successfully.'
        );
    }

    /**
     * POST attendance/check-out
     */
    public function checkOut(CheckOutAttendanceRequest $request): JsonResponse
    {
        $employee = $this->employeeFor($request);

        if ($employee instanceof JsonResponse) {
            return $employee;
        }

        $attendance = $this->service->checkOut(
            $employee,
            $request->user(),
            $request->validated('notes'),
            $request->completedItemIds(),
        );

        return ApiResponse::success(
            new AttendanceTodayResource($this->service->stateFor($employee, $this->dayOf($attendance))),
            'Checked out successfully.'
        );
    }

    /**
     * GET attendance/my — own history plus the totals for the same range.
     */
    public function my(AttendanceHistoryRequest $request): JsonResponse
    {
        $employee = $this->employeeFor($request);

        if ($employee instanceof JsonResponse) {
            return $employee;
        }

        [$from, $to] = $request->range($this->resolver);

        return ApiResponse::success([
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'records' => AttendanceResource::collection($this->service->history($employee, $from, $to)),
            'summary' => $this->service->summary($employee, $from, $to),
        ], 'Attendance history retrieved successfully.');
    }

    /**
     * The working day a punched row belongs to.
     *
     * Not simply "today": a shift that starts at 23:00 and ends at 02:30 is
     * closed on the following calendar day but belongs to the day it began.
     */
    private function dayOf(Attendance $attendance): CarbonImmutable
    {
        return CarbonImmutable::parse($attendance->work_date->format('Y-m-d'), config('attendance.timezone'));
    }

    /**
     * The employee profile behind the signed-in account.
     *
     * An account with no profile cannot record attendance — that is a
     * configuration gap for management to close, not an error the employee
     * can do anything about, so it is reported plainly.
     */
    private function employeeFor(Request $request): Employee|JsonResponse
    {
        $employee = $request->user()?->employee;

        if (! $employee) {
            return ApiResponse::conflict(
                'This account is not linked to an employee profile, so attendance cannot be recorded for it.',
                'NO_EMPLOYEE_PROFILE'
            );
        }

        return $employee;
    }
}
