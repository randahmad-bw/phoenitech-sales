<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\LeaveTypeService;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeaveTypeRequest;
use App\Http\Requests\UpdateLeaveTypeRequest;
use App\Http\Resources\LeaveTypeResource;
use App\Http\Responses\ApiResponse;
use App\Models\LeaveType;
use Illuminate\Http\JsonResponse;

/**
 * The catalogue of leave types.
 *
 * Reading it and editing it are two different acts behind two different
 * permissions. Everyone who can file a request needs the list - to fill the
 * picker, and to name the type on leave already taken - so `index` is open to
 * `leave.view_own`. Configuring the catalogue is a settings act, so `manage`,
 * which adds how many records each type carries, and everything that writes sit
 * behind `settings.view` / `settings.edit`.
 */
class LeaveTypeController extends Controller
{
    public function __construct(private LeaveTypeService $service) {}

    /**
     * GET leave-types — the catalogue behind the picker.
     *
     * Switched-off types are included and flagged: the picker drops them, and
     * the rest of the interface uses them to name leave already on file.
     */
    public function index(): JsonResponse
    {
        return ApiResponse::success(
            LeaveTypeResource::collection($this->service->catalogue()),
            'Leave types retrieved successfully.'
        );
    }

    /**
     * GET leave-types/manage — the whole catalogue, with usage counts.
     */
    public function manage(): JsonResponse
    {
        return ApiResponse::success(
            LeaveTypeResource::collection($this->service->withUsage()),
            'Leave types retrieved successfully.'
        );
    }

    public function store(StoreLeaveTypeRequest $request): JsonResponse
    {
        return ApiResponse::created(
            new LeaveTypeResource($this->service->create($request->validated())),
            'Leave type created successfully.'
        );
    }

    public function update(UpdateLeaveTypeRequest $request, LeaveType $leaveType): JsonResponse
    {
        return ApiResponse::success(
            new LeaveTypeResource($this->service->update($leaveType, $request->validated())),
            'Leave type updated successfully.'
        );
    }

    public function destroy(LeaveType $leaveType): JsonResponse
    {
        $this->service->delete($leaveType);

        return ApiResponse::success(null, 'Leave type deleted successfully.');
    }
}
