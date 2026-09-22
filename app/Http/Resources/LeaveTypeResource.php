<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A leave type.
 *
 * `leaves_count` is present only on the management listing, and it is what the
 * settings screen reads to decide whether a type offers "delete" or only
 * "switch off": a type with history behind it cannot be removed.
 */
class LeaveTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // What `employee_leaves.leave_type` stores. Read-only everywhere.
            'key' => $this->key,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'is_active' => (bool) $this->is_active,
            'deducts_from_allowance' => (bool) $this->deducts_from_allowance,
            'sort_order' => (int) $this->sort_order,
            'leaves_count' => $this->whenCounted('leaves'),
        ];
    }
}
