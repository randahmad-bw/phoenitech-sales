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
 * this one). Every employee now has an account: design and photography staff
 * were profile-only until the attendance module, which needs a login per
 * person to record a check-in against.
 *
 * Seed password: override in any non-local environment with SEED_PASSWORD.
 * Each account is hashed individually, so no two rows share a hash even when
 * the plaintext matches (this fixes the duplicated-hash issue in the old data).
 *
 * Which role each account gets is decided in RolePermissionSeeder, which runs
 * next and is the only place that decides it. This file used to carry a `role`
 * hint beside every row that nothing ever read — it had already drifted out of
 * date, which is what a second copy of a fact tends to do.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('SEED_PASSWORD', 'password');

        $users = [
            // Management.
            ['name' => 'الإدارة', 'email' => 'info@phoenitech.sy'],
            ['name' => 'System Admin', 'email' => 'admin@phoenitech.sy'],
            ['name' => 'Antoine Haddad', 'email' => 'antoine.haddad@phoenitech.sy'],
            ['name' => 'Rand Ahmad', 'email' => 'rand.ahmad@phoenitech.sy'],

            // Sales.
            ['name' => 'سارة حسون', 'email' => 'sara@phoenitech.sy'],
            ['name' => 'مايكل حبيب', 'email' => 'michael@phoenitech.sy'],

            // Everyone producing the work. They were profile-only until the
            // attendance module, which needs a login per person to record a
            // check-in against.
            ['name' => 'حلا شنديعة', 'email' => 'hla.shindeah@phoenitech.sy'],
            ['name' => 'أيمن شعبان', 'email' => 'ayman.shaaban@phoenitech.sy'],
            ['name' => 'سابين بربهان', 'email' => 'sabine.barbahan@phoenitech.sy'],
            ['name' => 'ماجد', 'email' => 'majed@phoenitech.sy'],

            // Added 2026-09-20 with their working schedules.
            ['name' => 'كمال', 'email' => 'kamal@phoenitech.sy'],
            ['name' => 'عمر', 'email' => 'omar@phoenitech.sy'],
            ['name' => 'زين', 'email' => 'zain@phoenitech.sy'],
            ['name' => 'مروان', 'email' => 'marwan@phoenitech.sy'],
            ['name' => 'نوال', 'email' => 'nawal@phoenitech.sy'],
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
