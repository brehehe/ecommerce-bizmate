<?php

use App\Models\Category;
use App\Models\CustomerAddress;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\KomerceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->address = CustomerAddress::create([
        'user_id' => $this->user->id,
        'receiver_name' => 'Test User',
        'phone_number' => '08123456789',
        'label' => 'Rumah',
        'full_address' => 'Jl. Test No. 1',
        'province_name' => 'Jawa Timur',
        'regency_name' => 'Surabaya',
        'district_name' => 'Wonokromo',
        'village_name' => 'Ngagelrejo',
        'postal_code' => '60245',
        'is_primary' => true,
    ]);

    $this->paymentMethod = PaymentMethod::create([
        'name' => 'Transfer BCA',
        'type' => 'manual',
        'is_active' => true,
        'admin_fee' => 0,
    ]);

    $this->transaction = Transaction::create([
        'transaction_number' => Transaction::generateNumber(),
        'user_id' => $this->user->id,
        'customer_address_id' => $this->address->id,
        'payment_method_id' => $this->paymentMethod->id,
        'status' => 'diproses',
        'subtotal' => 100000,
        'shipping_fee' => 15000,
        'grand_total' => 115000,
        'booking_code' => 'BOOK-TEST-123',
        'tracking_number' => 'RES-TEST-123',
    ]);
});

test('artisan command app:sync-shipment-status updates transaction status to dikirim when in transit', function () {
    Http::fake([
        'https://api-sandbox.collaborator.komerce.id/order/api/v1/orders/history-airway-bill*' => Http::response([
            'success' => true,
            'history' => [
                ['date' => '2026-06-03 12:00:00', 'desc' => 'Paket sedang transit di hub logistik'],
            ],
        ], 200),
    ]);

    Artisan::call('app:sync-shipment-status');

    $this->transaction->refresh();
    expect($this->transaction->status)->toBe('dikirim');
});

test('artisan command app:sync-shipment-status updates transaction status to selesai when delivered', function () {
    Http::fake([
        'https://api-sandbox.collaborator.komerce.id/order/api/v1/orders/history-airway-bill*' => Http::response([
            'success' => true,
            'history' => [
                ['date' => '2026-06-03 12:00:00', 'desc' => 'Paket telah diterima oleh Ybs'],
            ],
        ], 200),
    ]);

    Artisan::call('app:sync-shipment-status');

    $this->transaction->refresh();
    expect($this->transaction->status)->toBe('selesai');
});

test('komerce webhook updates status to dikirim and selesai on shipping status payload', function () {
    // 1. Post shipping status OTW
    $response = $this->postJson(route('api.komerce.webhook'), [
        'booking_code' => 'BOOK-TEST-123',
        'status' => 'shipping',
    ]);

    $response->assertOk();
    $this->transaction->refresh();
    expect($this->transaction->status)->toBe('dikirim');

    // 2. Post shipping status delivered
    $responseDelivered = $this->postJson(route('api.komerce.webhook'), [
        'booking_code' => 'BOOK-TEST-123',
        'status' => 'delivered',
    ]);

    $responseDelivered->assertOk();
    $this->transaction->refresh();
    expect($this->transaction->status)->toBe('selesai');
});

test('artisan command komerce:simulate-webhook dispatches internal request successfully', function () {
    Artisan::call('komerce:simulate-webhook', [
        'transaction_number' => $this->transaction->transaction_number,
        'status' => 'shipping',
    ]);

    $this->transaction->refresh();
    expect($this->transaction->status)->toBe('dikirim');

    Artisan::call('komerce:simulate-webhook', [
        'transaction_number' => $this->transaction->transaction_number,
        'status' => 'delivered',
    ]);

    $this->transaction->refresh();
    expect($this->transaction->status)->toBe('selesai');
});

