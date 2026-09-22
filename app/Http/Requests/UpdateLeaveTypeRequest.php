<?php

namespace App\Http\Requests;

/**
 * Validates a change to an existing leave type.
 *
 * `key` is absent here for a stronger reason than on create: existing leave
 * records name the type by its key, and editing it would detach every one of
 * them. A type can be renamed — the names are display only — but not re-keyed.
 */
class UpdateLeaveTypeRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name_ar' => ['sometimes', 'required', 'string', 'max:60'],
            'name_en' => ['sometimes', 'required', 'string', 'max:60'],
            'is_active' => ['sometimes', 'boolean'],
            'deducts_from_allowance' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:999'],
        ];
    }
}
