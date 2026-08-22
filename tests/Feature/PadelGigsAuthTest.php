<?php

use App\Models\User;
use App\Services\PadelGigsOAuthService;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
});

test('can redirect to padelgigs for authorization with state and pkce', function () {
    $response = $this->get(route('auth.padelgigs'));

    $response->assertRedirect();
    $targetUrl = $response->headers->get('Location');
    expect($targetUrl)->toContain('/oauth/authorize')
        ->and($targetUrl)->toContain('response_type=code')
        ->and($targetUrl)->toContain('code_challenge=');

    expect(session('padelgigs_oauth_state'))->not->toBeNull()
        ->and(session('padelgigs_oauth_code_verifier'))->not->toBeNull();
});

test('can handle oauth callback and create new customer account', function () {
    config(['app.is_seller' => false]);

    $this->mock(PadelGigsOAuthService::class, function ($mock) {
        $mock->shouldReceive('exchangeCodeForTokens')
            ->once()
            ->with('valid-auth-code', 'test-state')
            ->andReturn([
                'access_token' => 'test-access-token',
                'refresh_token' => 'test-refresh-token',
                'expires_in' => 3600,
            ]);

        $mock->shouldReceive('getUserInfo')
            ->once()
            ->with('test-access-token')
            ->andReturn([
                'id' => 'padelgigs-user-123',
                'name' => 'Budi Padel',
                'email' => 'budi.padel@example.com',
                'phone' => '081299998888',
                'avatar' => 'https://example.com/avatar.jpg',
            ]);
    });

    session([
        'padelgigs_oauth_state' => 'test-state',
        'padelgigs_oauth_code_verifier' => 'test-verifier',
    ]);

    $response = $this->get(route('auth.padelgigs.callback', [
        'code' => 'valid-auth-code',
        'state' => 'test-state',
    ]));

    $response->assertRedirect('/');
    $this->assertAuthenticated();

    $user = User::where('padelgigs_user_id', 'padelgigs-user-123')->first();
    expect($user)->not->toBeNull()
        ->and($user->email)->toBe('budi.padel@example.com')
        ->and($user->name)->toBe('Budi Padel')
        ->and($user->hasRole('Customer'))->toBeTrue()
        ->and($user->padelgigs_access_token)->toBe('test-access-token');
});

test('can handle oauth callback and unify with existing seller account by email without losing store data', function () {
    config(['app.is_seller' => true]);

    $seller = User::factory()->create([
        'name' => 'Owner Toko Padel',
        'email' => 'seller@example.com',
        'phone_number' => '081211112222',
        'is_seller' => true,
        'store_name' => 'Toko Padel Pro',
        'store_slug' => 'toko-padel-pro',
        'store_description' => 'Toko perlengkapan padel nomor 1',
        'padelgigs_user_id' => null,
    ]);
    $seller->assignRole('Customer');

    $this->mock(PadelGigsOAuthService::class, function ($mock) {
        $mock->shouldReceive('exchangeCodeForTokens')
            ->once()
            ->andReturn([
                'access_token' => 'seller-token-123',
                'refresh_token' => 'seller-refresh-123',
                'expires_in' => 7200,
            ]);

        $mock->shouldReceive('getUserInfo')
            ->once()
            ->andReturn([
                'id' => 'padelgigs-seller-999',
                'name' => 'Owner Toko Padel',
                'email' => 'seller@example.com',
                'phone' => '081211112222',
            ]);
    });

    session([
        'padelgigs_oauth_state' => 'test-state',
        'padelgigs_oauth_code_verifier' => 'test-verifier',
    ]);

    $response = $this->get(route('auth.padelgigs.callback', [
        'code' => 'seller-code',
        'state' => 'test-state',
    ]));

    $response->assertRedirect('/admin');
    $this->assertAuthenticatedAs($seller);

    $seller->refresh();
    expect($seller->padelgigs_user_id)->toBe('padelgigs-seller-999')
        ->and($seller->is_seller)->toBeTrue()
        ->and($seller->store_name)->toBe('Toko Padel Pro')
        ->and($seller->store_slug)->toBe('toko-padel-pro')
        ->and($seller->store_description)->toBe('Toko perlengkapan padel nomor 1')
        ->and($seller->hasRole('Customer'))->toBeTrue();
});

