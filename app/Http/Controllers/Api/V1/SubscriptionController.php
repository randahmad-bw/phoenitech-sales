<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\SubscriptionService;
use App\Http\Controllers\Controller;
use App\Http\Resources\ContractCollection;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Subscription/renewal management endpoints.
 * Provides dashboard stats and filtered subscription listing.
 */
class SubscriptionController extends Controller
{
    public function __construct(private SubscriptionService $service) {}

    /**
     * GET /subscriptions/dashboard — KPI stats for the subscriptions dashboard.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $data = $this->service->getDashboard($request->all());
        return ApiResponse::success($data);
    }

    /**
     * GET /subscriptions — paginated subscription list with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $subscriptions = $this->service->list($request->all());
        return ApiResponse::paginated(new ContractCollection($subscriptions));
    }
}
