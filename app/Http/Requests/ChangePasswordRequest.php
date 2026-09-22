<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rules\Password;

/**
 * Validates a self-service password change for the authenticated user.
 */
class ChangePasswordRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'confirmed',
                'different:current_password',
                Password::min(8)->letters()->numbers(),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'password.different' => 'The new password must be different from the current password.',
        ];
    }
}
