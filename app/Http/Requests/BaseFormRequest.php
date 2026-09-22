<?php

namespace App\Http\Requests;

use App\Http\Responses\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Base form request that all API requests must extend.
 * Standardizes validation error and authorization failure responses.
 */
class BaseFormRequest extends FormRequest
{
    /**
     * Default authorization — override in child classes if needed.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Override failed validation to return ApiResponse JSON instead of redirect.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError($validator->errors()->toArray())
        );
    }

    /**
     * Override failed authorization to return ApiResponse JSON.
     */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            ApiResponse::forbidden('You are not authorized to perform this action.')
        );
    }
}
