<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ── Super Admin ───────────────────────────────────────────────────────

        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin@mp.com'],
            [
                'name'     => 'Super Admin',
                'password' => Hash::make('password'),
                'is_admin' => true,
            ]
        );
        $superAdmin->syncRoles(['super_admin']);

        // ── Admin ─────────────────────────────────────────────────────────────

        $admin = User::updateOrCreate(
            ['email' => 'admin@mp.com'],
            [
                'name'     => 'Admin User',
                'password' => Hash::make('password'),
                'is_admin' => true,
            ]
        );
        $admin->syncRoles(['admin']);

        // ── Manager ───────────────────────────────────────────────────────────

        $manager = User::updateOrCreate(
            ['email' => 'manager@mp.com'],
            [
                'name'     => 'Event Manager',
                'password' => Hash::make('password'),
                'is_admin' => true,
            ]
        );
        $manager->syncRoles(['manager']);

        // ── Validator ─────────────────────────────────────────────────────────

        $validator = User::updateOrCreate(
            ['email' => 'validator@mp.com'],
            [
                'name'     => 'Ticket Validator',
                'password' => Hash::make('password'),
                'is_admin' => false,
            ]
        );
        $validator->syncRoles(['validator']);

        // ── Staff ─────────────────────────────────────────────────────────────

        $staff = User::updateOrCreate(
            ['email' => 'staff@mp.com'],
            [
                'name'     => 'Staff Member',
                'password' => Hash::make('password'),
                'is_admin' => false,
            ]
        );
        $staff->syncRoles(['staff']);

        $this->command->info('Admin users seeded.');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['super_admin', 'superadmin@mp.com', 'password'],
                ['admin',       'admin@mp.com',      'password'],
                ['manager',     'manager@mp.com',    'password'],
                ['validator',   'validator@mp.com',  'password'],
                ['staff',       'staff@mp.com',      'password'],
            ]
        );
    }
}
