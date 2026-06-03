<?php

use App\Models\User;
use App\Notifications\QueuedResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Administrator', 'guard_name' => 'web']);
});

test('forgot password page can be rendered', function () {
    $response = $this->get('/forgot-password');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Auth/ForgotPassword')
    );
});

test('password reset link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'customer@example.com',
    ]);
    $user->assignRole('Customer');

    $response = $this->from('/forgot-password')->post('/forgot-password', [
        'email' => 'customer@example.com',
    ]);

    $response->assertRedirect('/forgot-password');
    $response->assertSessionHas('success', 'Link reset kata sandi telah dikirim ke email Anda.');

    Notification::assertSentTo($user, QueuedResetPassword::class);
});

test('password reset page can be rendered', function () {
    $user = User::factory()->create([
        'email' => 'customer@example.com',
    ]);
    $user->assignRole('Customer');

    $token = Password::broker()->createToken($user);

    $response = $this->get('/reset-password/'.$token.'?email=customer@example.com');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Auth/ResetPassword')
        ->where('token', $token)
        ->where('email', 'customer@example.com')
    );
});

test('password can be reset with valid token', function () {
    $user = User::factory()->create([
        'email' => 'customer@example.com',
        'password' => Hash::make('oldpassword123'),
    ]);
    $user->assignRole('Customer');

    $token = Password::broker()->createToken($user);

    $response = $this->post('/reset-password', [
        'token' => $token,
        'email' => 'customer@example.com',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertRedirect('/login');
    $response->assertSessionHas('success', 'Kata sandi Anda berhasil diperbarui! Silakan masuk.');

    $user->refresh();
    expect(Hash::check('newpassword123', $user->password))->toBeTrue();
});

test('password reset validation is enforced', function () {
    $user = User::factory()->create([
        'email' => 'customer@example.com',
        'password' => Hash::make('oldpassword123'),
    ]);
    $user->assignRole('Customer');

    $token = Password::broker()->createToken($user);

    // Password mismatch
    $response = $this->from('/reset-password/'.$token)->post('/reset-password', [
        'token' => $token,
        'email' => 'customer@example.com',
        'password' => 'newpassword123',
        'password_confirmation' => 'different123',
    ]);

    $response->assertRedirect('/reset-password/'.$token);
    $response->assertSessionHasErrors(['password']);

    // Password too short
    $response = $this->from('/reset-password/'.$token)->post('/reset-password', [
        'token' => $token,
        'email' => 'customer@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    $response->assertRedirect('/reset-password/'.$token);
    $response->assertSessionHasErrors(['password']);
});
