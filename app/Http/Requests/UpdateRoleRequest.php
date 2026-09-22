<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * Validates updating a role's name and/or permission set.
 */
class UpdateRoleRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $roleId = $this->route('role')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:255', 'regex:/^[a-z0-9_]+$/', Rule::unique('roles', 'name')->ignore($roleId)],
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
