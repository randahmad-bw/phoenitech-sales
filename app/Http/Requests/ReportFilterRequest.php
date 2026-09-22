<?php

namespace App\Http\Requests;

/**
 * Validates reports filtering parameters.
 */
class ReportFilterRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2000', 'max:2099'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ];
    }
}
