<?php

use App\Models\Category;
use App\Models\CustomerAddress;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->category = Category::create([
        'name' => 'Elektronik',
        'slug' => 'elektronik',
        'icon' => 'ti-cpu',
    ]);

    $this->product = Product::create([
        'name' => 'Laptop Test',
        'slug' => 'laptop-test',
        'sku' => 'LAP-TEST',
        'category_id' => $this->category->id,
        'summary' => 'Summary',
        'description' => 'Description',
        'active' => true,
    ]);

    $this->product->productPrice()->create(['price' => 5000000, 'cost' => 3000000]);

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
        'name' => 'Midtrans Gateway',
        'type' => 'gateway',
        'api_key' => 'SB-Mid-server-YvWfBSvBdRvLwqzUc_TmKCHH', // Server Key
        'api_secret' => 'SB-Mid-client-15KqQ2A7XYrBc5cL', // Client Key
        'is_active' => true,
        'admin_fee' => 0,
        'settings' => [
            'url' => 'https://app.sandbox.midtrans.com',
            'webhook_token' => 'dummy-token',
        ],
    ]);

    $this->transaction = Transaction::create([
        'transaction_number' => 'TRX-999999-TEST',
        'user_id' => $this->user->id,
        'customer_address_id' => $this->address->id,
        'payment_method_id' => $this->paymentMethod->id,
        'status' => 'belum_bayar',
        'subtotal' => 5000000,
        'discount_amount' => 0,
        'shipping_fee' => 20000,
        'shipping_discount' => 0,
        'admin_fee' => 0,
        'application_fee' => 0,
        'grand_total' => 5020000,
        'shipping_courier' => 'jne',
        'shipping_service' => 'REG',
    ]);

    $this->payment = TransactionPayment::create([
        'transaction_id' => $this->transaction->id,
        'payment_method_id' => $this->paymentMethod->id,
        'amount' => 5020000,
        'status' => 'pending',
    ]);
});

test('webhook returns 400 when order_id is missing', function () {
    $response = $this->postJson('/api/payment/midtrans/callback', [
        'status_code' => '200',
        'gross_amount' => '5020000.00',
        'transaction_status' => 'settlement',
    ]);

    $response->assertStatus(400);
});

test('webhook returns 404 when transaction is not found', function () {
    $orderId = 'TRX-UNKNOWN';
    $statusCode = '200';
    $grossAmount = '5020000.00';
    $serverKey = $this->paymentMethod->api_key;

    $signature = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

    $response = $this->postJson('/api/payment/midtrans/callback', [
        'order_id' => $orderId,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'signature_key' => $signature,
        'transaction_status' => 'settlement',
    ]);

    $response->assertStatus(404);
});

test('webhook returns 401 on invalid signature key', function () {
    $response = $this->postJson('/api/payment/midtrans/callback', [
        'order_id' => $this->transaction->transaction_number,
        'status_code' => '200',
        'gross_amount' => '5020000.00',
        'signature_key' => 'wrong-signature',
        'transaction_status' => 'settlement',
    ]);

    $response->assertStatus(401);
});

test('webhook processes settlement callback and updates transaction to paid', function () {
    $orderId = $this->transaction->transaction_number;
    $statusCode = '200';
    $grossAmount = '5020000.00';
    $serverKey = $this->paymentMethod->api_key;

    $signature = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

    $response = $this->postJson('/api/payment/midtrans/callback', [
        'order_id' => $orderId,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'signature_key' => $signature,
        'transaction_id' => 'midtrans-trans-id-123',
        'transaction_status' => 'settlement',
    ]);

    $response->assertOk();
    $response->assertJson(['status' => 'success']);

    $this->transaction->refresh();
    $this->payment->refresh();

    expect($this->transaction->status)->toBe('diproses');
    expect($this->payment->status)->toBe('confirmed');
    expect($this->payment->gateway_transaction_id)->toBe('midtrans-trans-id-123');
});

test('webhook processes expire/cancel callback and updates transaction to cancelled', function () {
    $orderId = $this->transaction->transaction_number;
    $statusCode = '407';
    $grossAmount = '5020000.00';
    $serverKey = $this->paymentMethod->api_key;

    $signature = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

    $response = $this->postJson('/api/payment/midtrans/callback', [
        'order_id' => $orderId,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'signature_key' => $signature,
        'transaction_status' => 'expire',
    ]);

    $response->assertOk();
    $response->assertJson(['status' => 'success']);

    $this->transaction->refresh();
    $this->payment->refresh();

    expect($this->transaction->status)->toBe('batal');
    expect($this->payment->status)->toBe('rejected');
    expect($this->transaction->cancel_reason)->toContain('Pembayaran otomatis dibatalkan');
});
