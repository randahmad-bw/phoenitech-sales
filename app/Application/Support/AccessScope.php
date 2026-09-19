<?php

namespace App\Application\Support;

use App\Models\User;

/**
 * Central helper for "view_own vs view_all" data scoping.
 *
 * Route-level permission middleware decides *whether* a user may hit an
 * endpoint; this decides *which rows* they may see once inside. Keeping the
 * rule here means every module (and the upcoming attendance module) scopes the
 * same way instead of re-inventing a heuristic per controller.
 */
class AccessScope
{
    /**
     * The employee id a listing must be restricted to, or null for no restriction.
     *
     * Returns null when the user holds the "view all" permission (sees everything).
     * Otherwise returns the user's own linked employee id — or -1 when the account
     * has no employee profile, so a restricted query safely matches nothing.
     */
    public static function ownEmployeeId(?User $user, string $viewAllPermission): ?int
    {
        if ($user && $user->can($viewAllPermission)) {
            return null;
        }

        return $user?->employee?->id ?? -1;
    }

    /**
     * Whether the user may act on records belonging to a specific employee.
     *
     * True when they can view all, or when the target employee is their own
     * profile. Used to guard the nested /employees/{employee}/… endpoints.
     */
    public static function canAccessEmployee(?User $user, int $employeeId, string $viewAllPermission): bool
    {
        if ($user && $user->can($viewAllPermission)) {
            return true;
        }

        return $user?->employee?->id === $employeeId;
    }
}
