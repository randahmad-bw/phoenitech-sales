<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\WorkScheduleService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignWorkScheduleRequest;
use App\Http\Requests\SetEmployeeWeekRequest;
use App\Http\Requests\StoreWorkScheduleRequest;
use App\Http\Requests\UpdateWorkScheduleRequest;
use App\Http\Resources\EmployeeScheduleResource;
use App\Http\Resources\EmployeeWeekResource;
use App\Http\Resources\WorkScheduleResource;
use App\Http\Responses\ApiResponse;
use App\Models\Employee;
use App\Models\WorkSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

/**
 * Schedule templates and their assignment to employees.
 *
 * Everything here is behind `attendance.manage_schedules`, which only the admin
 * accounts hold: correcting one day's record is routine, but redefining
 * someone's working week changes what every future day is measured against.
 */
class WorkScheduleController extends Controller
{
    public function __construct(private WorkScheduleService $service) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success(
            WorkScheduleResource::collection($this->service->list()),
            'Work schedules retrieved successfully.'
        );
    }

    public function show(WorkSchedule $workSchedule): JsonResponse
    {
        return ApiResponse::success(
            new WorkScheduleResource($workSchedule->load('days')->loadCount('assignments')),
            'Work schedule retrieved successfully.'
        );
    }

    public function store(StoreWorkScheduleRequest $request): JsonResponse
    {
        return ApiResponse::created(
            new WorkScheduleResource($this->service->create($request->validated())),
            'Work schedule created successfully.'
        );
    }

    public function update(UpdateWorkScheduleRequest $request, WorkSchedule $workSchedule): JsonResponse
    {
        return ApiResponse::success(
            new WorkScheduleResource($this->service->update($workSchedule, $request->validated())),
            'Work schedule updated successfully.'
        );
    }

    public function destroy(WorkSchedule $workSchedule): JsonResponse
    {
        $this->service->delete($workSchedule);

        return ApiResponse::success(null, 'Work schedule deleted successfully.');
    }

    /**
     * GET employee-weeks — every employee with their working week.
     *
     * The schedule screen is a list of people, not a catalogue of templates:
     * management opens a person and edits their days and hours. Templates
     * still carry the data underneath, but nobody has to think about them.
     */
    public function weeks(): JsonResponse
    {
        $employees = Employee::query()
            ->with('currentSchedule.workSchedule.days')
            ->orderByDesc('tracks_attendance')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(
            EmployeeWeekResource::collection($employees),
            'Employee working weeks retrieved successfully.'
        );
    }

    /**
     * PUT employees/{employee}/week — set one employee's working week.
     */
    public function setWeek(SetEmployeeWeekRequest $request, Employee $employee): JsonResponse
    {
        if ($request->has('tracks_attendance')) {
            $employee->update(['tracks_attendance' => $request->boolean('tracks_attendance')]);
        }

        $this->service->setEmployeeWeek($employee, $request->validated('days'));

        return ApiResponse::success(
            new EmployeeWeekResource($employee->fresh(['currentSchedule.workSchedule.days'])),
            'Working week updated successfully.'
        );
    }

    /**
     * GET employees/{employee}/schedules — the assignment history.
     */
    public function assignments(Employee $employee): JsonResponse
    {
        return ApiResponse::success(
            EmployeeScheduleResource::collection($this->service->assignmentsFor($employee)),
            'Employee schedule history retrieved successfully.'
        );
    }

    /**
     * POST employees/{employee}/schedules — move the employee onto a schedule.
     */
    public function assign(AssignWorkScheduleRequest $request, Employee $employee): JsonResponse
    {
        $schedule = WorkSchedule::findOrFail($request->validated('work_schedule_id'));

        $assignment = $this->service->assign(
            $employee,
            $schedule,
            CarbonImmutable::parse($request->validated('effective_from'), config('attendance.timezone'))->startOfDay(),
            $request->validated('notes'),
        );

        return ApiResponse::created(
            new EmployeeScheduleResource($assignment->load('workSchedule.days')),
            'Schedule assigned successfully.'
        );
    }
}
