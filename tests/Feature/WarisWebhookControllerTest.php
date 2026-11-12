<?php

use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->category = Category::create([
        'name' => 'Olahraga',
        'slug' => 'olahraga',
        'icon' => 'ti-ball-tennis',
    ]);

    $this->product = Product::create([
        'name' => 'Raket Padel Babolat',
        'slug' => 'raket-padel-babolat',
        'sku' => 'BAB-PADEL',
        'category_id' => $this->category->id,
        'summary' => 'Raket Padel',
        'description' => 'Deskripsi',
        'active' => true,
    ]);

    $this->product->productPrice()->create(['price' => 1000000, 'cost' => 800000]);

    $this->paymentMethod = PaymentMethod::create([
        'name' => 'QRIS (Semua Pembayaran / E-Wallet)',
        'type' => 'gateway',
        'api_key' => 'wrs_live_dummy',
        'is_active' => true,
        'admin_fee' => 0,
        'settings' => [
            'gateway' => 'waris',
            'url' => 'https://analisis.giradia.id/api/v1',
            'qris_code' => 'random',
        ],
    ]);

    $this->transaction = Transaction::create([
        'transaction_number' => 'TRX-TEST-WARIS-001',
        'user_id' => $this->user->id,
        'payment_method_id' => $this->paymentMethod->id,
        'status' => 'belum_bayar',
        'payment_status' => 'unpaid',
        'subtotal' => 1000000,
        'grand_total' => 1000000,
        'shipping_courier' => 'self_pickup',
        'customer_name' => 'Budi Santoso',
        'customer_email' => 'budi@example.com',
        'customer_phone' => '081234567890',
    ]);

    $this->payment = TransactionPayment::create([
        'transaction_id' => $this->transaction->id,
        'payment_method_id' => $this->paymentMethod->id,
        'amount' => 1000000,
        'status' => 'pending',
        'gateway_transaction_id' => 'TRX-TEST-WARIS-001',
        'gateway_status' => 'PENDING',
        'gateway_response' => json_encode(['qr_string' => '000201010212...']),
    ]);
});

test('waris webhook updates unpaid transaction to diproses and lunas', function () {
    $payload = [
        'event' => 'payment.received',
        'data' => [
            'reference_id' => 'TRX-TEST-WARIS-001',
            'amount' => 1000000,
            'paid_at' => now()->toIso8601String(),
            'payment_method' => 'BCA_QRIS',
            'bank_sender' => 'BCA Mobile',
            'status' => 'success',
        ],
    ];

    $response = $this->postJson('/api/webhooks/waris', $payload);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'acknowledged',
            'message' => 'Payment processed successfully.',
        ]);

    $this->transaction->refresh();
    $this->payment->refresh();

    expect($this->transaction->status)->toBe('diproses');
    expect($this->transaction->payment_status)->toBe('lunas');
    expect($this->payment->status)->toBe('confirmed');
    expect($this->payment->gateway_status)->toBe('SUCCESS');
});

test('waris webhook verifies signature when secret is configured', function () {
    Setting::updateOrCreate(
        ['key' => 'waris_webhook_secret'],
        ['value' => 'my-super-secret-key']
    );

    $payload = [
        'event' => 'payment.received',
        'data' => [
            'reference_id' => 'TRX-TEST-WARIS-001',
            'amount' => 1000000,
            'status' => 'success',
        ],
    ];

    $rawJson = json_encode($payload);
    $validSignature = hash_hmac('sha256', $rawJson, 'my-super-secret-key');

    // 1. Invalid signature should return 401
    $invalidResponse = $this->call(
        'POST',
        '/api/webhooks/waris',
        [],
        [],
        [],
        ['HTTP_X_WARIS_SIGNATURE' => 'sha256=invalidsignature', 'CONTENT_TYPE' => 'application/json'],
        $rawJson
    );
    $invalidResponse->assertStatus(401);

    // 2. Valid signature should return 200
    $validResponse = $this->call(
        'POST',
        '/api/webhooks/waris',
        [],
        [],
        [],
        ['HTTP_X_WARIS_SIGNATURE' => 'sha256='.$validSignature, 'CONTENT_TYPE' => 'application/json'],
        $rawJson
    );
    $validResponse->assertStatus(200);

    $this->transaction->refresh();
    expect($this->transaction->status)->toBe('diproses');
});

test('waris webhook returns 404 for non-existent reference id', function () {
    $payload = [
        'event' => 'payment.received',
        'data' => [
            'reference_id' => 'TRX-UNKNOWN-99999',
            'amount' => 50000,
            'status' => 'success',
        ],
    ];

    $response = $this->postJson('/api/webhooks/waris', $payload);
    $response->assertStatus(404);
});

test('waris simulate payment artisan command confirms order', function () {
    $this->artisan('waris:simulate-payment', ['order_id' => 'TRX-TEST-WARIS-001'])
        ->expectsOutputToContain('DIPROSES dan LUNAS')
        ->assertSuccessful();

    $this->transaction->refresh();
    expect($this->transaction->status)->toBe('diproses');
    expect($this->transaction->payment_status)->toBe('lunas');
});
