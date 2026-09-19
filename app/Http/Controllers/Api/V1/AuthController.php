<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Support\AuditLogger;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Handles API authentication: login, logout, current user, and password change.
 */
class AuthController extends Controller
{
    /**
     * Authenticate by email OR username and return a Sanctum API token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $login = $request->input('email');
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $user = User::where($field, $login)->first();

        // Verify the password even when the user is missing, against a dummy hash,
        // so response timing does not reveal whether the account exists.
        $passwordValid = $user
            ? Hash::check($request->input('password'), $user->password)
            : Hash::check($request->input('password'), '$2y$12$'.str_repeat('.', 53));

        if (! $user || ! $passwordValid) {
            // Recorded even when the identifier matches no account — a burst of
            // these against unknown identifiers is what credential stuffing
            // looks like in the trail.
            AuditLogger::record(
                AuditLog::EVENT_LOGIN_FAILED,
                $user,
                null,
                ['identifier' => $login, 'reason' => $user ? 'invalid_password' : 'unknown_identifier'],
                actor: $user,
            );

            return ApiResponse::unauthorized('Invalid credentials.');
        }

        if ($user->is_active === false) {
            AuditLogger::record(
                AuditLog::EVENT_LOGIN_BLOCKED,
                $user,
                null,
                ['identifier' => $login, 'reason' => 'account_disabled'],
                actor: $user,
            );

            return ApiResponse::forbidden('Your account has been disabled. Please contact an administrator.');
        }

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $user->load('employee');
        $token = $user->createToken('api-token')->plainTextToken;

        AuditLogger::record(
            AuditLog::EVENT_LOGIN,
            $user,
            null,
            ['via' => $field],
            actor: $user,
        );

        return ApiResponse::success([
            'token' => $token,
            'user' => new UserResource($user),
        ], 'Login successful.');
    }

    /**
     * Revoke the current API token and log out.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        $token = $user->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        AuditLogger::record(AuditLog::EVENT_LOGOUT, $user, actor: $user);

        return ApiResponse::success(null, 'Logged out successfully.');
    }

    /**
     * Return the currently authenticated user's profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('employee');

        return ApiResponse::success(
            new UserResource($user),
            'User retrieved.'
        );
    }

    /**
     * Change the authenticated user's own password.
     *
     * Verifies the current password, then updates it, clears any
     * force-change flag, and revokes every other token so other sessions are
     * signed out. The caller's current token is kept so they stay logged in.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->input('current_password'), $user->password)) {
            return ApiResponse::error('The current password is incorrect.', 'INVALID_CURRENT_PASSWORD', 422);
        }

        $user->forceFill([
            'password' => $request->input('password'),
            'must_change_password' => false,
        ])->save();

        // Sign out other sessions/devices; keep the token used for this request.
        $currentTokenId = $user->currentAccessToken()->id;
        $user->tokens()->where('id', '!=', $currentTokenId)->delete();

        // The password itself is never stored in the trail — only the fact it changed.
        AuditLogger::record(AuditLog::EVENT_PASSWORD_CHANGED, $user, actor: $user);

        return ApiResponse::success(null, 'Password changed successfully.');
    }
}
