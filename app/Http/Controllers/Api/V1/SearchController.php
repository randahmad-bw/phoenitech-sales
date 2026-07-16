<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\SearchService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles global search across all domain entities.
 */
class SearchController extends Controller
{
    public function __construct(private SearchService $service) {}

    public function index(Request $request): JsonResponse
    {
        $query = $request->input('q', '');
        if (strlen($query) < 2) {
            return ApiResponse::error('Search query must be at least 2 characters.', 'SEARCH_TOO_SHORT', 400);
        }
        $results = $this->service->search($query);
        return ApiResponse::success($results, 'Search results retrieved.');
    }
}
