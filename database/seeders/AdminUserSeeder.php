<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create or update super admin user
        $admin = User::updateOrCreate(
            ['email' => 'admin@mail.com'],
            [
                'name' => 'Admin User',
                'email' => 'admin@mail.com',
                'password' => Hash::make('power@123'),
                'email_verified_at' => now(),
            ]
        );

        if (class_exists(Role::class)) {
            // Sync super_admin role (removes other roles and assigns super_admin)
            $superAdminRole = Role::firstWhere('name', 'super_admin');

            if ($superAdminRole) {
                $admin->syncRoles([$superAdminRole]);
            }
        }

        $this->command->info('Admin user created/updated successfully!');
        $this->command->info('Email: admin@mail.com');
        $this->command->info('Password: power@123');
    }
}
