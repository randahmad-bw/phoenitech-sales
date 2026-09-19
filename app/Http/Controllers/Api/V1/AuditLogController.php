<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\AuditLogService;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Http\Responses\ApiResponse;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only access to the audit trail. Every route is gated by `audit.view`.
 *
 * There is no store/update/destroy by design: the trail is append-only, written
 * by AuditLogger, and thinned only by the `audit:prune` command.
 */
class AuditLogController extends Controller
{
    public function __construct(private AuditLogService $service) {}

    /**
     * Filtered listing. Query params: user_id, event, type, record_id,
     * date_from, date_to, search, per_page.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['sometimes', 'integer'],
            'record_id' => ['sometimes', 'integer'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $logs = $this->service->list($request->all());

        return ApiResponse::paginated(AuditLogResource::collection($logs));
    }

    /**
     * The filter dropdown options: events, record types, and actors that
     * actually appear in the trail.
     */
    public function filters(): JsonResponse
    {
        return ApiResponse::success($this->service->filterOptions());
    }

    /**
     * The full history of one record, e.g. /audit-logs/record/contract/42.
     */
    public function forRecord(Request $request, string $type, int $id): JsonResponse
    {
        $logs = $this->service->forRecord($type, $id, (int) $request->input('per_page', 25));

        return ApiResponse::paginated(AuditLogResource::collection($logs));
    }

    public function show(AuditLog $auditLog): JsonResponse
    {
        return ApiResponse::success(new AuditLogResource($this->service->find($auditLog->id)));
    }
}
