<?php

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

    config([
        'services.google.client_id' => 'test-client-id.apps.googleusercontent.com',
        'services.google.client_secret' => 'test-client-secret',
        'services.google.redirect' => 'http://localhost:8000/auth/google/callback',
    ]);
});

test('can redirect to google for authentication', function () {
    $response = $this->get(route('auth.google'));

    $response->assertRedirect();
    $targetUrl = $response->headers->get('Location');
    expect($targetUrl)->toContain('accounts.google.com')
        ->and($targetUrl)->toContain('client_id=')
        ->and($targetUrl)->toContain('redirect_uri=');
});

test('redirects to login with error if google credentials are missing', function () {
    config([
        'services.google.client_id' => null,
        'services.google.client_secret' => null,
    ]);

    $response = $this->get(route('auth.google'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
});

test('can handle google oauth callback and create new customer account', function () {
    config(['app.is_seller' => false]);

    $abstractUser = Mockery::mock(SocialiteUser::class);
    $abstractUser->shouldReceive('getId')->andReturn('google-user-123456');
    $abstractUser->shouldReceive('getEmail')->andReturn('google.customer@gmail.com');
    $abstractUser->shouldReceive('getName')->andReturn('Google Customer');
    $abstractUser->shouldReceive('getAvatar')->andReturn('https://lh3.googleusercontent.com/avatar.jpg');
    $abstractUser->token = 'mock-google-token';
    $abstractUser->refreshToken = 'mock-google-refresh-token';

    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('user')->andReturn($abstractUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect('/');
    $this->assertAuthenticated();

    $user = User::where('google_id', 'google-user-123456')->first();
    expect($user)->not->toBeNull()
        ->and($user->email)->toBe('google.customer@gmail.com')
        ->and($user->name)->toBe('Google Customer')
        ->and($user->avatar)->toBe('https://lh3.googleusercontent.com/avatar.jpg')
        ->and($user->google_token)->toBe('mock-google-token')
        ->and($user->hasRole('Customer'))->toBeTrue();
});

test('can handle google oauth callback and unify with existing account by email', function () {
    config(['app.is_seller' => true]);

    $existing = User::factory()->create([
        'name' => 'Existing Seller',
        'email' => 'existing.seller@gmail.com',
        'is_seller' => true,
        'store_name' => 'Toko Seller Existing',
        'store_slug' => 'toko-seller-existing',
        'google_id' => null,
    ]);
    $existing->assignRole('Customer');

    $abstractUser = Mockery::mock(SocialiteUser::class);
    $abstractUser->shouldReceive('getId')->andReturn('google-id-789012');
    $abstractUser->shouldReceive('getEmail')->andReturn('existing.seller@gmail.com');
    $abstractUser->shouldReceive('getName')->andReturn('Existing Seller Google');
    $abstractUser->shouldReceive('getAvatar')->andReturn('https://lh3.googleusercontent.com/new-avatar.jpg');
    $abstractUser->token = 'seller-token';
    $abstractUser->refreshToken = 'seller-refresh-token';

    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('user')->andReturn($abstractUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect('/');
    $this->assertAuthenticatedAs($existing);

    $existing->refresh();
    expect($existing->google_id)->toBe('google-id-789012')
        ->and($existing->is_seller)->toBeTrue()
        ->and($existing->store_name)->toBe('Toko Seller Existing')
        ->and($existing->hasRole('Customer'))->toBeTrue();
});

test('redirects admin users to /admin after google login', function () {
    $admin = User::factory()->create([
        'name' => 'Admin Store',
        'email' => 'admin@gmail.com',
        'google_id' => 'admin-google-999',
    ]);
    $admin->assignRole('Super Admin');

    $abstractUser = Mockery::mock(SocialiteUser::class);
    $abstractUser->shouldReceive('getId')->andReturn('admin-google-999');
    $abstractUser->shouldReceive('getEmail')->andReturn('admin@gmail.com');
    $abstractUser->shouldReceive('getName')->andReturn('Admin Store');
    $abstractUser->shouldReceive('getAvatar')->andReturn(null);
    $abstractUser->token = 'admin-token';
    $abstractUser->refreshToken = null;

    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('user')->andReturn($abstractUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect('/admin');
    $this->assertAuthenticatedAs($admin);
});

test('redirects to login with error when google oauth request has error', function () {
    $response = $this->get(route('auth.google.callback', [
        'error' => 'access_denied',
        'error_description' => 'User declined access',
    ]));

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
});

test('can link and unlink google account for authenticated user', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password123'),
        'google_id' => null,
    ]);

    $abstractUser = Mockery::mock(SocialiteUser::class);
    $abstractUser->shouldReceive('getId')->andReturn('linked-google-id');
    $abstractUser->token = 'link-token';
    $abstractUser->refreshToken = null;

    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('user')->andReturn($abstractUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    session(['google_link_user_id' => $user->id]);

    $response = $this->actingAs($user)->get(route('auth.google.link.callback'));

    $response->assertRedirect(route('profile.edit'));
    $user->refresh();
    expect($user->google_id)->toBe('linked-google-id')
        ->and($user->isLinkedToGoogle())->toBeTrue();

    // Test Unlink
    $unlinkResponse = $this->actingAs($user)->delete(route('auth.google.unlink'));
    $unlinkResponse->assertRedirect(route('profile.edit'));
    $user->refresh();
    expect($user->google_id)->toBeNull()
        ->and($user->isLinkedToGoogle())->toBeFalse();
});
