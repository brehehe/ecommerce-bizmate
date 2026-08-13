<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Admin Toko', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Customer', 'guard_name' => 'web']);
});

test('user can login with email', function () {
    $user = User::factory()->create([
        'email' => 'testuser@bizmate.local',
        'password' => bcrypt('password123'),
    ]);
    $user->assignRole('Admin Toko');

    $this->post('/login', [
        'email' => 'testuser@bizmate.local',
        'password' => 'password123',
    ])->assertRedirect('/admin');

    $this->assertAuthenticatedAs($user);
});

test('user can login with phone number', function () {
    $user = User::factory()->create([
        'phone_number' => '08123456789',
        'password' => bcrypt('password123'),
    ]);
    $user->assignRole('Admin Toko');

    $this->post('/login', [
        'email' => '08123456789',
        'password' => 'password123',
    ])->assertRedirect('/admin');

    $this->assertAuthenticatedAs($user);
});

test('user can login with normalized phone number 62 format', function () {
    $user = User::factory()->create([
        'phone_number' => '08129876543',
        'password' => bcrypt('password123'),
    ]);
    $user->assignRole('Admin Toko');

    $this->post('/login', [
        'email' => '628129876543',
        'password' => 'password123',
    ])->assertRedirect('/admin');

    $this->assertAuthenticatedAs($user);
});
