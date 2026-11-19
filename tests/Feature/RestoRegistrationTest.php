<?php

use App\Models\CustomerAddress;
use App\Models\RestoProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guest can access resto registration page', function () {
    $response = $this->get(route('register.resto'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Auth/RegisterResto')
        ->where('currentUser', null)
        ->has('culinaryCategories')
    );
});

test('authenticated customer can access resto registration page with prefilled user data', function () {
    $user = User::create([
        'name' => 'Pak Harun',
        'email' => 'harun@resto.com',
        'phone_number' => '081234567890',
        'password' => bcrypt('password'),
    ]);

    $response = $this->actingAs($user)->get(route('register.resto'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Auth/RegisterResto')
        ->where('currentUser.name', 'Pak Harun')
        ->where('currentUser.email', 'harun@resto.com')
        ->where('currentUser.phone_number', '081234567890')
    );
});

test('authenticated seller with active resto profile is redirected to dashboard', function () {
    $seller = User::create([
        'name' => 'Bu Susi',
        'email' => 'susi@resto.com',
        'password' => bcrypt('password'),
        'is_seller' => true,
    ]);

    RestoProfile::create([
        'user_id' => $seller->id,
        'resto_name' => 'Dapur Bu Susi',
        'slug' => 'dapur-bu-susi',
        'culinary_category' => 'Aneka Nasi & Lauk',
        'address' => 'Jl. Veteran No. 5',
    ]);

    $response = $this->actingAs($seller)->get(route('register.resto'));
    $response->assertRedirect(route('admin.dashboard'));
    $response->assertSessionHas('info', 'Anda sudah terdaftar sebagai Mitra Resto.');
});

test('guest can successfully register a new resto merchant', function () {
    $payload = [
        'name' => 'Chef Junaidi',
        'email' => 'junaidi@resto.com',
        'phone_number' => '081987654321',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
        'resto_name' => 'Ayam Bakar Madu Junaidi',
        'culinary_category' => 'Ayam & Bebek',
        'description' => 'Ayam bakar dengan bumbu madu khas Nusantara.',
        'full_address' => 'Jl. Ijen No. 45, Klojen',
        'province_name' => 'Jawa Timur',
        'regency_name' => 'Kota Malang',
        'district_name' => 'Klojen',
        'postal_code' => '65111',
        'latitude' => -7.9754321,
        'longitude' => 112.6256789,
        'avg_prep_time_minutes' => 25,
        'allow_pickup' => true,
        'allow_delivery' => true,
    ];

    $response = $this->post(route('register.resto.post'), $payload);

    $response->assertRedirect(route('admin.dashboard'));
    $response->assertSessionHas('success');

    // Assert User created
    $user = User::where('email', 'junaidi@resto.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->is_seller)->toBeTrue()
        ->and($user->store_name)->toBe('Ayam Bakar Madu Junaidi');

    // Assert RestoProfile created
    $resto = RestoProfile::where('user_id', $user->id)->first();
    expect($resto)->not->toBeNull()
        ->and($resto->resto_name)->toBe('Ayam Bakar Madu Junaidi')
        ->and($resto->culinary_category)->toBe('Ayam & Bebek')
        ->and($resto->avg_prep_time_minutes)->toBe(25)
        ->and((float) $resto->latitude)->toBe(-7.9754321);

    // Assert primary CustomerAddress created for origin / pickup
    $address = CustomerAddress::where('user_id', $user->id)->where('is_primary', true)->first();
    expect($address)->not->toBeNull()
        ->and($address->full_address)->toBe('Jl. Ijen No. 45, Klojen');

    // Assert user is authenticated
    $this->assertAuthenticatedAs($user);
});

test('existing authenticated customer can upgrade to resto merchant', function () {
    $customer = User::create([
        'name' => 'Rina Wijaya',
        'email' => 'rina@gmail.com',
        'phone_number' => '085678901234',
        'password' => bcrypt('password'),
        'is_seller' => false,
    ]);

    $payload = [
        'resto_name' => 'Kopi Sejiwa Rina',
        'culinary_category' => 'Kopi & Minuman',
        'description' => 'Kedai kopi artisanal.',
        'full_address' => 'Jl. Soekarno Hatta No. 88',
        'province_name' => 'Jawa Timur',
        'regency_name' => 'Kota Malang',
        'district_name' => 'Lowokwaru',
        'postal_code' => '65141',
        'avg_prep_time_minutes' => 15,
        'allow_pickup' => true,
        'allow_delivery' => false,
    ];

    $response = $this->actingAs($customer)->post(route('register.resto.post'), $payload);

    $response->assertRedirect(route('admin.dashboard'));
    $response->assertSessionHas('success');

    $customer->refresh();
    expect($customer->is_seller)->toBeTrue()
        ->and($customer->store_name)->toBe('Kopi Sejiwa Rina')
        ->and($customer->restoProfile)->not->toBeNull()
        ->and($customer->restoProfile->resto_name)->toBe('Kopi Sejiwa Rina');
});

test('resto registration fails when required fields are missing', function () {
    $response = $this->post(route('register.resto.post'), []);

    $response->assertSessionHasErrors([
        'name',
        'email',
        'phone_number',
        'password',
        'resto_name',
        'culinary_category',
        'full_address',
    ]);
});
