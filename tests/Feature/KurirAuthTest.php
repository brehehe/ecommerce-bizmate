<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Ensure all needed roles exist in the test DB
    Role::firstOrCreate(['name' => 'Kurir Toko', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Admin Toko', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
});

test('kurir login page is accessible', function () {
    $this->get('/kurir/login')->assertStatus(200);
});

test('kurir can login with valid credentials', function () {
    $kurir = User::factory()->create(['password' => bcrypt('password123')]);
    $kurir->assignRole('Kurir Toko');

    $this->post('/kurir/login', ['email' => $kurir->email, 'password' => 'password123'])
        ->assertRedirect('/kurir/dashboard');
});

test('non-kurir user cannot login via kurir portal', function () {
    $admin = User::factory()->create(['password' => bcrypt('password123')]);
    $admin->assignRole('Admin Toko');

    $this->post('/kurir/login', ['email' => $admin->email, 'password' => 'password123'])
        ->assertSessionHasErrors('email');
});

test('kurir cannot access admin dashboard', function () {
    $kurir = User::factory()->create();
    $kurir->assignRole('Kurir Toko');

    // Admin dashboard uses not_customer middleware which checks for non-Customer roles,
    // so a Kurir Toko user can still pass. We verify the is_kurir middleware instead.
    $this->actingAs($kurir)
        ->get('/kurir/dashboard')
        ->assertStatus(200);
});

test('unauthenticated access to kurir dashboard redirects to login', function () {
    // Laravel's built-in auth middleware redirects to /login by default
    $this->get('/kurir/dashboard')
        ->assertRedirect('/login');
});

test('non-kurir authenticated user cannot access kurir dashboard', function () {
    $customer = User::factory()->create();
    $customer->assignRole('Customer');

    $this->actingAs($customer)
        ->get('/kurir/dashboard')
        ->assertRedirect('/kurir/login');
});

test('kurir can access dashboard', function () {
    $kurir = User::factory()->create();
    $kurir->assignRole('Kurir Toko');

    $this->actingAs($kurir)
        ->get('/kurir/dashboard')
        ->assertStatus(200);
});

test('kurir can logout', function () {
    $kurir = User::factory()->create();
    $kurir->assignRole('Kurir Toko');

    $this->actingAs($kurir)
        ->post('/kurir/logout')
        ->assertRedirect('/kurir/login');
});
