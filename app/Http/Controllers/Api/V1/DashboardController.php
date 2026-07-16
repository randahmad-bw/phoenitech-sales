<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\DashboardService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Returns aggregated dashboard statistics and chart data.
 */
class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboardService) {}

    /**
     * Retrieve dashboard KPI stats and chart datasets.
     */
    public function index(Request $request): JsonResponse
    {
        $year = $request->integer('year', now()->year);
        $data = $this->dashboardService->getData($year);
        return ApiResponse::success($data, 'Dashboard data retrieved.');
    }
}
