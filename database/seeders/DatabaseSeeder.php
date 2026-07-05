<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed roles and permissions first
        $this->call(RolesAndPermissionsSeeder::class);

        // Seed payment method configurations
        $this->call(PaymentMethodConfigSeeder::class);

        // Seed admin users
        $this->call(AdminUserSeeder::class);

        $this->call(DevDummyDataSeeder::class);
    }
}
