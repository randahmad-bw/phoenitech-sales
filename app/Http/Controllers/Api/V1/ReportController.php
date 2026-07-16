<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\ReportService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Generates monthly and yearly reports.
 */
class ReportController extends Controller
{
    public function __construct(private ReportService $service) {}

    public function monthly(Request $request): JsonResponse
    {
        $year = $request->integer('year', now()->year);
        $month = $request->integer('month', now()->month);
        $report = $this->service->monthlyReport($year, $month);
        return ApiResponse::success($report, 'Monthly report retrieved.');
    }

    public function yearly(Request $request): JsonResponse
    {
        $year = $request->integer('year', now()->year);
        $report = $this->service->yearlyReport($year);
        return ApiResponse::success($report, 'Yearly report retrieved.');
    }
}
