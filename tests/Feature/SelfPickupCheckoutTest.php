<?php

use App\Models\CartItem;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['broadcasting.default' => 'log']);

    $this->user = User::factory()->create();

    $this->category = Category::create([
        'name' => 'Badminton',
        'slug' => 'badminton',
        'icon' => 'ti-box',
    ]);

    $this->product = Product::create([
        'name' => 'Raket Badminton',
        'slug' => 'raket-badminton',
        'sku' => 'RKT-001',
        'category_id' => $this->category->id,
        'summary' => 'Summary',
        'description' => 'Description',
        'active' => true,
    ]);

    $this->product->productPrice()->create(['price' => 100000, 'cost' => 50000]);

    $this->productStock = ProductStock::create([
        'product_id' => $this->product->id,
        'stock' => 10,
        'is_unlimited' => false,
    ]);

    $this->paymentMethod = PaymentMethod::create([
        'name' => 'Transfer BCA',
        'type' => 'manual',
        'bank_name' => 'BCA',
        'account_number' => '1234567890',
        'account_name' => 'Test Store',
        'is_active' => true,
        'admin_fee' => 0,
    ]);

    Setting::updateOrCreate(['key' => 'self_pickup_enabled'], ['value' => '1']);
    Setting::updateOrCreate(['key' => 'self_pickup_fee'], ['value' => '1000']);
});

test('customer can calculate shipping cost for self_pickup without destination or address', function () {
    $response = $this->actingAs($this->user)->post(route('checkout.shipping-cost'), [
        'courier' => 'self_pickup',
        'weight' => 1000,
        'destination' => '',
        'address_id' => null,
    ]);

    $response->assertOk();
    $data = $response->json();
    expect($data['results'])->toHaveCount(1);
    expect($data['results'][0]['code'])->toBe('self_pickup');
    expect($data['results'][0]['costs'][0]['service'])->toBe('Self Pickup');
    expect((float) $data['results'][0]['costs'][0]['cost'][0]['value'])->toBe(1000.0);
});

test('customer can process checkout with self_pickup without address', function () {
    CartItem::create([
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
        'is_checked' => true,
    ]);

    $response = $this->actingAs($this->user)->post(route('checkout.store'), [
        'customer_address_id' => null,
        'payment_method_id' => $this->paymentMethod->id,
        'shipping_courier' => 'self_pickup',
        'shipping_service' => 'Self Pickup',
        'shipping_fee' => 1000,
        'shipping_etd' => '0',
    ]);

    $response->assertRedirect();
    $transaction = Transaction::latest('created_at')->first();
    expect($transaction)->not->toBeNull();
    expect($transaction->shipping_courier)->toBe('self_pickup');
    expect($transaction->shipping_service)->toBe('Self Pickup');
    expect((float) $transaction->shipping_fee)->toBe(1000.0);
    expect($transaction->customer_address_id)->toBeNull();
});
