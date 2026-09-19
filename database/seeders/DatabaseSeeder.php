<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

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
        $this->call([
            // Identity first: accounts, roles/permissions, then employee profiles.
            UserSeeder::class,
            RolePermissionSeeder::class,
            ServiceSeeder::class,
            EmployeeSeeder::class,

            // Business data (resolves employees by name — must run after EmployeeSeeder).
            CompanySeeder::class,
            ContractSeeder::class,
            ServerSubscriptionSeeder::class,
            SocialMediaSeeder::class,
        ]);
    }
}
