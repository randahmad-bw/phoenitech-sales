<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for User model serialization.
 */
class UserResource extends JsonResource
{
    /**
     * Transform user model into API response array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'is_active' => (bool) $this->is_active,
            'must_change_password' => (bool) $this->must_change_password,
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
            'employee' => $this->employee ? new EmployeeResource($this->employee) : null,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
