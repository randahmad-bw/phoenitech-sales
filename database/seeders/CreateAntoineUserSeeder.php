<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Temporary seeder to create Super Admin user: antoine.haddad@phoenitech.sy
 * Run using: php artisan db:seed --class=CreateAntoineUserSeeder
 */
class CreateAntoineUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'antoine.haddad@phoenitech.sy'],
            [
                'name' => 'Antoine Haddad',
                'password' => Hash::make('12345678'),
            ]
        );

        Employee::updateOrCreate(
            ['email' => 'antoine.haddad@phoenitech.sy'],
            [
                'user_id' => $user->id,
                'name' => 'Antoine Haddad',
                'phone' => '+963900000000',
                'email' => 'antoine.haddad@phoenitech.sy',
                'department' => 'management',
                'employment_date' => '2026-01-01',
            ]
        );
    }
}
