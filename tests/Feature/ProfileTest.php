<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Administrator', 'guard_name' => 'web']);
});

test('customer can view edit profile page', function () {
    $customer = User::factory()->create();
    $customer->assignRole('Customer');

    $response = $this->actingAs($customer)->get('/profile');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Storefront/Profile')
        ->has('user')
    );
});

test('customer can update their name and email', function () {
    $customer = User::factory()->create([
        'name' => 'Old Customer Name',
        'email' => 'old_customer@example.com',
    ]);
    $customer->assignRole('Customer');

    $response = $this->actingAs($customer)->put('/profile', [
        'name' => 'New Customer Name',
        'email' => 'new_customer@example.com',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Profil Anda berhasil diperbarui!');

    $customer->refresh();
    expect($customer->name)->toBe('New Customer Name')
        ->and($customer->email)->toBe('new_customer@example.com');
});

test('customer can update their password', function () {
    $customer = User::factory()->create([
        'password' => Hash::make('oldpassword123'),
    ]);
    $customer->assignRole('Customer');

    $response = $this->actingAs($customer)->put('/profile', [
        'name' => $customer->name,
        'email' => $customer->email,
        'current_password' => 'oldpassword123',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertRedirect();
    $customer->refresh();

    expect(Hash::check('newpassword123', $customer->password))->toBeTrue();
});

test('customer profile validation is enforced', function () {
    $customer1 = User::factory()->create(['email' => 'user1@example.com']);
    $customer2 = User::factory()->create(['email' => 'user2@example.com']);
    $customer1->assignRole('Customer');

    // Duplicate email
    $response = $this->actingAs($customer1)->from('/profile')->put('/profile', [
        'name' => 'User One',
        'email' => 'user2@example.com',
    ]);

    $response->assertRedirect('/profile');
    $response->assertSessionHasErrors(['email']);

    // Too short password
    $response = $this->actingAs($customer1)->from('/profile')->put('/profile', [
        'name' => 'User One',
        'email' => 'user1@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    $response->assertRedirect('/profile');
    $response->assertSessionHasErrors(['password']);

    // Password confirmation mismatch
    $response = $this->actingAs($customer1)->from('/profile')->put('/profile', [
        'name' => 'User One',
        'email' => 'user1@example.com',
        'password' => 'newpassword123',
        'password_confirmation' => 'different123',
    ]);

    $response->assertRedirect('/profile');
    $response->assertSessionHasErrors(['password']);
});

test('admin can view edit profile page', function () {
    $admin = User::factory()->create();
    // Assuming admin bypasses the customer middleware or has proper admin attributes/roles
    // Since we prefix 'admin' routes, the not_customer middleware checks if user is not customer

    $response = $this->actingAs($admin)->get('/admin/profile');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Profile')
        ->has('user')
    );
});

test('admin can update their profile', function () {
    $admin = User::factory()->create([
        'name' => 'Old Admin Name',
        'email' => 'old_admin@example.com',
    ]);

    $response = $this->actingAs($admin)->put('/admin/profile', [
        'name' => 'New Admin Name',
        'email' => 'new_admin@example.com',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Profil admin berhasil diperbarui!');

    $admin->refresh();
    expect($admin->name)->toBe('New Admin Name')
        ->and($admin->email)->toBe('new_admin@example.com');
});
