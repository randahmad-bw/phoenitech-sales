<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for Attachment model serialization.
 */
class AttachmentResource extends JsonResource
{
    /**
     * Transform attachment model into API response array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'url' => $this->url,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
