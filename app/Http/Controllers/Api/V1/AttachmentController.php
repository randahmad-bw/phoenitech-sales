<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\AttachmentService;
use App\Http\Controllers\Controller;
use App\Http\Requests\UploadAttachmentRequest;
use App\Http\Resources\AttachmentResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Handles file upload and deletion for attachments.
 */
class AttachmentController extends Controller
{
    public function __construct(private AttachmentService $service) {}

    public function store(UploadAttachmentRequest $request): JsonResponse
    {
        $attachment = $this->service->store(
            $request->file('file'),
            $request->input('attachable_type'),
            $request->integer('attachable_id')
        );
        return ApiResponse::created(new AttachmentResource($attachment), 'File uploaded.');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);
        return ApiResponse::success(null, 'Attachment deleted.');
    }
}
