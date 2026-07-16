<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Master database seeder. Calls all domain seeders in dependency order.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application database with default data.
     */
    public function run(): void
    {
        // Admin user
        User::updateOrCreate(
            ['email' => 'admin@phoenitech.com'],
            [
                'name' => 'System Admin',
                'password' => Hash::make('password'),
            ]
        );

        // Domain seeders in dependency order
        $this->call([
            ServiceSeeder::class,
            EmployeeSeeder::class,
            CompanySeeder::class,
            ContractSeeder::class,
        ]);
    }
}
