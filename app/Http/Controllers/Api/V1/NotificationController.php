<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\NotificationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\NotificationFilterRequest;
use App\Http\Resources\NotificationCollection;
use App\Http\Resources\NotificationResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The bell.
 *
 * Every endpoint here is scoped to the caller by the service — there is no
 * "whose feed" parameter to get wrong, and no way to ask for somebody else's.
 * That is why these routes carry only `notifications.view`: holding it lets
 * you read your own notices and nobody else's.
 */
class NotificationController extends Controller
{
    public function __construct(private NotificationService $service) {}

    /**
     * GET notifications — my feed, newest first.
     */
    public function index(NotificationFilterRequest $request): JsonResponse
    {
        return ApiResponse::paginated(
            new NotificationCollection($this->service->list($request->user(), $request->filters())),
            'Notifications retrieved successfully.'
        );
    }

    /**
     * GET notifications/unread-count — the number on the bell.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return ApiResponse::success(
            ['unread' => $this->service->unreadCount($request->user())],
            'Unread notification count retrieved successfully.'
        );
    }

    /**
     * PATCH notifications/{notification}/read.
     */
    public function markRead(Request $request, int $notification): JsonResponse
    {
        return ApiResponse::success(
            new NotificationResource($this->service->markRead($request->user(), $notification)),
            'Notification marked as read.'
        );
    }

    /**
     * PATCH notifications/read-all — clear the bell.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        return ApiResponse::success(
            ['marked' => $this->service->markAllRead($request->user())],
            'All notifications marked as read.'
        );
    }
}
