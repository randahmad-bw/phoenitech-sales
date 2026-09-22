<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds employee profiles to mirror the real database.
 *
 * Login accounts are owned by UserSeeder (which runs first). This seeder only
 * links a profile to its account via `user_id`. Every profile now has one:
 * the attendance module needs a login per person to record a check-in against,
 * so design and photography staff are no longer profile-only.
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
                'account_email' => 'hla.shindeah@phoenitech.sy',
            ],
            [
                'name' => 'أيمن',
                'phone' => null,
                'email' => 'ayman.shaaban@phoenitech.sy',
                'department' => 'design',
                'employment_date' => null,
                'account_email' => 'ayman.shaaban@phoenitech.sy',
            ],
            [
                'name' => 'سابين',
                'phone' => '+963994341382',
                'email' => 'sabine.barbahan@phoenitech.sy',
                'department' => 'design',
                'employment_date' => '2026-05-09',
                'account_email' => 'sabine.barbahan@phoenitech.sy',
            ],
            [
                'name' => 'ماجد',
                'phone' => null,
                'email' => 'majed@phoenitech.sy',
                'department' => 'photography',
                'employment_date' => null,
                'account_email' => 'majed@phoenitech.sy',
            ],

            // Added 2026-09-20 together with their working schedules
            // (WorkScheduleSeeder resolves them by name).
            //
            // `department` is 'design' as a stated assumption, not a fact:
            // the real departments were not supplied. All five are on the
            // attendance system, which rules out sales and management, and
            // their two peers on the same schedules are designers. Correct it
            // from the employees screen — nothing else depends on it.
            [
                'name' => 'كمال',
                'phone' => null,
                'email' => 'kamal@phoenitech.sy',
                'department' => 'design',
                'employment_date' => null,
                'account_email' => 'kamal@phoenitech.sy',
            ],
            [
                'name' => 'عمر',
                'phone' => null,
                'email' => 'omar@phoenitech.sy',
                'department' => 'design',
                'employment_date' => null,
                'account_email' => 'omar@phoenitech.sy',
            ],
            [
                'name' => 'زين',
                'phone' => null,
                'email' => 'zain@phoenitech.sy',
                'department' => 'design',
                'employment_date' => null,
                'account_email' => 'zain@phoenitech.sy',
            ],
            [
                'name' => 'مروان',
                'phone' => null,
                'email' => 'marwan@phoenitech.sy',
                'department' => 'design',
                'employment_date' => null,
                'account_email' => 'marwan@phoenitech.sy',
            ],
            [
                'name' => 'نوال',
                'phone' => null,
                'email' => 'nawal@phoenitech.sy',
                'department' => 'design',
                'employment_date' => null,
                'account_email' => 'nawal@phoenitech.sy',
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
