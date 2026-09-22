<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level authorization guard.
 *
 * Usage: ->middleware('permission:contracts.create')
 *        ->middleware('permission:employees.view_all|employees.view_own')
 *
 * Passing several permissions separated by "|" grants access if the user holds
 * ANY of them (useful for "view_all OR view_own" style endpoints, where the
 * service layer then scopes the data). A super_admin passes everything via the
 * Gate::before hook in AppServiceProvider.
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::unauthorized('Unauthenticated.');
        }

        $required = array_filter(array_map('trim', explode('|', $permissions)));

        foreach ($required as $permission) {
            if ($user->can($permission)) {
                return $next($request);
            }
        }

        return ApiResponse::forbidden(
            'You do not have permission to perform this action.'
        );
    }
}
