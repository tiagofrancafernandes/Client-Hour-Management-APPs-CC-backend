<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Roles and Permissions Seeder for Hours Ledger System
 *
 * Permission naming convention: namespace.action or namespace.action_modifier
 * Examples:
 *   - client.view       → view own/related clients
 *   - client.view_any   → view all clients (admin)
 *   - ledger.credit     → add credit entries
 *
 * @author Tiago França
 * @copyright (c) 2025
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $isProduction = app()->environment('production');

        // Create Permissions
        $permissions = [
            // Client Management
            'client.view',
            'client.view_any',
            'client.create',
            'client.update',
            'client.delete',

            // User Management
            'user.view',
            'user.view_any',
            'user.create',
            'user.update',
            'user.delete',
            'user.manage_client_users',

            // Wallet Management
            'wallet.view',
            'wallet.view_any',
            'wallet.create',
            'wallet.update',
            'wallet.delete',
            'wallet.view_internal_note',

            // Ledger/Hour Log Operations
            'ledger.view',
            'ledger.view_any',
            'ledger.credit',
            'ledger.debit',
            'ledger.adjust',

            // Tag Management
            'tag.view',
            'tag.view_any',
            'tag.create',
            'tag.update',
            'tag.delete',

            // Timer Management
            'timer.view',
            'timer.view_any',
            'timer.create',
            'timer.update',
            'timer.confirm',
            'timer.delete',

            // Import Management
            'import.view',
            'import.view_any',
            'import.create',
            'import.update',
            'import.confirm',
            'import.cancel',
            'import.delete',

            // Credit Purchases
            'credit_purchase.create',
            'credit_purchase.view',
            'credit_purchase.approve',

            // Reports
            'report.view',
            'report.export',
            'report.export_any',
            'report.view_any',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission], ['name' => $permission]);
        }

        // Create Roles and Assign Permissions

        // Super Admin - Full access to everything
        /** @var Role $superAdmin */
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin'], ['name' => 'super_admin']);
        $superAdmin->syncPermissions(Permission::all());

        // Admin - Full operational access
        /** @var Role $admin */
        $admin = Role::firstOrCreate(['name' => 'admin'], ['name' => 'admin']);
        $admin->syncPermissions([
            'client.view',
            'client.view_any',
            'client.create',
            'client.update',
            'client.delete',
            'user.view',
            'user.view_any',
            'user.create',
            'user.update',
            'user.delete',
            'user.manage_client_users',
            'wallet.view',
            'wallet.view_any',
            'wallet.create',
            'wallet.update',
            'wallet.delete',
            'wallet.view_internal_note',
            'ledger.view',
            'ledger.view_any',
            'ledger.credit',
            'ledger.debit',
            'ledger.adjust',
            'tag.view',
            'tag.view_any',
            'tag.create',
            'tag.update',
            'tag.delete',
            'timer.view',
            'timer.view_any',
            'timer.create',
            'timer.update',
            'timer.confirm',
            'timer.delete',
            'import.view',
            'import.view_any',
            'import.create',
            'import.update',
            'import.confirm',
            'import.cancel',
            'import.delete',
            'report.view',
            'report.view_any',
        ]);

        if (!$isProduction) {
            dump('admin permissions', $admin->getAllPermissions()->pluck('name')->toArray());
            echo PHP_EOL;
        }

        // Manager - Can manage clients, wallets, and add credits/debits
        /** @var Role $manager */
        $manager = Role::firstOrCreate(['name' => 'manager'], ['name' => 'manager']);
        $manager->syncPermissions([
            'client.view',
            'client.view_any',
            'client.create',
            'client.update',
            'user.view',
            'user.create',
            'user.update',
            'user.manage_client_users',
            'wallet.view',
            'wallet.view_any',
            'wallet.create',
            'wallet.update',
            'ledger.view',
            'ledger.view_any',
            'ledger.credit',
            'ledger.debit',
            'tag.view',
            'tag.view_any',
            'tag.create',
            'timer.view',
            'timer.view_any',
            'timer.create',
            'timer.update',
            'timer.confirm',
            'import.view',
            'import.view_any',
            'import.create',
            'import.update',
            'import.confirm',
            'import.cancel',
            'report.view',
            'report.view_any',
        ]);

        if (!$isProduction) {
            dump('manager permissions', $manager->getAllPermissions()->pluck('name')->toArray());
            echo PHP_EOL;
        }

        // Operator - Can view and add debits (consume hours)
        /** @var Role $operator */
        $operator = Role::firstOrCreate(['name' => 'operator'], ['name' => 'operator']);
        $operator->syncPermissions([
            'client.view',
            'client.view_any',
            'wallet.view',
            'wallet.view_any',
            'ledger.view',
            'ledger.view_any',
            'ledger.debit',
            'tag.view',
            'tag.view_any',
            'timer.view',
            'timer.create',
            'timer.update',
            'timer.confirm',
            'import.view',
            'import.create',
            'import.update',
            'import.confirm',
            'import.cancel',
            'report.view',
        ]);

        if (!$isProduction) {
            dump('operator permissions', $operator->getAllPermissions()->pluck('name')->toArray());
            echo PHP_EOL;
        }

        // // Viewer - Read-only access
        // /** @var Role $viewer */
        // $viewer = Role::firstOrCreate(['name' => 'viewer'], ['name' => 'viewer']);
        // $viewer->syncPermissions([
        //     'client.view',
        //     'client.view_any',
        //     'wallet.view',
        //     'wallet.view_any',
        //     'ledger.view',
        //     'ledger.view_any',
        //     'tag.view',
        //     'tag.view_any',
        //     'timer.view',
        //     'import.view',
        //     'report.view',
        // ]);

        // Customer - Can only view own data
        /** @var Role $customer */
        $customer = Role::firstOrCreate(['name' => 'customer'], ['name' => 'customer']);
        $customer->syncPermissions([
            'client.view',
            'wallet.view',
            'ledger.view',
            'tag.view',
            'timer.view',
            'report.view',
            'report.export',
            'credit_purchase.create',
            'credit_purchase.view',
        ]);

        dump('customer permissions', $customer->getAllPermissions()->pluck('name')->toArray());
    }
}
