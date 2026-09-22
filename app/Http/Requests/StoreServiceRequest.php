<?php

namespace App\Http\Requests;

/**
 * Validates service creation data.
 */
class StoreServiceRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
        ];
    }
}
