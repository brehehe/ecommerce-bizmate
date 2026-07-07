<?php

use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Administrator', 'guard_name' => 'web']);
});

test('email verification screen can be rendered', function () {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);
    $user->assignRole('Customer');

    $response = $this->actingAs($user)->get('/email/verify');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->component('Auth/VerifyEmail'));
});

test('email verification screen redirects verified users to home', function () {
    $user = User::factory()->create();
    $user->assignRole('Customer');

    $response = $this->actingAs($user)->get('/email/verify');

    $response->assertRedirect('/');
});

test('unverified users are redirected to verification page when trying to access checkout', function () {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);
    $user->assignRole('Customer');

    $response = $this->actingAs($user)->get('/checkout');

    $response->assertRedirect('/email/verify');
});

test('unverified admin users are not redirected to verification page when trying to access checkout', function () {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);
    $user->assignRole('Administrator');

    $response = $this->actingAs($user)->get('/checkout');

    $location = $response->headers->get('Location');
    expect($location)->not->toContain('/email/verify');
});

test('unverified users can still access profile page', function () {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);
    $user->assignRole('Customer');

    $response = $this->actingAs($user)->get('/profile');

    $response->assertStatus(200);
});

test('email can be verified by a guest', function () {
    Event::fake();

    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);
    $user->assignRole('Customer');

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    // Call as guest (not logged in)
    $response = $this->get($verificationUrl);

    $response->assertRedirect('/login');
    $response->assertSessionHas('success', 'Email Anda berhasil diverifikasi! Silakan masuk untuk melanjutkan.');

    $user->refresh();
    expect($user->hasVerifiedEmail())->toBeTrue();
});

test('email verification link can be resent', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);
    $user->assignRole('Customer');

    $response = $this->actingAs($user)->from('/email/verify')->post('/email/verification-notification');

    $response->assertRedirect('/email/verify');
    $response->assertSessionHas('success', 'Link verifikasi baru telah dikirim ke email Anda.');

    Notification::assertSentTo($user, QueuedVerifyEmail::class);
});

test('registration triggers verification email notification and redirects to login', function () {
    Event::fake();

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertRedirect('/login');
    $response->assertSessionHas('success', 'Pendaftaran berhasil! Silakan periksa email Anda untuk memverifikasi akun sebelum masuk.');

    Event::assertDispatched(Registered::class);
});

test('unverified user cannot log in and receives verification error', function () {
    $user = User::factory()->create([
        'email_verified_at' => null,
        'password' => Hash::make('password123'),
    ]);
    $user->assignRole('Customer');

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $response->assertSessionHasErrors(['email' => 'Akun Anda belum diverifikasi. Silakan periksa email Anda.']);
    expect(Auth::check())->toBeFalse();
});

test('guest can request email verification resend', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);
    $user->assignRole('Customer');

    $response = $this->post('/email/resend-verification-guest', [
        'email' => $user->email,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Link verifikasi baru telah dikirim ke email Anda.');

    Notification::assertSentTo($user, QueuedVerifyEmail::class);
});