test('komerce webhook updates status to batal and restores stock on cancelled payload', function () {
    // 1. Create a category, product, stock, and transaction item
    $category = Category::create([
        'name' => 'Test Cat',
        'slug' => 'test-cat',
        'icon' => 'ti-cpu',
    ]);

    $product = Product::create([
        'name' => 'Test Webhook Product',
        'slug' => 'test-webhook-product',
        'sku' => 'TEST-WH-PROD',
        'category_id' => $category->id,
        'summary' => 'Summary',
        'description' => 'Description',
        'active' => true,
    ]);

    $stock = ProductStock::create([
        'product_id' => $product->id,
        'stock' => 10,
        'is_unlimited' => false,
    ]);

    $this->transaction->items()->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'product_sku' => $product->sku,
        'harga_akhir' => 50000,
        'harga_jual' => 50000,
        'quantity' => 3,
        'subtotal' => 150000,
    ]);

    // 2. Post shipping status cancelled
    $response = $this->postJson(route('api.komerce.webhook'), [
        'booking_code' => 'BOOK-TEST-123',
        'status' => 'cancelled',
    ]);

    $response->assertOk();

    // 3. Assert status is cancelled (batal) and stock is restored (10 + 3 = 13)
    $this->transaction->refresh();
    expect($this->transaction->status)->toBe('batal');
    expect($this->transaction->cancel_reason)->toBe('Dibatalkan oleh kurir/sistem pengiriman Komerce');

    $stock->refresh();
    expect($stock->stock)->toBe(13);
});

test('KomerceService::getShipmentHistory returns dynamic simulated history based on transaction status', function () {
    // 1. Transaction is "diproses", simulated history should not contain "diterima"
    $res1 = KomerceService::getShipmentHistory($this->transaction->tracking_number, $this->transaction->shipping_courier);
    expect($res1['success'])->toBeTrue();
    expect($res1['simulated'])->toBeTrue();
    $latest1 = end($res1['history']);
    expect($latest1['desc'])->toContain('dalam perjalanan');

    // 2. Update transaction status to "dikirim", simulated history should contain "diterima"
    $this->transaction->update(['status' => 'dikirim']);
    $res2 = KomerceService::getShipmentHistory($this->transaction->tracking_number, $this->transaction->shipping_courier);
    expect($res2['success'])->toBeTrue();
    expect($res2['simulated'])->toBeTrue();
    $latest2 = end($res2['history']);
    expect($latest2['desc'])->toContain('diterima');
});

test('KomerceService::resolveKomerceDestinationId maps names to destination id successfully', function () {
    Http::fake([
        'https://api-sandbox.collaborator.komerce.id/tariff/api/v1/destination/search*' => Http::response([
            'meta' => ['status' => 'success', 'code' => 200, 'message' => 'Success'],
            'data' => [
                [
                    'id' => 4910,
                    'province_name' => 'JAWA BARAT',
                    'city_name' => 'BANDUNG',
                    'district_name' => 'CICENDO',
                    'subdistrict_name' => 'ARJUNA',
                    'label' => 'ARJUNA, CICENDO, BANDUNG, 40172',
                ],
            ],
        ], 200),
    ]);

    $id = KomerceService::resolveKomerceDestinationId('Jawa Barat', 'Bandung', 'Cicendo');
    expect($id)->toBe(4910);
});

test('KomerceService::getDomesticCost calls collaborator calculate when delivery is enabled', function () {
    // 1. Enable Komerce Delivery
    Setting::updateOrCreate(['key' => 'shipping_delivery_enabled'], ['value' => '1']);
    Setting::updateOrCreate(['key' => 'shipping_delivery_key'], ['value' => 'mock_key']);

    // Mock resolveKomerceDestinationId response
    Http::fake([
        'https://api-sandbox.collaborator.komerce.id/tariff/api/v1/destination/search*' => Http::response([
            'meta' => ['status' => 'success'],
            'data' => [
                ['id' => 12345, 'province_name' => 'JAWA TIMUR', 'city_name' => 'SURABAYA', 'district_name' => 'WONOKROMO'],
            ],
        ], 200),
        'https://api-sandbox.collaborator.komerce.id/tariff/api/v1/calculate*' => Http::response([
            'meta' => ['status' => 'success', 'code' => 200, 'message' => 'Success'],
            'data' => [
                'calculate_reguler' => [
                    [
                        'shipping_name' => 'JNE',
                        'service_name' => 'REG',
                        'shipping_cost' => 12000,
                        'etd' => '2-3 hari',
                    ],
                ],
                'calculate_cargo' => [],
                'calculate_instant' => [],
            ],
        ], 200),
    ]);

    $response = KomerceService::getDomesticCost('3578', '3578', 1000, 'jne', $this->address->id);

    expect($response)->toHaveKey('results');
    expect($response['results'][0]['code'])->toBe('jne');
    expect($response['results'][0]['costs'][0]['service'])->toBe('REG');
    expect($response['results'][0]['costs'][0]['cost'][0]['value'])->toBe(12000.0);
});

