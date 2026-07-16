<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\ServiceService;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD operations for service type management.
 */
class ServiceController extends Controller
{
    public function __construct(private ServiceService $service) {}

    public function index(Request $request): JsonResponse
    {
        $activeOnly = $request->boolean('active_only', false);
        $services = $this->service->list($activeOnly);
        return ApiResponse::success(ServiceResource::collection($services));
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $service = $this->service->create($request->validated());
        return ApiResponse::created(new ServiceResource($service), 'Service created.');
    }

    public function update(UpdateServiceRequest $request, int $id): JsonResponse
    {
        $service = $this->service->update($id, $request->validated());
        return ApiResponse::success(new ServiceResource($service), 'Service updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->service->delete($id);
        if (!$deleted) {
            return ApiResponse::conflict('Cannot delete service with existing contracts.', 'SERVICE_HAS_CONTRACTS');
        }
        return ApiResponse::success(null, 'Service deleted.');
    }
}
