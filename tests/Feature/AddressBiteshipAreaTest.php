<?php

use App\Models\CustomerAddress;
use App\Models\Setting;
use App\Models\User;
use App\Services\BiteshipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createSetting(string $key, string $value): void
{
    Setting::insert([
        'id' => Str::uuid()->toString(),
        'key' => $key,
        'value' => $value,
        'order' => Setting::withTrashed()->max('order') + 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function createAddress(User $user, array $attributes = []): CustomerAddress
{
    $defaults = [
        'id' => Str::uuid()->toString(),
        'user_id' => $user->id,
        'label' => 'Rumah',
        'receiver_name' => 'Test User',
        'phone_number' => '081234567890',
        'full_address' => 'Jl Test No 1',
        'province_id' => '35',
        'province_name' => 'JAWA TIMUR',
        'regency_id' => '3578',
        'regency_name' => 'KOTA SURABAYA',
        'district_id' => '3578110',
        'district_name' => 'WONOKROMO',
        'village_id' => '3578110004',
        'village_name' => 'NGAGELREJO',
        'postal_code' => '60241',
        'biteship_area_id' => null,
        'rajaongkir_destination_id' => null,
        'latitude' => -7.30,
        'longitude' => 112.76,
        'is_primary' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ];

    $merged = array_merge($defaults, $attributes);
    CustomerAddress::insert($merged);

    return CustomerAddress::find($merged['id']);
}

// ─── Address CRUD with biteship_area_id ─────────────────────────────────────

it('stores biteship_area_id when creating an address', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/profile/addresses', [
        'label' => 'Rumah',
        'receiver_name' => 'Test User',
        'phone_number' => '081234567890',
        'full_address' => 'Jl Test No 1',
        'province_id' => '35',
        'province_name' => 'JAWA TIMUR',
        'regency_id' => '3578',
        'regency_name' => 'KOTA SURABAYA',
        'district_id' => '3578110',
        'district_name' => 'WONOKROMO',
        'village_id' => '3578110004',
        'village_name' => 'NGAGELREJO',
        'postal_code' => '60241',
        'biteship_area_id' => 'IDNP11IDNC434IDND5450IDZ60241',
        'rajaongkir_destination_id' => null,
        'latitude' => -7.30,
        'longitude' => 112.76,
        'is_primary' => false,
    ])->assertRedirect();

    $this->assertDatabaseHas('customer_addresses', [
        'user_id' => $user->id,
        'biteship_area_id' => 'IDNP11IDNC434IDND5450IDZ60241',
    ]);
});

it('updates biteship_area_id when editing an address', function () {
    $user = User::factory()->create();
    $address = createAddress($user, ['biteship_area_id' => null]);

    $this->actingAs($user)->put("/profile/addresses/{$address->id}", [
        'label' => $address->label,
        'receiver_name' => $address->receiver_name,
        'phone_number' => $address->phone_number,
        'full_address' => $address->full_address,
        'province_id' => $address->province_id,
        'province_name' => $address->province_name,
        'regency_id' => $address->regency_id,
        'regency_name' => $address->regency_name,
        'district_id' => $address->district_id,
        'district_name' => $address->district_name,
        'village_id' => $address->village_id,
        'village_name' => $address->village_name,
        'postal_code' => $address->postal_code,
        'biteship_area_id' => 'IDNP11IDNC434IDND5450IDZ60241',
        'is_primary' => true,
    ])->assertRedirect();

    $this->assertDatabaseHas('customer_addresses', [
        'id' => $address->id,
        'biteship_area_id' => 'IDNP11IDNC434IDND5450IDZ60241',
    ]);
});

// ─── Biteship area search API proxy ─────────────────────────────────────────

it('returns empty areas when query is too short', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/api/addresses/biteship-areas?q=ab')
        ->assertOk()
        ->assertJson(['success' => false, 'areas' => []]);
});

it('proxies biteship area search and returns areas', function () {
    createSetting('biteship_secret_key', 'test-api-key');
    createSetting('biteship_url', 'https://api.biteship.com/v1');

    Http::fake([
        'api.biteship.com/v1/maps/areas*' => Http::response([
            'success' => true,
            'areas' => [
                [
                    'id' => 'IDNP11IDNC434IDND5450IDZ60241',
                    'name' => 'Wonokromo, Kota Surabaya, Jawa Timur',
                    'administrative_division_level_1_name' => 'Jawa Timur',
                    'administrative_division_level_2_name' => 'Kota Surabaya',
                    'administrative_division_level_3_name' => 'Wonokromo',
                    'postal_code' => 60241,
                ],
            ],
        ], 200),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/api/addresses/biteship-areas?q=Wonokromo')
        ->assertOk()
        ->assertJson(['success' => true])
        ->assertJsonPath('areas.0.id', 'IDNP11IDNC434IDND5450IDZ60241');
});

// ─── BiteshipService uses stored area ID ────────────────────────────────────

it('uses stored biteship_area_id skipping maps api resolution for destination', function () {
    createSetting('biteship_secret_key', 'test-api-key');
    createSetting('biteship_url', 'https://api.biteship.com/v1');
    createSetting('biteship_enabled', '1');
    createSetting('province_name', 'JAWA TIMUR');
    createSetting('regency_name', 'KOTA SURABAYA');
    createSetting('district_name', 'WONOKROMO');
    createSetting('postal_code', '60241');

    // Pre-cache origin area ID using the same key format that BiteshipService generates
    // Note: BiteshipService strips "kota" prefix from regency names
    $provinceClean = strtoupper(trim(str_ireplace(['provinsi', 'prov'], '', 'JAWA TIMUR')));
    $regencyClean = strtoupper(trim(str_ireplace(['kabupaten', 'kab', 'kota'], '', 'KOTA SURABAYA')));
    $districtClean = strtoupper(trim(str_ireplace(['kecamatan', 'kec'], '', 'WONOKROMO')));
    $zipClean = '60241';
    $cacheKey = 'biteship_area_id_'.md5("{$provinceClean}_{$regencyClean}_{$districtClean}_{$zipClean}");
    Cache::put($cacheKey, 'IDNP11IDNC434IDND5450IDZ60241', 86400);

    Http::fake([
        'api.biteship.com/v1/rates/couriers' => Http::response([
            'success' => true,
            'pricing' => [
                [
                    'courier_code' => 'jne',
                    'courier_name' => 'JNE',
                    'courier_service_name' => 'Reguler',
                    'price' => 15000,
                    'duration' => '2-3 hari',
                ],
            ],
        ], 200),
    ]);

    $user = User::factory()->create();
    $address = createAddress($user, [
        'province_name' => 'JAWA TIMUR',
        'regency_name' => 'KOTA SURABAYA',
        'district_name' => 'WONOKROMO',
        'postal_code' => '60241',
        'biteship_area_id' => 'IDNP11IDNC434IDND5450IDZ60241',
        'latitude' => -7.30,
        'longitude' => 112.76,
    ]);

    $result = BiteshipService::getDomesticCost('3578', '3578', 1000, 'jne', $address->id);

    expect($result)->toHaveKey('results')
        ->and($result['results'])->toHaveCount(1)
        ->and($result['results'][0]['code'])->toBe('jne');

    // Only the rates endpoint should be called (origin resolved via cache, dest from stored ID)
    Http::assertSentCount(1);
});
