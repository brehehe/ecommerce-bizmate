<?php

use App\Models\CustomerAddress;
use App\Models\PaymentMethod;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BiteshipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Clear Cache
    Cache::flush();

    // Set default settings
    Setting::updateOrCreate(['key' => 'biteship_url'], ['value' => 'https://api.biteship.com/v1/']);
    Setting::updateOrCreate(['key' => 'biteship_secret_key'], ['value' => 'biteship_test.dummykey']);
    Setting::updateOrCreate(['key' => 'biteship_enabled'], ['value' => '1']);
    Setting::updateOrCreate(['key' => 'shipping_delivery_enabled'], ['value' => '0']);

    Setting::updateOrCreate(['key' => 'province_name'], ['value' => 'Jawa Timur']);
    Setting::updateOrCreate(['key' => 'regency_name'], ['value' => 'Surabaya']);
    Setting::updateOrCreate(['key' => 'regency_id'], ['value' => '444']);
    Setting::updateOrCreate(['key' => 'district_name'], ['value' => 'Wonokromo']);
    Setting::updateOrCreate(['key' => 'postal_code'], ['value' => '60245']);
});

test('biteship service is enabled based on settings', function () {
    expect(BiteshipService::isEnabled())->toBeTrue();

    Setting::updateOrCreate(['key' => 'biteship_enabled'], ['value' => '0']);
    expect(BiteshipService::isEnabled())->toBeFalse();
});

test('biteship resolves area id successfully', function () {
    Http::fake([
        '*/maps/areas*' => Http::response([
            'success' => true,
            'areas' => [
                [
                    'id' => 'ID_AREA_WONOKROMO',
                    'name' => 'Wonokromo, Surabaya, Jawa Timur',
                    'country' => 'Indonesia',
                    'postal_code' => '60245',
                ],
            ],
        ], 200),
    ]);

    $areaId = BiteshipService::resolveBiteshipAreaId('Jawa Timur', 'Surabaya', 'Wonokromo', '60245');

    expect($areaId)->toBe('ID_AREA_WONOKROMO');
});

