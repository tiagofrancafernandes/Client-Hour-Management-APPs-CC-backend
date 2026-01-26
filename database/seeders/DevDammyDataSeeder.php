<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DevDammyDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $this->dummyUsers();
    }

    protected function dummyUsers()
    {
        $users = [
            [
                'name' => 'Customer1',
                'email' => 'customer1@mail.com',
                'password' => 'power@123',
                'email_verified_at' => now(),
                'role' => 'customer',
            ],
        ];

        foreach ($users as $userData) {
            if (!isset($userData['email'])) {
                continue;
            }

            $role = $userData['role'] ?? null;
            $password = $userData['password'] ?? 'power@123';
            $userData = \Arr::except($userData, ['role']);
            $userData['password'] = Hash::make($password);

            $user = User::updateOrCreate(
                [
                    'email' => $userData['email']
                ],
                $userData,
            );

            $this->command->newLine();
            $this->command->info("Email: {$user?->email}");
            $this->command->info("Password: {$password}");

            if (!$role || !is_string($role) || !class_exists(Role::class)) {
                continue;
            }

            $userRole = Role::firstWhere('name', $role);

            if (!$userRole || $user->hasRole('super_admin')) {
                continue;
            }

            $user->syncRoles($userRole);

            $this->command->info("Role: {$userRole?->name}");
        }
    }
}
