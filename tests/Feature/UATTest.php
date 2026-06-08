<?php

use App\Models\CustomerAddress;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * Setup a dummy transaction with items and address.
 */
function createDummyTransaction(): Transaction
{
    $customer = User::factory()->create();

    $address = CustomerAddress::create([
        'user_id' => $customer->id,
        'receiver_name' => 'Receiver Customer',
        'phone_number' => '08123456789',
        'full_address' => 'Jl. Kebon Jeruk No. 12',
        'label' => 'Rumah',
        'is_primary' => true,
    ]);

    $paymentMethod = PaymentMethod::create([
        'name' => 'BCA',
        'type' => 'manual',
        'bank_name' => 'BCA',
        'account_number' => '9876543210',
        'account_name' => 'Toko Test',
        'is_active' => true,
        'admin_fee' => 0,
    ]);

    $product = Product::create([
        'name' => 'Product Test UAT',
        'slug' => 'product-test-uat',
        'sku' => 'SKU-UAT',
        'description' => 'Test UAT Description',
        'is_digital' => false,
        'active' => true,
    ]);

    $transaction = Transaction::create([
        'transaction_number' => 'TRX-UAT-00001',
        'user_id' => $customer->id,
        'customer_address_id' => $address->id,
        'payment_method_id' => $paymentMethod->id,
        'status' => 'belum_bayar',
        'subtotal' => 100000,
        'discount_amount' => 0,
        'shipping_fee' => 15000,
        'shipping_discount' => 0,
        'admin_fee' => 0,
        'grand_total' => 115000,
        'shipping_courier' => 'JNE',
        'shipping_service' => 'REG',
        'booking_code' => 'BOOK-UAT12345',
        'tracking_number' => 'RES-UAT12345',
    ]);

    TransactionItem::create([
        'transaction_id' => $transaction->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'product_sku' => $product->sku,
        'quantity' => 1,
        'harga_jual' => 100000,
        'subtotal' => 100000,
        'hpp' => 60000,
        'harga_akhir' => 100000,
        'diskon_item' => 0,
    ]);

    return $transaction;
}

test('admin can view UAT index page', function () {
    $admin = User::factory()->create();

    $response = $this->actingAs($admin)->get(route('admin.uat.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/UAT')
    );
});

test('admin can run destination step in UAT', function () {
    $admin = User::factory()->create();

    Http::fake([
        '*destination/search*' => Http::response([
            'meta' => [
                'message' => 'Successfully Get Destination Data',
                'code' => 200,
                'status' => 'success',
            ],
            'data' => [
                [
                    'id' => 69363,
                    'label' => 'NGAGELREJO, WONOKROMO, SURABAYA, 60245',
                    'subdistrict_name' => 'NGAGELREJO',
                    'district_name' => 'WONOKROMO',
                    'city_name' => 'SURABAYA',
                    'province_name' => 'JAWA TIMUR',
                    'zip_code' => '60245',
                ],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($admin)->post(route('admin.uat.run', ['step' => 'destination']), [
        'keyword' => 'cibungbulang',
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['curl', 'result']);
    expect($response->json('result.meta.status'))->toBe('success');
});

test('admin can run calculate step in UAT', function () {
    $admin = User::factory()->create();

    Http::fake([
        '*calculate*' => Http::response([
            'meta' => [
                'code' => 200,
                'status' => 'success',
            ],
            'data' => [
                'calculate_reguler' => [],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($admin)->post(route('admin.uat.run', ['step' => 'calculate']), [
        'shipper_destination_id' => 3578,
        'receiver_destination_id' => 69363,
        'weight' => 1.0,
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['curl', 'result']);
    expect($response->json('result.meta.status'))->toBe('success');
});

test('admin can run order step in UAT', function () {
    $admin = User::factory()->create();
    createDummyTransaction();

    Http::fake([
        '*orders/store*' => Http::response([
            'meta' => [
                'code' => 200,
                'status' => 'success',
            ],
            'data' => [
                'booking_code' => 'BOOK-UAT12345',
                'airway_bill' => 'RES-UAT12345',
            ],
        ], 200),
    ]);

    $response = $this->actingAs($admin)->post(route('admin.uat.run', ['step' => 'order']));

    $response->assertOk();
    $response->assertJsonStructure(['curl', 'result']);
    expect($response->json('result.meta.status'))->toBe('success');
    expect($response->json('result.data.booking_code'))->toBe('BOOK-UAT12345');
});

test('admin can run pickup step in UAT', function () {
    $admin = User::factory()->create();
    createDummyTransaction();

    Http::fake([
        '*pickup/request*' => Http::response([
            'meta' => [
                'code' => 200,
                'status' => 'success',
            ],
            'data' => [
                'pickup_code' => 'PKP-UAT12345',
            ],
        ], 200),
    ]);

    $response = $this->actingAs($admin)->post(route('admin.uat.run', ['step' => 'pickup']));

    $response->assertOk();
    $response->assertJsonStructure(['curl', 'result']);
    expect($response->json('result.meta.status'))->toBe('success');
    expect($response->json('result.data.pickup_code'))->toBe('PKP-UAT12345');
});

test('admin can run print label step in UAT', function () {
    $admin = User::factory()->create();
    createDummyTransaction();

    Http::fake([
        '*orders/print-label*' => Http::response([
            'meta' => [
                'code' => 200,
                'status' => 'success',
            ],
            'data' => [
                'label_url' => 'https://sandbox.collaborator.komerce.id/label.pdf',
            ],
        ], 200),
    ]);

    $response = $this->actingAs($admin)->post(route('admin.uat.run', ['step' => 'print_label']));

    $response->assertOk();
    $response->assertJsonStructure(['curl', 'result']);
    expect($response->json('result.meta.status'))->toBe('success');
});

test('admin can run history AWB step in UAT', function () {
    $admin = User::factory()->create();
    createDummyTransaction();

    Http::fake([
        '*history-airway-bill*' => Http::response([
            'meta' => [
                'code' => 200,
                'status' => 'success',
            ],
            'data' => [
                'history' => [],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($admin)->post(route('admin.uat.run', ['step' => 'history_awb']));

    $response->assertOk();
    $response->assertJsonStructure(['curl', 'result']);
    expect($response->json('result.meta.status'))->toBe('success');
});
