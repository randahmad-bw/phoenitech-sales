<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds the login accounts (users table) that mirror the real database.
 *
 * Accounts are the single source of truth for authentication. Employee
 * profiles are linked to these accounts by EmployeeSeeder (which runs after
 * this one). Not every employee has an account — design/photography staff are
 * profile-only for now and gain accounts in a later phase.
 *
 * Seed password: override in any non-local environment with SEED_PASSWORD.
 * Each account is hashed individually, so no two rows share a hash even when
 * the plaintext matches (this fixes the duplicated-hash issue in the old data).
 *
 * Role assignment (super_admin / admin / …) is applied once the roles &
 * permissions layer is installed; see the `role` hint on each row below.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('SEED_PASSWORD', 'password');

        // Roles themselves are assigned by RolePermissionSeeder (runs next); the
        // `role` key here documents the intended mapping and is not applied here.
        $users = [
            // Super Admin — the management account, full access to the whole system.
            ['name' => 'الإدارة',         'email' => 'info@phoenitech.sy',           'role' => 'super_admin'],

            // Administrators.
            ['name' => 'Antoine Haddad',  'email' => 'antoine.haddad@phoenitech.sy', 'role' => 'admin'],
            ['name' => 'System Admin',    'email' => 'admin@phoenitech.sy',          'role' => 'admin'],

            // Operations Manager.
            ['name' => 'Rand Ahmad',      'email' => 'rand.ahmad@phoenitech.sy',     'role' => 'manager'],

            // Team members with login access (linked to employee profiles).
            ['name' => 'سارة حسون',       'email' => 'sara@phoenitech.sy',           'role' => 'employee'],
            ['name' => 'مايكل حبيب',      'email' => 'michael@phoenitech.sy',        'role' => 'employee'],
        ];

        foreach ($users as $data) {
            User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make($password),
                    'is_active' => true,
                ]
            );
        }
    }
}
