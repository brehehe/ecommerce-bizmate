<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create Roles
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin']);
        $adminPenjualanRole = Role::firstOrCreate(['name' => 'Admin Penjualan']);
        $adminTokoRole = Role::firstOrCreate(['name' => 'Admin Toko']);
        $customerRole = Role::firstOrCreate(['name' => 'Customer']);

        // Create Admin User
        $admin = User::firstOrCreate(
            ['email' => 'admin@bizmate.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
            ]
        );
        if (! $admin->hasRole('Super Admin')) {
            $admin->assignRole($superAdminRole);
        }

        $adminPenjualan = User::firstOrCreate(
            ['email' => 'admin-penjualan@bizmate.com'],
            [
                'name' => 'Admin Penjualan',
                'password' => Hash::make('password'),
            ]
        );
        if (! $adminPenjualan->hasRole('Admin Penjualan')) {
            $adminPenjualan->assignRole($adminPenjualanRole);
        }

        $adminToko = User::firstOrCreate(
            ['email' => 'admin-toko@bizmate.com'],
            [
                'name' => 'Admin Toko',
                'password' => Hash::make('password'),
            ]
        );
        if (! $adminToko->hasRole('Admin Toko')) {
            $adminToko->assignRole($adminTokoRole);
        }

        // Create Customer User
        $customer = User::firstOrCreate(
            ['email' => 'customer@bizmate.com'],
            [
                'name' => 'John Doe Customer',
                'password' => Hash::make('password'),
            ]
        );
        if (! $customer->hasRole('Customer')) {
            $customer->assignRole($customerRole);
        }
    }
}