test('can handle oauth callback and unify with existing seller account by phone number', function () {
    config(['app.is_seller' => true]);

    $seller = User::factory()->create([
        'name' => 'Phone Match Seller',
        'email' => 'local.seller@domain.com',
        'phone_number' => '081234567890',
        'is_seller' => true,
        'store_name' => 'Toko Ponsel Match',
        'padelgigs_user_id' => null,
    ]);
    $seller->assignRole('Customer');

    $this->mock(PadelGigsOAuthService::class, function ($mock) {
        $mock->shouldReceive('exchangeCodeForTokens')
            ->once()
            ->andReturn([
                'access_token' => 'phone-match-token',
                'refresh_token' => 'phone-match-refresh',
                'expires_in' => 3600,
            ]);

        $mock->shouldReceive('getUserInfo')
            ->once()
            ->andReturn([
                'id' => 'padelgigs-phone-user-456',
                'name' => 'Phone Match Seller',
                'email' => 'padelgigs.email@different.com',
                'phone' => '+6281234567890',
            ]);
    });

    session([
        'padelgigs_oauth_state' => 'test-state',
        'padelgigs_oauth_code_verifier' => 'test-verifier',
    ]);

    $response = $this->get(route('auth.padelgigs.callback', [
        'code' => 'phone-code',
        'state' => 'test-state',
    ]));

    $response->assertRedirect('/admin');
    $this->assertAuthenticatedAs($seller);

    $seller->refresh();
    expect($seller->padelgigs_user_id)->toBe('padelgigs-phone-user-456')
        ->and($seller->is_seller)->toBeTrue()
        ->and($seller->store_name)->toBe('Toko Ponsel Match');
});

test('can link and unlink padelgigs account for authenticated user', function () {
    $user = User::factory()->create([
        'password' => bcrypt('secret123'),
        'padelgigs_user_id' => null,
    ]);

    $this->mock(PadelGigsOAuthService::class, function ($mock) use ($user) {
        $mock->shouldReceive('exchangeCodeForTokens')
            ->once()
            ->andReturn([
                'access_token' => 'link-token',
                'refresh_token' => 'link-refresh',
                'expires_in' => 3600,
            ]);

        $mock->shouldReceive('getUserInfo')
            ->once()
            ->andReturn([
                'id' => 'padelgigs-linked-id',
                'name' => $user->name,
                'email' => $user->email,
            ]);
    });

    session([
        'padelgigs_link_user_id' => $user->id,
        'padelgigs_oauth_state' => 'test-state',
        'padelgigs_oauth_code_verifier' => 'test-verifier',
    ]);

    $response = $this->actingAs($user)->get(route('auth.padelgigs.link.callback', [
        'code' => 'link-code',
        'state' => 'test-state',
    ]));

    $response->assertRedirect(route('profile.edit'));
    $user->refresh();
    expect($user->padelgigs_user_id)->toBe('padelgigs-linked-id');

    // Test unlink
    $unlinkResponse = $this->actingAs($user)->delete(route('auth.padelgigs.unlink'));
    $unlinkResponse->assertRedirect(route('profile.edit'));
    $user->refresh();
    expect($user->padelgigs_user_id)->toBeNull();
});

test('sets is_seller to true and assigns Customer role when IS_SELLER config is true', function () {
    config(['app.is_seller' => true]);

    $this->mock(PadelGigsOAuthService::class, function ($mock) {
        $mock->shouldReceive('exchangeCodeForTokens')
            ->once()
            ->andReturn([
                'access_token' => 'seller-mode-token',
                'refresh_token' => 'seller-mode-refresh',
                'expires_in' => 3600,
            ]);

        $mock->shouldReceive('getUserInfo')
            ->once()
            ->andReturn([
                'id' => 'padelgigs-new-seller-777',
                'name' => 'Calon Seller Padel',
                'email' => 'new.seller@padelgigs.id',
                'phone' => '087711223344',
            ]);
    });

    session([
        'padelgigs_oauth_state' => 'test-state',
        'padelgigs_oauth_code_verifier' => 'test-verifier',
    ]);

    $response = $this->get(route('auth.padelgigs.callback', [
        'code' => 'seller-auth-code',
        'state' => 'test-state',
    ]));

    $response->assertRedirect('/admin');
    $this->assertAuthenticated();

    $user = User::where('padelgigs_user_id', 'padelgigs-new-seller-777')->first();
    expect($user)->not->toBeNull()
        ->and($user->is_seller)->toBeTrue()
        ->and($user->store_name)->toContain('Calon Seller Padel')
        ->and($user->store_slug)->not->toBeNull()
        ->and($user->hasRole('Customer'))->toBeTrue();
});

test('redirects to login with error when padelgigs login is disabled', function () {
    config(['services.padelgigs.enabled' => false]);

    $response = $this->get(route('auth.padelgigs'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
});

test('callback redirects to login with error when padelgigs login is disabled', function () {
    config(['services.padelgigs.enabled' => false]);

    $response = $this->get(route('auth.padelgigs.callback', [
        'code' => 'any-code',
        'state' => 'any-state',
    ]));

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
});
