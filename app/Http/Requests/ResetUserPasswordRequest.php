<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rules\Password;

/**
 * Validates an administrator resetting another user's password.
 */
class ResetUserPasswordRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }
}
