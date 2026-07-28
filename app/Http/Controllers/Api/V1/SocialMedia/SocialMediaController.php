<?php

namespace App\Http\Controllers\Api\V1\SocialMedia;

use App\Application\Services\SocialMedia\SocialMediaService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Single controller for all Social Media module endpoints.
 */
class SocialMediaController extends Controller
{
    public function __construct(private SocialMediaService $service) {}

    // ─── Packages ─────────────────────────────────────────────

    public function listPackages(Request $request): JsonResponse
    {
        $packages = $this->service->listPackages($request->all());
        return ApiResponse::success($packages);
    }

    public function storePackage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contract_id'      => 'nullable|exists:contracts,id',
            'package_name'     => 'nullable|string|max:255',
            'price'            => 'nullable|numeric|min:0',
            'monthly_posts'    => 'integer|min:0',
            'monthly_reels'    => 'integer|min:0',
            'monthly_stories'  => 'integer|min:0',
            'boost_reel_cost'  => 'nullable|numeric|min:0',
            'boost_post_cost'  => 'nullable|numeric|min:0',
            'boost_story_cost' => 'nullable|numeric|min:0',
            'is_custom'        => 'boolean',
            'notes'            => 'nullable|string',
        ]);

        $package = $this->service->createPackage($data);
        return ApiResponse::created($package->load('contract.company'));
    }

    public function updatePackage(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'package_name'     => 'nullable|string|max:255',
            'price'            => 'nullable|numeric|min:0',
            'monthly_posts'    => 'integer|min:0',
            'monthly_reels'    => 'integer|min:0',
            'monthly_stories'  => 'integer|min:0',
            'boost_reel_cost'  => 'nullable|numeric|min:0',
            'boost_post_cost'  => 'nullable|numeric|min:0',
            'boost_story_cost' => 'nullable|numeric|min:0',
            'is_custom'        => 'boolean',
            'notes'            => 'nullable|string',
        ]);

        $package = $this->service->updatePackage($id, $data);
        return ApiResponse::success($package);
    }

    public function deletePackage(int $id): JsonResponse
    {
        $this->service->deletePackage($id);
        return ApiResponse::success(null, 'Package deleted.');
    }

    // ─── Content Plans ────────────────────────────────────────

    public function listPlans(Request $request): JsonResponse
    {
        $plans = $this->service->listPlans($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Data retrieved.',
            'data' => $plans->items(),
            'meta' => [
                'current_page' => $plans->currentPage(),
                'last_page' => $plans->lastPage(),
                'per_page' => $plans->perPage(),
                'total' => $plans->total(),
            ],
        ]);
    }

    public function showPlan(int $id): JsonResponse
    {
        $plan = $this->service->findPlan($id);
        return ApiResponse::success($plan);
    }

    public function storePlan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contract_id' => 'required|exists:contracts,id',
            'company_id' => 'required|exists:companies,id',
            'sm_package_id' => 'nullable|exists:sm_packages,id',
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|min:2020',
            'status' => 'in:active,completed',
            'notes' => 'nullable|string',
        ]);

        $plan = $this->service->createPlan($data);
        return ApiResponse::created($plan->load(['contract.company', 'package']));
    }

    public function storePlanWithItems(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contract_id' => 'required|exists:contracts,id',
            'company_id' => 'required|exists:companies,id',
            'sm_package_id' => 'nullable|exists:sm_packages,id',
            'month' => 'nullable|integer|between:1,12',
            'year' => 'nullable|integer|min:2020',
            'notes' => 'nullable|string',
            'items' => 'required|array',
            'items.*.title' => 'required|string|max:255',
            'items.*.content_type' => 'required|in:post,reel,story',
            'items.*.design_date' => 'nullable|date',
            'items.*.publish_date' => 'nullable|date',
            'items.*.assigned_to' => 'nullable|exists:employees,id',
            'items.*.is_designed' => 'boolean',
            'items.*.is_published' => 'boolean',
        ]);

        $plan = $this->service->createPlanWithItems($data);
        return ApiResponse::created($plan);
    }

    public function updatePlan(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'month' => 'integer|between:1,12',
            'year' => 'integer|min:2020',
            'status' => 'in:active,completed',
            'notes' => 'nullable|string',
        ]);

        $plan = $this->service->updatePlan($id, $data);
        return ApiResponse::success($plan);
    }

    public function deletePlan(int $id): JsonResponse
    {
        $this->service->deletePlan($id);
        return ApiResponse::success(null, 'Plan deleted.');
    }

    // ─── Content Items ────────────────────────────────────────

    public function listItems(Request $request): JsonResponse
    {
        $items = $this->service->listItems($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Data retrieved.',
            'data' => $items->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function storeItem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'content_plan_id' => 'required|exists:sm_content_plans,id',
            'title' => 'required|string|max:255',
            'content_type' => 'required|in:post,reel,story',
            'design_date' => 'nullable|date',
            'publish_date' => 'nullable|date',
            'assigned_to' => 'nullable|exists:employees,id',
            'photo_session_id' => 'nullable|exists:sm_photo_sessions,id',
            'is_designed' => 'boolean',
            'is_published' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        $item = $this->service->createItem($data);
        return ApiResponse::created($item->load(['plan.contract.company', 'designer', 'photoSession']));
    }

    public function updateItem(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'title' => 'string|max:255',
            'content_type' => 'in:post,reel,story',
            'design_date' => 'nullable|date',
            'publish_date' => 'nullable|date',
            'assigned_to' => 'nullable|exists:employees,id',
            'photo_session_id' => 'nullable|exists:sm_photo_sessions,id',
            'is_designed' => 'boolean',
            'is_published' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        $item = $this->service->updateItem($id, $data);
        return ApiResponse::success($item);
    }

    public function toggleCheckboxes(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'is_designed' => 'boolean',
            'is_published' => 'boolean',
            'status' => 'string',
        ]);

        $item = $this->service->toggleCheckboxes($id, $data);
        return ApiResponse::success($item);
    }

    public function deleteItem(int $id): JsonResponse
    {
        $this->service->deleteItem($id);
        return ApiResponse::success(null, 'Item deleted.');
    }

    // ─── Photo Sessions ───────────────────────────────────────

    public function listSessions(Request $request): JsonResponse
    {
        $sessions = $this->service->listSessions($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Data retrieved.',
            'data' => $sessions->items(),
            'meta' => [
                'current_page' => $sessions->currentPage(),
                'last_page' => $sessions->lastPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total(),
            ],
        ]);
    }

    public function storeSession(Request $request): JsonResponse
    {
        $data = $request->validate([
            'content_plan_id' => 'required|exists:sm_content_plans,id',
            'company_id' => 'required|exists:companies,id',
            'session_date' => 'required|date',
            'session_time' => 'required',
            'photographer_id' => 'nullable|exists:employees,id',
            'status' => 'in:scheduled,completed,cancelled',
            'notes' => 'nullable|string',
        ]);

        $session = $this->service->createSession($data);
        return ApiResponse::created($session->load(['plan.contract', 'company', 'photographer']));
    }

    public function updateSession(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'session_date' => 'date',
            'session_time' => 'string',
            'photographer_id' => 'nullable|exists:employees,id',
            'status' => 'in:scheduled,completed,cancelled',
            'notes' => 'nullable|string',
        ]);

        $session = $this->service->updateSession($id, $data);
        return ApiResponse::success($session);
    }

    public function updateSessionStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:scheduled,completed,cancelled',
        ]);

        $session = $this->service->updateSessionStatus($id, $data['status']);
        return ApiResponse::success($session);
    }

    public function deleteSession(int $id): JsonResponse
    {
        $this->service->deleteSession($id);
        return ApiResponse::success(null, 'Session deleted.');
    }

    // ─── Alerts & Dashboard ───────────────────────────────────

    public function alerts(): JsonResponse
    {
        return ApiResponse::success($this->service->getPublishingAlerts());
    }

    public function dashboard(): JsonResponse
    {
        return ApiResponse::success($this->service->getDashboardStats());
    }
}
