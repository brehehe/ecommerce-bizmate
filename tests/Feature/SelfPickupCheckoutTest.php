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
use Spatie\Permission\Models\Role;

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

test('customer can process checkout with self_pickup and custom pickup time', function () {
    CartItem::create([
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
        'is_checked' => true,
    ]);

    $pickupDate = now()->addDay()->format('Y-m-d');
    $pickupTime = '15:30';

    $response = $this->actingAs($this->user)->post(route('checkout.store'), [
        'customer_address_id' => null,
        'payment_method_id' => $this->paymentMethod->id,
        'shipping_courier' => 'self_pickup',
        'shipping_service' => 'Self Pickup',
        'shipping_fee' => 1000,
        'shipping_etd' => '0',
        'pickup_date' => $pickupDate,
        'pickup_time' => $pickupTime,
        'pickup_at' => "{$pickupDate} {$pickupTime}:00",
    ]);

    $response->assertRedirect();
    $transaction = Transaction::latest('created_at')->first();
    expect($transaction)->not->toBeNull();
    expect($transaction->shipping_courier)->toBe('self_pickup');
    expect($transaction->pickup_at)->not->toBeNull();
    expect($transaction->pickup_at->format('Y-m-d H:i'))->toBe("{$pickupDate} 15:30");
    expect($transaction->pickup_at_formatted)->not->toBeNull();
});

test('admin can update pickup time for self_pickup transaction', function () {
    $admin = User::factory()->create();
    $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
    $admin->assignRole($adminRole);

    $transaction = Transaction::create([
        'transaction_number' => Transaction::generateNumber(),
        'user_id' => $this->user->id,
        'customer_address_id' => null,
        'payment_method_id' => $this->paymentMethod->id,
        'status' => 'diproses',
        'subtotal' => 100000,
        'shipping_fee' => 1000,
        'grand_total' => 101000,
        'shipping_courier' => 'self_pickup',
        'shipping_service' => 'Self Pickup',
        'pickup_at' => now()->addHours(2),
    ]);

    $newPickupAt = now()->addDays(2)->format('Y-m-d H:i:s');

    $response = $this->actingAs($admin)->post(route('admin.transactions.update-pickup-time', $transaction->id), [
        'pickup_at' => $newPickupAt,
    ]);

    $response->assertRedirect();
    $transaction->refresh();
    expect($transaction->pickup_at->format('Y-m-d H:i:s'))->toBe($newPickupAt);
});
