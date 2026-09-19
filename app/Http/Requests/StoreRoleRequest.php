<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * Validates creation of a new role and its permission set.
 */
class StoreRoleRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_]+$/', Rule::unique('roles', 'name')],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'The role name may only contain lowercase letters, numbers, and underscores.',
        ];
    }
}
