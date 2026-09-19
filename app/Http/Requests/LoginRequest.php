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
        // The field is still named "email" for frontend compatibility, but it
        // accepts either an email address or a username — resolved in the
        // controller — so it is validated as a plain string, not an email.
        return [
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }
}
