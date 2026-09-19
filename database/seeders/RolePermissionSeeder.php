<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the permission catalog, the baseline roles, their permission sets, and
 * assigns roles to the seeded accounts.
 *
 * This is data, not code: permissions per role are meant to be edited later
 * from the admin dashboard without touching this file. It is re-runnable
 * (permissions/roles are found-or-created and permission sets are re-synced).
 *
 * The `super_admin` role is granted every permission here AND bypasses all
 * checks via Gate::before (see AppServiceProvider), so it can never be locked
 * out even if a permission is removed from the catalog.
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * The full permission catalog, grouped. Keys are the group label used in
     * the management UI; values are the permission names checked in code.
     */
    private const CATALOG = [
        'users'          => ['users.view', 'users.create', 'users.edit', 'users.delete', 'users.activate'],
        'roles'          => ['roles.view', 'roles.create', 'roles.edit', 'roles.delete', 'roles.assign_permissions'],
        'employees'      => ['employees.view_all', 'employees.view_own', 'employees.create', 'employees.edit', 'employees.delete', 'employees.view_salary'],
        'companies'      => ['companies.view', 'companies.create', 'companies.edit', 'companies.delete'],
        'contracts'      => ['contracts.view_all', 'contracts.view_own', 'contracts.create', 'contracts.edit', 'contracts.delete', 'contracts.renew'],
        'payments'       => ['payments.view', 'payments.create', 'payments.edit', 'payments.delete'],
        'subscriptions'  => ['subscriptions.view', 'subscriptions.create', 'subscriptions.edit', 'subscriptions.delete', 'subscriptions.renew'],
        'social_media'   => ['social_media.view', 'social_media.create', 'social_media.edit', 'social_media.delete'],
        'leave'          => ['leave.view_all', 'leave.view_own', 'leave.create', 'leave.approve', 'leave.reject'],
        'overtime'       => ['overtime.view_all', 'overtime.view_own', 'overtime.create', 'overtime.approve', 'overtime.reject'],
        'reports'        => ['reports.view', 'reports.export'],
        'weekly_reports' => ['weekly_reports.view_all', 'weekly_reports.view_own', 'weekly_reports.create', 'weekly_reports.delete'],
        'settings'       => ['settings.view', 'settings.edit'],
        'audit'          => ['audit.view'],
        // Reserved for the upcoming attendance module — seeded now so the
        // roles are ready and the catalog is stable.
        'attendance'     => ['attendance.view_all', 'attendance.view_own', 'attendance.create', 'attendance.edit', 'attendance.delete', 'attendance.approve'],
    ];

    public function run(): void
    {
        $guard = config('auth.defaults.guard', 'web');

        // 1) Ensure every permission exists.
        $allPermissions = [];
        foreach (self::CATALOG as $names) {
            foreach ($names as $name) {
                Permission::findOrCreate($name, $guard);
                $allPermissions[] = $name;
            }
        }

        // 2) Define the baseline roles and the permission sets they receive.
        //    super_admin is handled separately (gets everything).
        $roles = [
            'super_admin' => $allPermissions,
            'admin'       => $allPermissions,
            'hr' => [
                'employees.view_all', 'employees.view_own', 'employees.create', 'employees.edit', 'employees.view_salary',
                'leave.view_all', 'leave.view_own', 'leave.create', 'leave.approve', 'leave.reject',
                'overtime.view_all', 'overtime.view_own', 'overtime.create', 'overtime.approve', 'overtime.reject',
                'attendance.view_all', 'attendance.view_own', 'attendance.create', 'attendance.edit', 'attendance.approve',
                'reports.view', 'reports.export',
                'weekly_reports.view_all',
            ],
            'manager' => [
                'employees.view_all', 'employees.view_own',
                'companies.view', 'companies.create', 'companies.edit',
                'contracts.view_all', 'contracts.view_own', 'contracts.create', 'contracts.edit', 'contracts.renew',
                'payments.view',
                'subscriptions.view', 'subscriptions.renew',
                'social_media.view', 'social_media.create', 'social_media.edit',
                'leave.view_all', 'leave.approve', 'leave.reject',
                'overtime.view_all', 'overtime.approve', 'overtime.reject',
                'attendance.view_all', 'attendance.approve',
                'reports.view', 'reports.export',
                'weekly_reports.view_all',
            ],
            'employee' => [
                'employees.view_own',
                'contracts.view_own',
                'leave.view_own', 'leave.create',
                'overtime.view_own', 'overtime.create',
                'attendance.view_own', 'attendance.create',
                'weekly_reports.view_own', 'weekly_reports.create',
            ],
        ];

        foreach ($roles as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, $guard);
            $role->syncPermissions($permissions);
        }

        // 3) Assign roles to the seeded accounts (idempotent).
        $assignments = [
            'info@phoenitech.sy'          => 'super_admin',
            'antoine.haddad@phoenitech.sy' => 'admin',
            'admin@phoenitech.sy'          => 'admin',
            'rand.ahmad@phoenitech.sy'     => 'manager',
            'sara@phoenitech.sy'           => 'employee',
            'michael@phoenitech.sy'        => 'employee',
        ];

        foreach ($assignments as $email => $roleName) {
            $user = User::where('email', $email)->first();
            if ($user) {
                $user->syncRoles([$roleName]);
            }
        }

        // Clear the cached permission map so the new data takes effect at once.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
