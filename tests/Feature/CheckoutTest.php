<?php

use App\Models\CartItem;
use App\Models\Category;
use App\Models\CustomerAddress;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Promotion;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

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

    $this->productStock = ProductStock::create([
        'product_id' => $this->product->id,
        'stock' => 10,
        'is_unlimited' => false,
    ]);

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
        'bank_name' => 'BCA',
        'account_number' => '1234567890',
        'account_name' => 'Test Store',
        'is_active' => true,
        'admin_fee' => 0,
    ]);

    $this->cartItem = CartItem::create([
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'product_variant_id' => null,
        'quantity' => 2,
        'is_checked' => true,
    ]);
});

test('authenticated user can access checkout page', function () {
    $response = $this->actingAs($this->user)->get(route('checkout.index'));
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Storefront/Checkout')
        ->has('cartItems', 1)
        ->has('addresses', 1)
        ->has('paymentMethods', 1)
    );
});

test('guest is redirected from checkout page', function () {
    $response = $this->get(route('checkout.index'));
    $response->assertRedirect('/login');
});

test('checkout redirects to cart if no items checked', function () {
    $this->cartItem->update(['is_checked' => false]);

    $response = $this->actingAs($this->user)->get(route('checkout.index'));
    $response->assertRedirect(route('cart.index'));
});

test('customer can place an order and stock is deducted', function () {
    $initialStock = $this->productStock->stock;

    $response = $this->actingAs($this->user)->post(route('checkout.store'), [
        'customer_address_id' => $this->address->id,
        'payment_method_id' => $this->paymentMethod->id,
        'shipping_courier' => 'jne',
        'shipping_service' => 'REG',
        'shipping_etd' => '2-3',
        'shipping_fee' => 25000,
        'notes' => 'Test order',
    ]);

    // Should redirect to transaction detail page
    $response->assertRedirect();

    // Transaction created
    $transaction = Transaction::where('user_id', $this->user->id)->first();
    expect($transaction)->not->toBeNull();
    expect($transaction->status)->toBe('belum_bayar');
    expect($transaction->shipping_courier)->toBe('jne');

    // Transaction item created
    $this->assertDatabaseHas('transaction_items', [
        'transaction_id' => $transaction->id,
        'product_id' => $this->product->id,
        'quantity' => 2,
    ]);

    // Payment record created
    $this->assertDatabaseHas('transaction_payments', [
        'transaction_id' => $transaction->id,
        'status' => 'pending',
    ]);

    // Stock reduced
    $this->productStock->refresh();
    expect($this->productStock->stock)->toBe($initialStock - 2);

    // Stock movement recorded
    $this->assertDatabaseHas('stock_movements', [
        'product_id' => $this->product->id,
        'transaction_id' => $transaction->id,
        'type' => 'keluar',
        'quantity' => -2,
    ]);

    // Cart item removed
    $this->assertDatabaseMissing('cart_items', [
        'id' => $this->cartItem->id,
    ]);
});

test('checkout calculates grand total correctly', function () {
    $response = $this->actingAs($this->user)->post(route('checkout.store'), [
        'customer_address_id' => $this->address->id,
        'payment_method_id' => $this->paymentMethod->id,
        'shipping_courier' => 'pos',
        'shipping_service' => 'Paket',
        'shipping_fee' => 15000,
    ]);

    $transaction = Transaction::where('user_id', $this->user->id)->first();

    // 2 × 5.000.000 + 15.000 shipping = 10.015.000
    expect((int) $transaction->grand_total)->toBe(10015000);
    expect((int) $transaction->subtotal)->toBe(10000000);
    expect((int) $transaction->shipping_fee)->toBe(15000);
});

test('checkout fails with invalid address', function () {
    $otherUser = User::factory()->create();
    $otherAddress = CustomerAddress::create([
        'user_id' => $otherUser->id,
        'receiver_name' => 'Other',
        'phone_number' => '08111111111',
        'label' => 'Kantor',
        'full_address' => 'Jl. Lain No. 2',
        'is_primary' => true,
    ]);

    $response = $this->actingAs($this->user)->post(route('checkout.store'), [
        'customer_address_id' => $otherAddress->id,
        'payment_method_id' => $this->paymentMethod->id,
        'shipping_courier' => 'jne',
        'shipping_service' => 'REG',
        'shipping_fee' => 25000,
    ]);

    $response->assertStatus(404);
    $this->assertDatabaseCount('transactions', 0);
});

test('voucher belanja applies discount correctly', function () {
    Promotion::create([
        'name' => 'Diskon 10%',
        'type' => 'voucher_belanja',
        'code' => 'DISKON10',
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'min_purchase' => 0,
        'max_discount' => null,
        'start_time' => now()->subHour(),
        'end_time' => now()->addDay(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)->post(route('checkout.apply-voucher'), [
        'code' => 'DISKON10',
        'subtotal' => 10000000,
        'shipping_fee' => 25000,
    ]);

    $response->assertOk()->assertJson([
        'valid' => true,
        'discount_amount' => 1000000.0,
        'shipping_discount' => 0,
    ]);
});

test('voucher gratis ongkir applies shipping discount', function () {
    Promotion::create([
        'name' => 'Gratis Ongkir',
        'type' => 'voucher_gratis_ongkir',
        'code' => 'GRATIS',
        'discount_type' => null,
        'discount_value' => 0,
        'max_discount' => 50000,
        'start_time' => now()->subHour(),
        'end_time' => now()->addDay(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)->post(route('checkout.apply-voucher'), [
        'code' => 'GRATIS',
        'subtotal' => 5000000,
        'shipping_fee' => 30000,
    ]);

    $response->assertOk()->assertJson([
        'valid' => true,
        'shipping_discount' => 30000.0,
        'discount_amount' => 0,
    ]);
});

test('expired voucher returns invalid', function () {
    Promotion::create([
        'name' => 'Expired Voucher',
        'type' => 'voucher_belanja',
        'code' => 'EXPIRED',
        'discount_type' => 'percentage',
        'discount_value' => 5,
        'start_time' => now()->subDays(2),
        'end_time' => now()->subDay(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)->post(route('checkout.apply-voucher'), [
        'code' => 'EXPIRED',
        'subtotal' => 100000,
        'shipping_fee' => 10000,
    ]);

    $response->assertOk()->assertJson(['valid' => false]);
});
