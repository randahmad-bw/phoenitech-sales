<?php

namespace App\Http\Requests;

/**
 * Validates login credentials for API authentication.
 */
class LoginRequest extends BaseFormRequest
{
    /**
     * Validation rules for login attempt.
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'string'],
            'password' => ['required', 'string', 'min:6'],
        ];
    }
}
