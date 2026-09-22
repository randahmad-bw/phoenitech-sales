<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * API collection resource for paginated contract lists.
 * Uses lightweight serialization (no nested payments).
 */
class ContractCollection extends ResourceCollection
{
    public $collects = ContractResource::class;

    /**
     * Transform collection into API response array.
     */
    public function toArray(Request $request): array
    {
        return $this->collection->toArray();
    }
}
