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
            // Event CRUD
            'view events',
            'create events',
            'update events',
            'delete events',
            // Ticket tiers CRUD
            'view tickets',
            'create tickets',
            'update tickets',
            'delete tickets',
            // Order and workflows
            'view orders',
            'create orders',
            'update orders',
            'delete orders',
            'review manual payments',
            'verify students',
            'scan tickets',
            'view dashboard',
            'manage settings',
            // User management
            'view users',
            'create users',
            'update users',
            'delete users',
            // Role management
            'view roles',
            'create roles',
            'update roles',
            'delete roles',
            // Permission management
            'view permissions',
            'assign permissions',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // ── Roles ─────────────────────────────────────────────────────────────

        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin      = Role::firstOrCreate(['name' => 'admin',       'guard_name' => 'web']);
        $manager    = Role::firstOrCreate(['name' => 'manager',     'guard_name' => 'web']);
        $validator  = Role::firstOrCreate(['name' => 'validator',   'guard_name' => 'web']);
        $staff      = Role::firstOrCreate(['name' => 'staff',       'guard_name' => 'web']);

        // super_admin → all permissions
        $superAdmin->syncPermissions(Permission::all());

        // admin → full management
        $admin->syncPermissions([
            'view dashboard',
            'view events', 'create events', 'update events', 'delete events',
            'view tickets', 'create tickets', 'update tickets', 'delete tickets',
            'view orders', 'create orders', 'update orders', 'delete orders',
            'review manual payments',
            'verify students',
            'scan tickets',
            'manage settings',
        ]);

        // manager → event view + no event mutations, ticket view/edit no create/delete, payment verification, orders, scan, student verify
        $manager->syncPermissions([
            'view dashboard',
            'view events',
            'view tickets', 'update tickets',
            'view orders', 'create orders', 'update orders',
            'review manual payments',
            'verify students',
            'scan tickets',
        ]);

        // staff → only ticket view and scan access
        $staff->syncPermissions([
            'view tickets',
            'scan tickets',
        ]);

        // validator → verify students, review payments, send tickets, scan tickets (no dashboard analytics)
        $validator->syncPermissions([
            'view tickets',
            'view orders',
            'update orders',
            'review manual payments',
            'verify students',
            'scan tickets',
        ]);

        $this->command->info('Roles and permissions seeded.');
    }
}
