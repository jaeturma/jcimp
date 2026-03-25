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
            ['email' => 'admin@example.com'],
            [
                'name'     => 'Super Admin',
                'password' => Hash::make('password'),
                'is_admin' => true,
            ]
        );
        $superAdmin->syncRoles(['super_admin']);

        // ── Admin ─────────────────────────────────────────────────────────────

        $admin = User::updateOrCreate(
            ['email' => 'manager@example.com'],
            [
                'name'     => 'Event Manager',
                'password' => Hash::make('password'),
                'is_admin' => true,
            ]
        );
        $admin->syncRoles(['admin']);

        // ── Staff ─────────────────────────────────────────────────────────────

        $staff = User::updateOrCreate(
            ['email' => 'staff@example.com'],
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
                ['super_admin', 'admin@example.com',   'password'],
                ['admin',       'manager@example.com', 'password'],
                ['staff',       'staff@example.com',   'password'],
            ]
        );
    }
}
