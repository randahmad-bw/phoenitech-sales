<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * API collection resource for paginated task lists.
 */
class TaskCollection extends ResourceCollection
{
    public $collects = TaskResource::class;

    public function toArray(Request $request): array
    {
        return $this->collection->toArray();
    }
}
