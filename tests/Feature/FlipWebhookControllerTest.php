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
        'name' => 'Flip Gateway',
        'type' => 'gateway',
        'api_key' => 'JDJ5JDEzJG5rSXlyTnlINlgzOVk2emxzOUVtNk9PTS9iUnpIcVRTdGlOS0RTSUxzTC83RXNGcGliclhh', // Secret Key
        'api_secret' => '$2y$13$bJAwMLvSexLawNRLvHAeP.8BT7mJorBYqBASfx1FjMN.NsYlZ7LXu', // Validation Token
        'is_active' => true,
        'admin_fee' => 0,
        'settings' => [
            'url' => 'https://bigflip.id/big_sandbox_api',
            'webhook_token' => '$2y$13$bJAwMLvSexLawNRLvHAeP.8BT7mJorBYqBASfx1FjMN.NsYlZ7LXu',
        ],
    ]);

    $this->transaction = Transaction::create([
        'transaction_number' => 'TRX-888888-TEST',
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
        'gateway_transaction_id' => '123456', // link_id in Flip
    ]);
});

test('flip webhook returns 401 when token is invalid', function () {
    $response = $this->postJson('/api/payment/flip/callback', [
        'token' => 'invalid-token',
        'data' => json_encode(['bill_id' => '123456', 'status' => 'SUCCESSFUL']),
    ]);

    $response->assertStatus(401);
});

test('flip webhook returns 400 when data is missing', function () {
    $response = $this->postJson('/api/payment/flip/callback', [
        'token' => '$2y$13$bJAwMLvSexLawNRLvHAeP.8BT7mJorBYqBASfx1FjMN.NsYlZ7LXu',
    ]);

    $response->assertStatus(400);
});

test('flip webhook returns 404 when payment record is not found', function () {
    $response = $this->postJson('/api/payment/flip/callback', [
        'token' => '$2y$13$bJAwMLvSexLawNRLvHAeP.8BT7mJorBYqBASfx1FjMN.NsYlZ7LXu',
        'data' => json_encode(['bill_id' => '999999', 'status' => 'SUCCESSFUL']),
    ]);

    $response->assertStatus(404);
});

test('flip webhook updates transaction and payment status to confirmed on SUCCESSFUL', function () {
    $response = $this->postJson('/api/payment/flip/callback', [
        'token' => '$2y$13$bJAwMLvSexLawNRLvHAeP.8BT7mJorBYqBASfx1FjMN.NsYlZ7LXu',
        'data' => json_encode([
            'bill_id' => '123456',
            'status' => 'SUCCESSFUL',
            'amount' => 5020000,
            'payment_method' => 'BANK_TRANSFER',
        ]),
    ]);

    $response->assertOk();
    $response->assertJson(['status' => 'success']);

    $this->transaction->refresh();
    $this->payment->refresh();

    expect($this->transaction->status)->toBe('diproses');
    expect($this->payment->status)->toBe('confirmed');
    expect($this->payment->gateway_status)->toBe('SUCCESSFUL');
});
