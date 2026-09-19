<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds employee profiles to mirror the real database.
 *
 * Login accounts are owned by UserSeeder (which runs first). This seeder only
 * links a profile to its account via `user_id` when an account exists; design
 * and photography staff are profile-only until they are granted accounts in a
 * later phase.
 *
 * Employee names are kept exactly as the live database has them because the
 * Company and Contract seeders resolve their assigned employee by name prefix
 * (e.g. "سارة", "مايكل", "الإدارة"). Renaming here would break those lookups.
 */
class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        // email => the login account this profile belongs to (null = no account yet).
        $employees = [
            [
                'name' => 'سارة',
                'phone' => '+963932735439',
                'email' => 'sara@phoenitech.sy',
                'department' => 'sales',
                'employment_date' => '2026-01-01',
                'account_email' => 'sara@phoenitech.sy',
            ],
            [
                'name' => 'مايكل',
                'phone' => '+963985763524',
                'email' => 'michael@phoenitech.sy',
                'department' => 'sales',
                'employment_date' => '2025-12-01',
                'account_email' => 'michael@phoenitech.sy',
            ],
            [
                'name' => 'الإدارة',
                'phone' => null,
                'email' => 'info@phoenitech.sy',
                'department' => 'management',
                'employment_date' => '2025-01-01',
                'account_email' => 'info@phoenitech.sy',
            ],
            [
                'name' => 'حلا',
                'phone' => '+963991423345',
                'email' => 'hla.shindeah@phoenitech.sy',
                'department' => 'design',
                'employment_date' => '2024-01-01',
                'account_email' => null,
            ],
            [
                'name' => 'أيمن',
                'phone' => null,
                'email' => 'ayman.shaaban@phoenitech.sy',
                'department' => 'design',
                'employment_date' => null,
                'account_email' => null,
            ],
            [
                'name' => 'سابين',
                'phone' => '+963994341382',
                'email' => 'sabine.barbahan@phoenitech.sy',
                'department' => 'design',
                'employment_date' => '2026-05-09',
                'account_email' => null,
            ],
            [
                'name' => 'ماجد',
                'phone' => null,
                'email' => null,
                'department' => 'photography',
                'employment_date' => null,
                'account_email' => null,
            ],
        ];

        foreach ($employees as $data) {
            $accountEmail = $data['account_email'];
            unset($data['account_email']);

            $data['user_id'] = $accountEmail
                ? User::where('email', $accountEmail)->value('id')
                : null;

            Employee::updateOrCreate(
                ['name' => $data['name']],
                $data
            );
        }
    }
}
