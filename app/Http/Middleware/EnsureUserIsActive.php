<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects requests from disabled accounts.
 *
 * A user can be deactivated (is_active = false) without deleting the record or
 * its history. This runs right after authentication so a disabled account is
 * blocked from every protected endpoint even if it still holds a valid token.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->is_active === false) {
            return ApiResponse::forbidden('Your account has been disabled. Please contact an administrator.');
        }

        return $next($request);
    }
}
