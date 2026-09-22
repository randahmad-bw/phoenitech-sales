<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * API collection resource for paginated notification feeds.
 */
class NotificationCollection extends ResourceCollection
{
    public $collects = NotificationResource::class;

    public function toArray(Request $request): array
    {
        return $this->collection->toArray();
    }
}