test('CheckoutController::searchKomerceDestination returns Komerce destination results', function () {
    Http::fake([
        'https://api-sandbox.collaborator.komerce.id/tariff/api/v1/destination/search*' => Http::response([
            'meta' => ['status' => 'success'],
            'data' => [
                ['id' => 9999, 'province_name' => 'JAWA TIMUR', 'city_name' => 'SURABAYA', 'district_name' => 'WONOKROMO'],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->getJson(route('checkout.komerce.search-destination', ['keyword' => 'wonokromo']));

    $response->assertOk();
    $response->assertJsonPath('data.0.id', 9999);
});

test('getDomesticCost logs warning on collaborator tariff calculation API failure', function () {
    // Enable Komerce Delivery
    Setting::updateOrCreate(['key' => 'shipping_delivery_enabled'], ['value' => '1']);
    Setting::updateOrCreate(['key' => 'shipping_delivery_key'], ['value' => 'mock_key']);

    // Mock resolveKomerceDestinationId response and failed calculate API call
    Http::fake([
        'https://api-sandbox.collaborator.komerce.id/tariff/api/v1/destination/search*' => Http::response([
            'meta' => ['status' => 'success'],
            'data' => [
                ['id' => 12345, 'province_name' => 'JAWA TIMUR', 'city_name' => 'SURABAYA', 'district_name' => 'WONOKROMO'],
            ],
        ], 200),
        'https://api-sandbox.collaborator.komerce.id/tariff/api/v1/calculate*' => Http::response([
            'meta' => ['status' => 'failed', 'message' => 'Service not found'],
            'data' => null,
        ], 404),
    ]);

    Log::shouldReceive('info');
    Log::shouldReceive('warning')
        ->once()
        ->with(Mockery::on(function ($message) {
            return str_contains($message, 'Komerce Tariff Calculate failed');
        }));

    KomerceService::getDomesticCost('3578', '3578', 1000, 'jne', $this->address->id);
});

test('getDomesticCost logs warning on RajaOngkir fallback API failure', function () {
    // Disable Komerce Delivery to force fallback
    Setting::updateOrCreate(['key' => 'shipping_delivery_enabled'], ['value' => '0']);
    Setting::updateOrCreate(['key' => 'rajaongkir_shipping_cost'], ['value' => 'mock_ro_key']);

    Http::fake([
        'https://rajaongkir.komerce.id/api/v1/calculate/domestic-cost' => Http::response([
            'meta' => ['message' => 'Invalid key'],
        ], 401),
    ]);

    Log::shouldReceive('info');
    Log::shouldReceive('warning')
        ->once()
        ->with(Mockery::on(function ($message) {
            return str_contains($message, 'RajaOngkir Shipping Cost API failed');
        }));

    KomerceService::getDomesticCost('3578', '3578', 1000, 'jne');
});

test('KomerceService::storeShipment normalizes JNEFlat service correctly', function () {
    Setting::updateOrCreate(['key' => 'shipping_delivery_key'], ['value' => 'mock_key']);
    Setting::updateOrCreate(['key' => 'store_name'], ['value' => 'My Store']);
    Setting::updateOrCreate(['key' => 'store_phone'], ['value' => '081234567890']);
    Setting::updateOrCreate(['key' => 'store_address'], ['value' => 'Jl. Merdeka No. 5']);
    Setting::updateOrCreate(['key' => 'store_email'], ['value' => 'store@example.com']);
    Setting::updateOrCreate(['key' => 'latitude'], ['value' => '-7.274631']);
    Setting::updateOrCreate(['key' => 'longitude'], ['value' => '109.207174']);

    $category = Category::create([
        'name' => 'Category A',
        'slug' => 'cat-a',
        'icon' => 'ti-home',
    ]);

    $product = Product::create([
        'name' => 'Tenda Dome',
        'slug' => 'tenda-dome',
        'sku' => 'TENDA-1',
        'category_id' => $category->id,
        'summary' => 'Summary',
        'description' => 'Description',
        'weight' => 2000,
        'length' => 15,
        'width' => 12,
        'height' => 10,
        'active' => true,
    ]);

    $this->transaction->items()->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'product_sku' => $product->sku,
        'harga_akhir' => 100000,
        'harga_jual' => 100000,
        'quantity' => 1,
        'subtotal' => 100000,
    ]);

    $this->transaction->update([
        'shipping_courier' => 'jne',
        'shipping_service' => 'JNEFlat',
    ]);

    Http::fake([
        'https://api-sandbox.collaborator.komerce.id/order/api/v1/orders/store*' => function (Request $request) {
            $data = $request->data();
            expect($data['shipping'])->toBe('JNE');
            expect($data['shipping_type'])->toBe('JNEFlat');
            expect($data['order_details'][0]['product_weight'])->toBe(2000);
            expect($data['order_details'][0]['product_width'])->toBe(12);
            expect($data['order_details'][0]['product_height'])->toBe(10);
            expect($data['order_details'][0]['product_length'])->toBe(15);

            return Http::response([
                'success' => true,
                'data' => [
                    'booking_code' => 'BOOK-OK-123',
                    'airway_bill' => 'RES-OK-123',
                ],
            ], 200);
        },
    ]);

    $response = KomerceService::storeShipment($this->transaction);
    expect($response['success'])->toBeTrue();
    expect($response['data']['booking_code'])->toBe('BOOK-OK-123');
});

test('komerce webhook accepts PUT payload containing order_no, cnote, status', function () {
    // Post status Dijemput via PUT
    $response = $this->putJson(route('api.komerce.webhook'), [
        'order_no' => 'BOOK-TEST-123',
        'cnote' => 'RES-TEST-123',
        'status' => 'Dijemput',
    ]);

    $response->assertOk();
    $this->transaction->refresh();
    expect($this->transaction->status)->toBe('dikirim');

    // Post status Selesai via PUT
    $response = $this->putJson(route('api.komerce.webhook'), [
        'order_no' => 'BOOK-TEST-123',
        'cnote' => 'RES-TEST-123',
        'status' => 'Selesai',
    ]);

    $response->assertOk();
    $this->transaction->refresh();
    expect($this->transaction->status)->toBe('selesai');
});

test('KomerceService::getOrderDetail returns simulated detail order in sandbox', function () {
    $response = KomerceService::getOrderDetail('BOOK-TEST-123');
    expect($response['success'])->toBeTrue();
    expect($response['simulated'])->toBeTrue();
    expect($response['data']['order_no'])->toBe('BOOK-TEST-123');
    expect($response['data']['order_status'])->toBe('Diajukan');
});

test('KomerceService::printLabel normalizes the response path key', function () {
    Setting::updateOrCreate(['key' => 'shipping_delivery_key'], ['value' => 'mock_key']);
    Setting::updateOrCreate(['key' => 'komerce_delivery_url'], ['value' => 'https://api-sandbox.collaborator.komerce.id/api/v1/']);

    Http::fake([
        'https://api-sandbox.collaborator.komerce.id/order/api/v1/orders/print-label*' => Http::response([
            'meta' => [
                'message' => 'Generate Print Label success',
                'code' => 200,
                'status' => 'success',
            ],
            'data' => [
                'path' => '/storage/label-03-06-2026-00-00-12345.pdf',
                'base_64' => 'dGVzdF9iYXNlXzY0',
            ],
        ], 200),
    ]);

    $response = KomerceService::printLabel('BOOK-TEST-123');
    expect($response['success'])->toBeTrue();
    expect($response['url'])->toBe('https://api-sandbox.collaborator.komerce.id/order/storage/label-03-06-2026-00-00-12345.pdf');
    expect($response['base_64'])->toBe('dGVzdF9iYXNlXzY0');
});

test('KomerceService::registerWebhook calls PUT to webhook endpoint', function () {
    Setting::updateOrCreate(['key' => 'shipping_delivery_key'], ['value' => 'mock_key']);
    Setting::updateOrCreate(['key' => 'komerce_delivery_url'], ['value' => 'https://api-sandbox.collaborator.komerce.id/api/v1/']);

    Http::fake([
        'https://api-sandbox.collaborator.komerce.id/order/api/v1/orders/webhook*' => Http::response([
            'meta' => [
                'message' => 'Success register webhook',
                'code' => 200,
                'status' => 'success',
            ],
        ], 200),
    ]);

    $response = KomerceService::registerWebhook('https://example.com/webhook');
    expect($response['success'])->toBeTrue();
});

test('Komerce APIs propagate actual errors and disable staging fallbacks in production', function () {
    Log::spy();
    // 1. Force production environment and set non-sandbox delivery URL
    app()->detectEnvironment(fn () => 'production');
    Setting::updateOrCreate(['key' => 'shipping_delivery_key'], ['value' => 'mock_key']);
    Setting::updateOrCreate(['key' => 'komerce_delivery_url'], ['value' => 'https://api.collaborator.komerce.id/api/v1/']);

    config([
        'app.rajaongkir.delivery_url' => 'https://api.collaborator.komerce.id/api/v1/',
        'app.rajaongkir.shipping_delivery_key' => 'mock_key',
    ]);

    Http::fake([
        'https://api.collaborator.komerce.id/order/api/v1/orders/print-label*' => Http::response([
            'meta' => ['message' => 'Label generation failed', 'code' => 500, 'status' => 'error'],
        ], 500),
        'https://api.collaborator.komerce.id/order/api/v1/orders/cancel*' => Http::response([
            'meta' => ['message' => 'Cancellation failed', 'code' => 400, 'status' => 'error'],
        ], 400),
        'https://api.collaborator.komerce.id/order/api/v1/orders/history-airway-bill*' => Http::response([
            'meta' => ['message' => 'AWB not found', 'code' => 404, 'status' => 'error'],
        ], 404),
    ]);

    // printLabel fails without fallback
    $resPrint = KomerceService::printLabel('BOOK-TEST-123');
    expect($resPrint['success'])->toBeFalse();
    expect($resPrint['error'])->toBe('Label generation failed');

    // cancelShipment fails without fallback
    $resCancel = KomerceService::cancelShipment('BOOK-TEST-123');
    expect($resCancel['success'])->toBeFalse();
    expect($resCancel['error'])->toBe('Cancellation failed');

    // getShipmentHistory fails without fallback
    $resHistory = KomerceService::getShipmentHistory('RES-TEST-123', 'jne');
    expect($resHistory['success'])->toBeFalse();
    expect($resHistory['error'])->toBe('AWB not found');
});

test('KomerceService::storeShipment calculates discount correctly when transaction has discounts', function () {
    Setting::updateOrCreate(['key' => 'shipping_delivery_key'], ['value' => 'mock_key']);
    Setting::updateOrCreate(['key' => 'store_name'], ['value' => 'My Store']);
    Setting::updateOrCreate(['key' => 'store_phone'], ['value' => '081234567890']);
    Setting::updateOrCreate(['key' => 'store_address'], ['value' => 'Jl. Merdeka No. 5']);
    Setting::updateOrCreate(['key' => 'store_email'], ['value' => 'store@example.com']);
    Setting::updateOrCreate(['key' => 'latitude'], ['value' => '-7.274631']);
    Setting::updateOrCreate(['key' => 'longitude'], ['value' => '109.207174']);

    $category = Category::create([
        'name' => 'Category A',
        'slug' => 'cat-a',
        'icon' => 'ti-home',
    ]);

    $product = Product::create([
        'name' => 'Tenda Dome',
        'slug' => 'tenda-dome',
        'sku' => 'TENDA-1',
        'category_id' => $category->id,
        'summary' => 'Summary',
        'description' => 'Description',
        'weight' => 2000,
        'length' => 15,
        'width' => 12,
        'height' => 10,
        'active' => true,
    ]);

    $this->transaction->items()->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'product_sku' => $product->sku,
        'harga_akhir' => 405000,
        'harga_jual' => 405000,
        'quantity' => 1,
        'subtotal' => 405000,
    ]);

    // Gross total = subtotal (405000) + shipping_fee (10500) + admin_fee (5000) + application_fee (10000) = 430500
    // Insurance = (405000 * 0.002) + 5000 = 5810
    // So Komerce Gross = 430500 + 5810 = 436310
    // We set grand_total = 430500 (since store did not collect insurance)
    // So discount should absorb the insurance value: 436310 - 430500 = 5810
    $this->transaction->update([
        'shipping_courier' => 'jne',
        'shipping_service' => 'JNEFlat',
        'shipping_fee' => 10500,
        'admin_fee' => 5000,
        'application_fee' => 10000,
        'grand_total' => 430500,
    ]);

    Http::fake([
        'https://api-sandbox.collaborator.komerce.id/order/api/v1/orders/store*' => function (Request $request) {
            $data = $request->data();
            expect($data['additional_cost'])->toBe(15000); // maps to application_fee + admin_fee
            expect($data['service_fee'])->toBe(0);
            expect($data['is_insurance'])->toBe(1);
            expect($data['insurance_value'])->toBe(5799);
            expect($data['discount'])->toBe(0); // 0 because item price was adjusted
            expect($data['order_details'][0]['product_price'])->toBe(399201);
            expect($data['order_details'][0]['subtotal'])->toBe(399201);
            expect($data['grand_total'])->toBe(430500);
            expect($data['shipping_cost'])->toBe(10500);

            return Http::response([
                'success' => true,
                'data' => [
                    'booking_code' => 'BOOK-OK-123',
                    'airway_bill' => 'RES-OK-123',
                ],
            ], 200);
        },
    ]);

    $response = KomerceService::storeShipment($this->transaction);
    expect($response['success'])->toBeTrue();
});
