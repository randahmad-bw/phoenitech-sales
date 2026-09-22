<?php

namespace App\Http\Requests;

/**
 * Validates a new leave type.
 *
 * Note what is missing: **there is no `key`.** The key is what every existing
 * leave record points at, so a client able to choose one could file a new type
 * on top of another type's history. The service derives it from the English
 * name instead.
 */
class StoreLeaveTypeRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:60'],
            'name_en' => ['required', 'string', 'max:60'],
            'is_active' => ['nullable', 'boolean'],
            // Whether approved days of this type come off the employee's
            // yearly balance. This is the only behaviour a type carries.
            'deducts_from_allowance' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }
}
