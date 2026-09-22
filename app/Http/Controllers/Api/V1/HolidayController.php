<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\HolidayService;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreHolidayRequest;
use App\Http\Resources\HolidayResource;
use App\Http\Responses\ApiResponse;
use App\Models\Holiday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Company and public holidays.
 *
 * A holiday is never an absence: the nightly close skips these dates and the
 * reader renders them as status `holiday`.
 */
class HolidayController extends Controller
{
    public function __construct(private HolidayService $service) {}

    public function index(Request $request): JsonResponse
    {
        $year = $request->integer('year') ?: null;

        return ApiResponse::success(
            HolidayResource::collection($this->service->list($year)),
            'Holidays retrieved successfully.'
        );
    }

    public function store(StoreHolidayRequest $request): JsonResponse
    {
        return ApiResponse::created(
            new HolidayResource($this->service->create($request->validated())),
            'Holiday created successfully.'
        );
    }

    public function update(StoreHolidayRequest $request, Holiday $holiday): JsonResponse
    {
        return ApiResponse::success(
            new HolidayResource($this->service->update($holiday, $request->validated())),
            'Holiday updated successfully.'
        );
    }

    public function destroy(Holiday $holiday): JsonResponse
    {
        $this->service->delete($holiday);

        return ApiResponse::success(null, 'Holiday deleted successfully.');
    }
}