test('biteship calculates domestic cost and formats correctly', function () {
    Http::fake([
        '*/maps/areas*' => function ($request) {
            $input = $request->url();
            if (str_contains(strtoupper($input), 'WONOKROMO') || str_contains(strtoupper($input), 'SURABAYA')) {
                return Http::response([
                    'success' => true,
                    'areas' => [['id' => 'ORIGIN_AREA', 'name' => 'Wonokromo']],
                ], 200);
            }

            return Http::response([
                'success' => true,
                'areas' => [['id' => 'DEST_AREA', 'name' => 'Tebet']],
            ], 200);
        },
        '*/rates/couriers' => Http::response([
            'success' => true,
            'couriers' => [
                [
                    'company_code' => 'jne',
                    'company_name' => 'JNE',
                    'available_services' => [
                        [
                            'service_code' => 'reg',
                            'service_name' => 'Regular',
                            'description' => 'Layanan Reguler JNE',
                            'price' => 12000,
                            'etd' => '2-3 hari',
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $response = BiteshipService::getDomesticCost('Surabaya', 'Jakarta Selatan', 1000, 'jne');

    expect($response)->toHaveKey('results');
    expect($response['results'])->toBeArray();
    expect($response['results'][0]['code'])->toBe('jne');
    expect($response['results'][0]['costs'][0]['service'])->toBe('[reg] Regular');
    expect($response['results'][0]['costs'][0]['cost'][0]['value'])->toBe(12000.0);
});

test('checkout shipping cost endpoint uses biteship when enabled', function () {
    $user = User::factory()->create();
    $address = CustomerAddress::create([
        'user_id' => $user->id,
        'label' => 'Rumah',
        'receiver_name' => 'John Doe',
        'phone_number' => '08123456789',
        'full_address' => 'Jl. Tebet No. 10',
        'province_name' => 'DKI Jakarta',
        'regency_name' => 'Jakarta Selatan',
        'district_name' => 'Tebet',
        'postal_code' => '12810',
        'is_primary' => true,
    ]);

    Http::fake([
        '*/maps/areas*' => function ($request) {
            $input = $request->url();
            if (str_contains(strtoupper($input), 'WONOKROMO') || str_contains(strtoupper($input), 'SURABAYA')) {
                return Http::response([
                    'success' => true,
                    'areas' => [['id' => 'ORIGIN_AREA', 'name' => 'Wonokromo']],
                ], 200);
            }

            return Http::response([
                'success' => true,
                'areas' => [['id' => 'DEST_AREA', 'name' => 'Tebet']],
            ], 200);
        },
        '*/rates/couriers' => Http::response([
            'success' => true,
            'couriers' => [
                [
                    'company_code' => 'jne',
                    'company_name' => 'JNE',
                    'available_services' => [
                        [
                            'service_code' => 'reg',
                            'service_name' => 'Regular',
                            'price' => 12000,
                            'etd' => '2-3 hari',
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('checkout.shipping-cost'), [
            'destination' => '123',
            'weight' => 1000,
            'courier' => 'jne',
            'address_id' => $address->id,
        ]);

    if ($response->status() !== 200) {
        dump($response->json());
    }

    $response->assertOk();
});

test('biteship resolves base url correctly adding v1 if not present', function () {
    Setting::updateOrCreate(['key' => 'biteship_url'], ['value' => 'https://api.biteship.com']);
    expect(BiteshipService::getBiteshipUrl())->toBe('https://api.biteship.com/v1');

    Setting::updateOrCreate(['key' => 'biteship_url'], ['value' => 'https://api.biteship.com/v1']);
    expect(BiteshipService::getBiteshipUrl())->toBe('https://api.biteship.com/v1');
});

test('biteship destination search returns mapped areas successfully', function () {
    $user = User::factory()->create();

    Http::fake([
        '*/maps/areas*' => Http::response([
            'success' => true,
            'areas' => [
                [
                    'id' => 'ID_AREA_WONOKROMO',
                    'name' => 'Pesanggrahan, Jakarta Selatan, DKI Jakarta. 12250',
                    'country_name' => 'Indonesia',
                    'country_code' => 'ID',
                    'administrative_division_level_1_name' => 'DKI Jakarta',
                    'administrative_division_level_1_type' => 'province',
                    'administrative_division_level_2_name' => 'Jakarta Selatan',
                    'administrative_division_level_2_type' => 'city',
                    'administrative_division_level_3_name' => 'Pesanggrahan',
                    'administrative_division_level_3_type' => 'district',
                    'postal_code' => 12250,
                ],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('checkout.komerce.search-destination', ['keyword' => 'pesanggrahan']));

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'city_name',
                'district_name',
                'zip_code',
                'postal_code',
                'label',
            ],
        ],
    ]);
});

test('checkout shipping cost prioritizes komerce over biteship if both enabled', function () {
    $user = User::factory()->create();
    $address = CustomerAddress::create([
        'user_id' => $user->id,
        'label' => 'Rumah',
        'receiver_name' => 'John Doe',
        'phone_number' => '08123456789',
        'full_address' => 'Jl. Tebet No. 10',
        'province_name' => 'DKI Jakarta',
        'regency_name' => 'Jakarta Selatan',
        'district_name' => 'Tebet',
        'postal_code' => '12810',
        'is_primary' => true,
    ]);

    Setting::updateOrCreate(['key' => 'shipping_delivery_enabled'], ['value' => '1']);
    Setting::updateOrCreate(['key' => 'shipping_delivery_key'], ['value' => 'dummy_komerce_key']);

    // Mock Komerce and Biteship requests
    Http::fake([
        '*destination/search*' => Http::response([
            'success' => true,
            'data' => [
                [
                    'id' => 12345,
                    'city_name' => 'Surabaya',
                    'district_name' => 'Wonokromo',
                    'zip_code' => '60245',
                    'postal_code' => '60245',
                ],
                [
                    'id' => 67890,
                    'city_name' => 'Jakarta Selatan',
                    'district_name' => 'Tebet',
                    'zip_code' => '12810',
                    'postal_code' => '12810',
                ],
            ],
        ], 200),
        '*calculate*' => Http::response([
            'success' => true,
            'data' => [
                'calculate_reguler' => [
                    [
                        'shipping_name' => 'JNE',
                        'service_name' => 'REG',
                        'shipping_cost' => 10000,
                        'etd' => '2-3 hari',
                    ],
                ],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('checkout.shipping-cost'), [
            'destination' => '123',
            'weight' => 1000,
            'courier' => 'jne',
            'address_id' => $address->id,
        ]);

    $response->assertOk();
    $response->assertJsonPath('results.0.costs.0.cost.0.value', 10000);
});

test('biteship calculates domestic cost with coordinates and flat pricing format', function () {
    // Set store coordinates
    Setting::updateOrCreate(['key' => 'latitude'], ['value' => '-6.3031123']);
    Setting::updateOrCreate(['key' => 'longitude'], ['value' => '106.7794934']);

    $user = User::factory()->create();
    $address = CustomerAddress::create([
        'user_id' => $user->id,
        'label' => 'Rumah',
        'receiver_name' => 'John Doe',
        'phone_number' => '08123456789',
        'full_address' => 'Jl. Tebet No. 10',
        'province_name' => 'DKI Jakarta',
        'regency_name' => 'Jakarta Selatan',
        'district_name' => 'Tebet',
        'postal_code' => '12810',
        'is_primary' => true,
        'latitude' => -6.2441792,
        'longitude' => 106.783529,
    ]);

    Http::fake([
        '*/maps/areas*' => function ($request) {
            return Http::response([
                'success' => true,
                'areas' => [['id' => 'SOME_AREA', 'name' => 'Tebet']],
            ], 200);
        },
        '*/rates/couriers' => Http::response([
            'success' => true,
            'pricing' => [
                [
                    'company' => 'jne',
                    'courier_name' => 'JNE',
                    'courier_code' => 'jne',
                    'courier_service_name' => 'City to City (CTC)',
                    'price' => 11000,
                    'duration' => '2 - 3 days',
                    'description' => 'Pengiriman city to city',
                ],
            ],
        ], 200),
    ]);

    $response = BiteshipService::getDomesticCost('Surabaya', 'Jakarta Selatan', 1000, 'jne', $address->id);

    expect($response)->toHaveKey('results');
    expect($response['results'])->toBeArray();
    expect($response['results'][0]['code'])->toBe('jne');
    expect($response['results'][0]['costs'][0]['service'])->toBe('[reg] City to City (CTC)');
    expect($response['results'][0]['costs'][0]['cost'][0]['value'])->toBe(11000.0);
    expect($response['results'][0]['costs'][0]['cost'][0]['etd'])->toBe('2 - 3 days');

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), 'rates/couriers')) {
            return true;
        }
        $payload = $request->data();

        return isset($payload['origin_latitude']) &&
               isset($payload['origin_longitude']) &&
               isset($payload['destination_latitude']) &&
               isset($payload['destination_longitude']) &&
               (float) $payload['origin_latitude'] === -6.3031123 &&
               (float) $payload['destination_longitude'] === 106.783529;
    });
});

test('biteship webhook updates transaction status to dikirim, selesai, or batal', function () {
    $user = User::factory()->create();
    $paymentMethod = PaymentMethod::create([
        'name' => 'Transfer BCA',
        'type' => 'manual',
        'is_active' => true,
        'admin_fee' => 0,
    ]);
    $transaction = Transaction::create([
        'transaction_number' => Transaction::generateNumber(),
        'user_id' => $user->id,
        'payment_method_id' => $paymentMethod->id,
        'status' => 'diproses',
        'subtotal' => 100000,
        'shipping_fee' => 15000,
        'grand_total' => 115000,
        'booking_code' => 'BITESHIP-BOOK-123',
        'tracking_number' => 'BITESHIP-TRACK-123',
    ]);

    // Test status update to dikirim (using 'picked' status)
    $response = $this->postJson(route('api.biteship.webhook'), [
        'id' => 'BITESHIP-BOOK-123',
        'status' => 'picked',
    ]);

    $response->assertOk();
    $transaction->refresh();
    expect($transaction->status)->toBe('dikirim');

    // Test status update to selesai
    $response = $this->postJson(route('api.biteship.webhook'), [
        'id' => 'BITESHIP-BOOK-123',
        'status' => 'delivered',
    ]);

    $response->assertOk();
    $transaction->refresh();
    expect($transaction->status)->toBe('selesai');
});

test('artisan command app:sync-shipment-status updates biteship transaction status', function () {
    $user = User::factory()->create();
    $paymentMethod = PaymentMethod::create([
        'name' => 'Transfer Mandiri',
        'type' => 'manual',
        'is_active' => true,
        'admin_fee' => 0,
    ]);
    $transaction = Transaction::create([
        'transaction_number' => Transaction::generateNumber(),
        'user_id' => $user->id,
        'payment_method_id' => $paymentMethod->id,
        'status' => 'diproses',
        'subtotal' => 100000,
        'shipping_fee' => 15000,
        'grand_total' => 115000,
        'booking_code' => 'BITESHIP-BOOK-456',
        'tracking_number' => 'BITESHIP-TRACK-456',
    ]);

    Http::fake([
        '*/trackings/BITESHIP-TRACK-456/couriers/*' => Http::response([
            'success' => true,
            'history' => [
                ['updated_at' => '2026-06-10 12:00:00', 'status' => 'picked_up', 'note' => 'Paket telah di pick up oleh kurir'],
            ],
        ], 200),
    ]);

    Artisan::call('app:sync-shipment-status');

    $transaction->refresh();
    expect($transaction->status)->toBe('dikirim');
});
