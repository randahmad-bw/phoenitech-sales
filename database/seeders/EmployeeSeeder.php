<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds sample employee records for development and testing.
 */
class EmployeeSeeder extends Seeder
{
    /**
     * Insert sample employee records.
     */
    public function run(): void
    {
        $employees = [
            [
                'name' => 'سارة حسون',
                'phone' => '+963932735439',
                'email' => 'sara@phoenitech.com',
                'employment_date' => '2026-01-01',
            ],
            [
                'name' => 'مايكل حبيب',
                'phone' => '+963985763524',
                'email' => 'michael@phoenitech.com',
                'employment_date' => '2025-12-01',
            ],
            [
                'name' => 'الإدارة',
                'phone' => null,
                'email' => 'info@phoenitech.com',
                'employment_date' => '2025-01-01',
            ],
        ];

        foreach ($employees as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('password'),
                ]
            );

            Employee::updateOrCreate(
                ['email' => $data['email']],
                [
                    'user_id' => $user->id,
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'email' => $data['email'],
                    'employment_date' => $data['employment_date'],
                ]
            );
        }
    }
}
