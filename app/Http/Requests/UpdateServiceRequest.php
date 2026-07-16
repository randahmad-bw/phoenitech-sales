<?php

namespace App\Http\Requests;

/**
 * Validates service update data.
 */
class UpdateServiceRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name_ar' => ['sometimes', 'string', 'max:255'],
            'name_en' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
