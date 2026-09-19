<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\UserManagementService;
use App\Http\Controllers\Controller;
use App\Http\Requests\ResetUserPasswordRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserListResource;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin management of user accounts: CRUD, role assignment, activation, and
 * password reset. Authorization is enforced by users.* permission middleware
 * on the routes; business-rule guards live in UserManagementService.
 */
class UserController extends Controller
{
    public function __construct(private UserManagementService $service) {}

    public function index(Request $request): JsonResponse
    {
        $users = $this->service->list($request->all());
        return ApiResponse::paginated(UserListResource::collection($users));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->service->create($request->validated());
        return ApiResponse::created(new UserResource($user), 'User created.');
    }

    public function show(User $user): JsonResponse
    {
        return ApiResponse::success(new UserResource($this->service->find($user->id)));
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $user = $this->service->update($user, $request->validated());
        return ApiResponse::success(new UserResource($user), 'User updated.');
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->service->delete($user, $request->user());
        return ApiResponse::success(null, 'User deleted.');
    }

    /**
     * Enable or disable a user account (PATCH body: { "is_active": bool }).
     */
    public function setActive(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);
        $user = $this->service->setActive($user, $validated['is_active'], $request->user());

        return ApiResponse::success(new UserResource($user), 'User status updated.');
    }

    /**
     * Admin resets a user's password. The user is signed out everywhere and must
     * change the password on next login.
     */
    public function resetPassword(ResetUserPasswordRequest $request, User $user): JsonResponse
    {
        $this->service->resetPassword($user, $request->input('password'));
        return ApiResponse::success(null, 'Password reset. The user must change it on next login.');
    }
}
