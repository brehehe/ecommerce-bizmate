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

        // Create Customer Users
        $customersData = [
            ['name' => 'Budi Santoso', 'email' => 'budi@example.com'],
            ['name' => 'Ani Wijaya', 'email' => 'ani@example.com'],
            ['name' => 'Citra Lestari', 'email' => 'citra@example.com'],
            ['name' => 'Dedi Kurniawan', 'email' => 'dedi@example.com'],
            ['name' => 'Eko Prasetyo', 'email' => 'eko@example.com'],
            ['name' => 'Farida Utami', 'email' => 'farida@example.com'],
            ['name' => 'Guntur Wibowo', 'email' => 'guntur@example.com'],
            ['name' => 'Hesti Putri', 'email' => 'hesti@example.com'],
            ['name' => 'John Doe Customer', 'email' => 'customer@bizmate.com'],
        ];

        foreach ($customersData as $data) {
            $customer = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );
            if (! $customer->hasRole('Customer')) {
                $customer->assignRole($customerRole);
            }
        }
    }
}
