<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
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
 *
 * ── On the shape of the role set ────────────────────────────────────────────
 * A role answers one question: *what may this account do?* It deliberately does
 * NOT answer *what does this person do for a living?* — that is the employee's
 * department, and it lives on the employee record.
 *
 * Keeping the two apart is what stops the set from sprawling. A designer, a
 * developer, a photographer and a video editor do four different jobs and need
 * exactly the same access, so they share one role and differ by department. A
 * sales manager and a salesperson do the same job at different levels and need
 * different access, so they are two roles. The question is never what the work
 * is called, only what the account may reach.
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * The full permission catalog, grouped. Keys are the group label used in
     * the management UI; values are the permission names checked in code.
     */
    private const CATALOG = [
        'users' => ['users.view', 'users.create', 'users.edit', 'users.delete', 'users.activate'],
        'roles' => ['roles.view', 'roles.create', 'roles.edit', 'roles.delete', 'roles.assign_permissions'],
        'employees' => ['employees.view_all', 'employees.view_own', 'employees.create', 'employees.edit', 'employees.delete', 'employees.view_salary'],
        'companies' => ['companies.view', 'companies.create', 'companies.edit', 'companies.delete'],
        'contracts' => ['contracts.view_all', 'contracts.view_own', 'contracts.create', 'contracts.edit', 'contracts.delete', 'contracts.renew'],
        'payments' => ['payments.view', 'payments.create', 'payments.edit', 'payments.delete'],
        'subscriptions' => ['subscriptions.view', 'subscriptions.create', 'subscriptions.edit', 'subscriptions.delete', 'subscriptions.renew'],
        'social_media' => ['social_media.view', 'social_media.create', 'social_media.edit', 'social_media.delete'],
        'leave' => ['leave.view_all', 'leave.view_own', 'leave.create', 'leave.approve', 'leave.reject'],
        'overtime' => ['overtime.view_all', 'overtime.view_own', 'overtime.create', 'overtime.approve', 'overtime.reject'],
        'reports' => ['reports.view', 'reports.export'],
        'weekly_reports' => ['weekly_reports.view_all', 'weekly_reports.view_own', 'weekly_reports.create', 'weekly_reports.delete'],
        // The landing dashboard aggregates company financials — contract values,
        // payments, revenue. It is therefore a permission of its own rather than
        // something every authenticated account gets: attendance-only staff have
        // no business reason to see the company's numbers.
        'dashboard' => ['dashboard.view'],
        'settings' => ['settings.view', 'settings.edit'],
        'audit' => ['audit.view'],
        // Attendance module. `create` is the employee's own check-in/check-out —
        // there is no separate check_in/check_out permission because nobody is
        // ever granted one without the other. `manage_schedules` is kept apart
        // from `edit`: correcting one day's record is routine, while redefining
        // someone's working week is not.
        'attendance' => ['attendance.view_all', 'attendance.view_own', 'attendance.create', 'attendance.edit', 'attendance.delete', 'attendance.approve', 'attendance.manage_schedules'],
        // Tasks. `view_own` is what everyone on the payroll holds — their own
        // plate — while `view_all` is the manager's board. Moving a task is
        // deliberately NOT behind `edit`: reporting progress on your own work
        // is not the same act as reassigning it or changing its deadline, so
        // the status route sits behind `view_own` instead.
        'tasks' => ['tasks.view_all', 'tasks.view_own', 'tasks.create', 'tasks.edit', 'tasks.delete'],
        // Notifications are the one thing every account holds: a notice is
        // always about the holder's own work, and the endpoints only ever read
        // the caller's feed. It is still a permission rather than "any logged-in
        // user" so the bell can be taken away from a role without a code change,
        // and so the catalog remains the single list of what exists.
        'notifications' => ['notifications.view'],
    ];

    /**
     * Roles that no longer exist, and what replaced them.
     *
     * Renaming rather than recreating matters: spatie keys both
     * `role_has_permissions` and `model_has_roles` on the role id, so a rename
     * carries every existing assignment across untouched.
     */
    private const LEGACY_RENAMES = [
        // `employee` was the sales role while everyone else on the payroll was
        // `staff` — so the word "employee" meant the sales team in the role
        // table and the whole company in the employees table.
        'employee' => 'sales',
        'staff' => 'team',
        'manager' => 'general_manager',
    ];

    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);

        // Flush the cached permission map BEFORE reading it: the cache store
        // (CACHE_STORE=database, 24h TTL) outlives `migrate:fresh`, and a stale
        // map makes findOrCreate() try to insert a permission that already
        // exists, which then trips the name+guard unique index.
        $registrar->forgetCachedPermissions();

        $guard = config('auth.defaults.guard', 'web');

        // Carry the previous role set over before anything below resolves a
        // role by name, so nobody is stripped of the access they already have.
        $this->migrateLegacyRoles($guard);

        // 1) Ensure every permission exists.
        $allPermissions = [];
        foreach (self::CATALOG as $names) {
            foreach ($names as $name) {
                Permission::findOrCreate($name, $guard);
                $allPermissions[] = $name;
            }
        }

        // Flush again before anything resolves a permission by name. DatabaseSeeder
        // runs under WithoutModelEvents, so creating a Permission does not fire the
        // `saved` hook that normally refreshes spatie's cache: without this the
        // registrar still holds the empty map it loaded above and every
        // syncPermissions() below throws PermissionDoesNotExist.
        $registrar->forgetCachedPermissions();

        // 2) Define the baseline roles and the permission sets they receive.
        $roles = [
            // Owns the system. Also bypasses every check via Gate::before.
            'super_admin' => $allPermissions,

            /*
             * Runs the company and sees every part of the work — but not the
             * accounts. Creating logins and granting permissions stays with the
             * owner, and that separation is the whole point of the role: the
             * person who runs the business should not also be the person who
             * can quietly widen their own access.
             */
            'general_manager' => array_values(array_diff($allPermissions, [
                'users.view', 'users.create', 'users.edit', 'users.delete', 'users.activate',
                'roles.view', 'roles.create', 'roles.edit', 'roles.delete', 'roles.assign_permissions',
            ])),

            // Owns the commercial side end to end: every client, contract and
            // payment, plus the leave and attendance of the people under them.
            'sales_manager' => [
                'dashboard.view',
                'employees.view_all', 'employees.view_own',
                'companies.view', 'companies.create', 'companies.edit', 'companies.delete',
                'contracts.view_all', 'contracts.view_own', 'contracts.create', 'contracts.edit', 'contracts.renew',
                'payments.view', 'payments.create', 'payments.edit',
                'subscriptions.view', 'subscriptions.renew',
                'reports.view', 'reports.export',
                'weekly_reports.view_all',
                'leave.view_all', 'leave.view_own', 'leave.create', 'leave.approve', 'leave.reject',
                'overtime.view_all', 'overtime.view_own', 'overtime.approve', 'overtime.reject',
                'attendance.view_all', 'attendance.view_own', 'attendance.create', 'attendance.approve',
                // Runs the team's board, but does not erase its history.
                'tasks.view_all', 'tasks.view_own', 'tasks.create', 'tasks.edit',
                'notifications.view',
            ],

            // A salesperson: their own contracts and weekly reports on top of
            // the self-service basics. Unchanged from the old `employee` role —
            // this is a rename, not a widening of access.
            'sales' => [
                'dashboard.view',
                'employees.view_own',
                'contracts.view_own',
                'leave.view_own', 'leave.create',
                'overtime.view_own', 'overtime.create',
                'attendance.view_own', 'attendance.create',
                'weekly_reports.view_own', 'weekly_reports.create',
                'tasks.view_own',
                'notifications.view',
            ],

            /*
             * Marketing owns the social-media module, and that is the only
             * reason it is a role of its own rather than a department on top of
             * `team`: the access genuinely differs. A department that needs no
             * different access does not get a role.
             */
            'marketing' => [
                'employees.view_own',
                'social_media.view', 'social_media.create', 'social_media.edit',
                'leave.view_own', 'leave.create',
                'overtime.view_own',
                'attendance.view_own', 'attendance.create',
                'tasks.view_own',
                'notifications.view',
            ],

            /*
             * Everyone who produces the work — design, development, photography,
             * video. One role, because their access is identical; their
             * department is what tells them apart.
             *
             * Deliberately no contracts and no dashboard: the commercial side
             * is not their business, and the landing dashboard is company
             * financials. They land on the attendance screen instead, which is
             * the only thing they need the system for.
             */
            'team' => [
                'employees.view_own',
                'attendance.view_own', 'attendance.create',
                'leave.view_own', 'leave.create',
                'overtime.view_own',
                'tasks.view_own',
                'notifications.view',
            ],
        ];

        foreach ($roles as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, $guard);
            $role->syncPermissions($permissions);
        }

        // 3) Assign roles to the seeded accounts (idempotent).
        $assignments = [
            // The owner, and the technical account kept as a way back in.
            'info@phoenitech.sy' => 'super_admin',
            'admin@phoenitech.sy' => 'super_admin',

            // Run the work, not the accounts.
            'antoine.haddad@phoenitech.sy' => 'general_manager',
            'rand.ahmad@phoenitech.sy' => 'general_manager',

            // Sales.
            'sara@phoenitech.sy' => 'sales',
            'michael@phoenitech.sy' => 'sales',

            // Everyone producing the work; their department is on the employee
            // record, not here.
            'hla.shindeah@phoenitech.sy' => 'team',
            'ayman.shaaban@phoenitech.sy' => 'team',
            'sabine.barbahan@phoenitech.sy' => 'team',
            'majed@phoenitech.sy' => 'team',
            'kamal@phoenitech.sy' => 'team',
            'omar@phoenitech.sy' => 'team',
            'zain@phoenitech.sy' => 'team',
            'marwan@phoenitech.sy' => 'team',
            'nawal@phoenitech.sy' => 'team',
        ];

        foreach ($assignments as $email => $roleName) {
            $user = User::where('email', $email)->first();
            if ($user) {
                $user->syncRoles([$roleName]);
            }
        }

        // Clear the cached permission map so the new data takes effect at once.
        $registrar->forgetCachedPermissions();
    }

    /**
     * Move the previous role set onto the current one, in place.
     *
     * Runs before the roles are (re)created so that a rename never collides
     * with a freshly made row of the same name.
     */
    private function migrateLegacyRoles(string $guard): void
    {
        foreach (self::LEGACY_RENAMES as $from => $to) {
            $legacy = Role::where('name', $from)->where('guard_name', $guard)->first();

            if (! $legacy) {
                continue;
            }

            $this->replaceRole($legacy, $to, $guard);
        }

        /*
         * `admin` held exactly the same permissions as `super_admin`, so it drew
         * a distinction the system never actually enforced — two names for one
         * level of access. Its people move to general_manager, which is the
         * level that was really meant.
         */
        $admin = Role::where('name', 'admin')->where('guard_name', $guard)->first();

        if ($admin) {
            $this->replaceRole($admin, 'general_manager', $guard);
        }

        // `hr` carried 24 permissions and was never assigned to anyone. Dropped
        // rather than kept "just in case": an unused role with real access is a
        // standing invitation to hand it out without thinking.
        Role::where('name', 'hr')
            ->where('guard_name', $guard)
            ->whereDoesntHave('users')
            ->delete();
    }

    /**
     * Rename $legacy to $to, or — when $to already exists — move its users
     * across and drop it. The second case matters because name+guard is a
     * unique index, so a blind rename would fail.
     */
    private function replaceRole(Role $legacy, string $to, string $guard): void
    {
        $target = Role::where('name', $to)->where('guard_name', $guard)->first();

        if (! $target) {
            $legacy->update(['name' => $to]);

            return;
        }

        foreach ($legacy->users as $user) {
            $user->assignRole($target);
        }

        $legacy->delete();
    }
}
