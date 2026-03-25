<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ── Permissions ───────────────────────────────────────────────────────

        $permissions = [
            'manage tickets',
            'manage orders',
            'review manual payments',
            'scan tickets',
            'view dashboard',
            'verify students',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // ── Roles ─────────────────────────────────────────────────────────────

        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin      = Role::firstOrCreate(['name' => 'admin',       'guard_name' => 'web']);
        $staff      = Role::firstOrCreate(['name' => 'staff',       'guard_name' => 'web']);

        // super_admin → all permissions
        $superAdmin->syncPermissions(Permission::all());

        // admin → all except scan tickets
        $admin->syncPermissions([
            'manage tickets',
            'manage orders',
            'review manual payments',
            'view dashboard',
            'verify students',
        ]);

        // staff → scan tickets, view dashboard
        $staff->syncPermissions([
            'scan tickets',
            'view dashboard',
        ]);

        $this->command->info('Roles and permissions seeded.');
    }
}
