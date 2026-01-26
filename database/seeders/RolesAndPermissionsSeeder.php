<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Summary of RolesAndPermissionsSeeder
 * @author Tiago França
 * @copyright (c) 2025
 *
 * @suppress PHP0413
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

        // Create Permissions
        $permissions = [
            // Account Management
            'accounts.view',
            'accounts.create',
            'accounts.update',
            'accounts.delete',
            'accounts.suspend',
            'accounts.activate',

            // Document
            'documents.submit',
            'documents.list',
            'documents.show',

            // Document Validation
            'documents.validate',
            'documents.approve',
            'documents.reject',

            // Apps Management
            'apps.view',
            'apps.create',
            'apps.update',
            'apps.delete',

            // Transaction Management
            'transactions.view',
            'transactions.create',
            'transactions.refund',
            'transactions.cancel',

            // Wallet Management
            'wallets.view',
            'wallets.create',
            'wallets.update',

            // Financial Operations
            'financial.view_reports',
            'financial.manage_fees',
            'financial.manage_exchange_rates',
            'financial.approve_withdrawals',

            // Support/Staff Operations
            'support.view_tickets',
            'support.create_tickets',
            'support.resolve_tickets',
            'support.view_logs',

            // Webhook Management
            'webhooks.view',
            'webhooks.create',
            'webhooks.update',
            'webhooks.delete',

            // System Settings
            'settings.view',
            'settings.update',

            // Roles and Permissions
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',
            'permissions.assign',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission], ['name' => $permission]);
        }

        // Create Roles and Assign Permissions

        // Super Admin - Full access to everything
        /** @var Role $superAdmin */
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin'], ['name' => 'super_admin']);
        $superAdmin->syncPermissions(Permission::all());

        // Admin - System administrator (below super_admin)
        /** @var Role $admin */
        $admin = Role::firstOrCreate(['name' => 'admin'], ['name' => 'admin']);
        $admin->syncPermissions([
            'accounts.view',
            'accounts.create',
            'accounts.update',
            'accounts.suspend',
            'accounts.activate',
            'documents.validate',
            'documents.approve',
            'documents.reject',
            'apps.view',
            'apps.create',
            'apps.update',
            'apps.delete',
            'transactions.view',
            'transactions.refund',
            'transactions.cancel',
            'wallets.view',
            'wallets.create',
            'wallets.update',
            'financial.view_reports',
            'financial.approve_withdrawals',
            'support.view_tickets',
            'support.create_tickets',
            'support.resolve_tickets',
            'support.view_logs',
            'webhooks.view',
            'webhooks.create',
            'webhooks.update',
            'webhooks.delete',
            'settings.view',
        ]);

        // Manager - Almost admin with reduced permissions
        /** @var Role $manager */
        $manager = Role::firstOrCreate(['name' => 'manager'], ['name' => 'manager']);
        $manager->syncPermissions([
            'accounts.view',
            'accounts.create',
            'accounts.update',
            'documents.validate',
            'documents.approve',
            'apps.view',
            'apps.create',
            'apps.update',
            'transactions.view',
            'transactions.refund',
            'wallets.view',
            'wallets.create',
            'financial.view_reports',
            'support.view_tickets',
            'support.create_tickets',
            'support.resolve_tickets',
            'support.view_logs',
            'webhooks.view',
            'settings.view',
        ]);

        // Financial - Financial operations specialist
        /** @var Role $financial */
        $financial = Role::firstOrCreate(['name' => 'financial'], ['name' => 'financial']);
        $financial->syncPermissions([
            'accounts.view',
            'transactions.view',
            'transactions.refund',
            'transactions.cancel',
            'wallets.view',
            'financial.view_reports',
            'financial.manage_fees',
            'financial.manage_exchange_rates',
            'financial.approve_withdrawals',
            'support.view_logs',
        ]);

        // Staff - Support team with specific permissions
        /** @var Role $staff */
        $staff = Role::firstOrCreate(['name' => 'staff'], ['name' => 'staff']);
        $staff->syncPermissions([
            'accounts.view',
            'documents.validate',
            'apps.view',
            'transactions.view',
            'wallets.view',
            'support.view_tickets',
            'support.create_tickets',
            'support.resolve_tickets',
            'support.view_logs',
        ]);

        // Customer
        /** @var Role $customer */
        $customer = Role::firstOrCreate(['name' => 'customer'], ['name' => 'customer']);
        $customer->syncPermissions([
            'documents.submit',
            'documents.list',
            'documents.show',
            'apps.view',
            'apps.create',
            'apps.update',
            'apps.delete',
            'transactions.view',
            'transactions.refund',
            'transactions.cancel',
            'wallets.view',
            'financial.view_reports',
            'support.view_tickets',
            'support.create_tickets',
            'webhooks.view',
            'webhooks.create',
            'webhooks.update',
            'webhooks.delete',
        ]);
    }
}
