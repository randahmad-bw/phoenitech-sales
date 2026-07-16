<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * API collection resource for paginated company lists.
 */
class CompanyCollection extends ResourceCollection
{
    public $collects = CompanyResource::class;

    /**
     * Transform collection into API response array.
     */
    public function toArray(Request $request): array
    {
        return $this->collection->toArray();
    }
}
