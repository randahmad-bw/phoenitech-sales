<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\RoleService;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;

/**
 * Admin management of roles and their permission sets.
 */
class RoleController extends Controller
{
    public function __construct(private RoleService $service) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success(RoleResource::collection($this->service->list()), 'Roles retrieved.');
    }

    /**
     * The full permission catalog, grouped, for building the role editor.
     */
    public function permissions(): JsonResponse
    {
        return ApiResponse::success($this->service->permissionCatalog(), 'Permissions retrieved.');
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = $this->service->create($request->validated());
        return ApiResponse::created(new RoleResource($role), 'Role created.');
    }

    public function show(Role $role): JsonResponse
    {
        return ApiResponse::success(new RoleResource($this->service->find($role->id)));
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $role = $this->service->update($role, $request->validated());
        return ApiResponse::success(new RoleResource($role), 'Role updated.');
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->service->delete($role);
        return ApiResponse::success(null, 'Role deleted.');
    }
}
