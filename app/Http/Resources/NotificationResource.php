<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One notice, as the bell reads it.
 *
 * `type` and `data` go out untranslated on purpose — the screen builds the
 * sentence from them, so the same row reads correctly in Arabic and English.
 */
class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'data' => $this->data ?? [],
            'link' => $this->link,
            'is_read' => $this->isRead(),
            'read_at' => $this->read_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
