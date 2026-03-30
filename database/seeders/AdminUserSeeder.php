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
        $this->insertSuperAdmin();
    }

    protected function insertSuperAdmin(): void
    {
        $superAdminData = \Arr::wrap(config('dev-plug.defaults.users.superadmin'));

        $superAdminData['name'] = ($superAdminData['name'] ?? null) ?: 'System Admin';
        $superAdminData['email'] = ($superAdminData['email'] ?? null) ?: 'admin@mail.com';
        $superAdminData['password'] ??= null;

        if (!$superAdminData['password']) {
            $superAdminData['password'] = app()->isProduction() || !config('app.seed-dummy-data', false)
                ? str()->random(10) : 'power@123';
        }

        // Create or update super admin user
        $admin = User::updateOrCreate(
            ['email' => $superAdminData['email']],
            [
                'name' => $superAdminData['name'],
                'email' => $superAdminData['email'],
                'password' => Hash::make($superAdminData['password']),
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
        $this->command->info('Email: ' . $admin->email);
        $this->command->info('Password: ' . $superAdminData['password']);
    }
}
