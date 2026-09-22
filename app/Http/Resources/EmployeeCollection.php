<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * API collection resource for paginated employee lists.
 */
class EmployeeCollection extends ResourceCollection
{
    public $collects = EmployeeResource::class;

    /**
     * Transform collection into API response array.
     */
    public function toArray(Request $request): array
    {
        return $this->collection->toArray();
    }
}
