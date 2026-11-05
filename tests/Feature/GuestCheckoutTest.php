<?php

use App\Mail\OrderConfirmation;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Administrator', 'guard_name' => 'web']);

    $this->category = Category::create([
        'name' => 'Elektronik',
        'slug' => 'elektronik',
        'icon' => 'ti-cpu',
    ]);

    $this->product = Product::create([
        'name' => 'Kopi Arabika Test',
        'slug' => 'kopi-arabika-test',
        'sku' => 'KOPI-001',
        'category_id' => $this->category->id,
        'summary' => 'Summary',
        'description' => 'Description',
        'active' => true,
    ]);

    $this->product->productPrice()->create(['price' => 50000, 'cost' => 30000]);

    $this->productStock = ProductStock::create([
        'product_id' => $this->product->id,
        'stock' => 50,
        'is_unlimited' => false,
    ]);

    $this->paymentMethod = PaymentMethod::create([
        'name' => 'Transfer Bank Manual',
        'type' => 'manual',
        'is_active' => true,
        'admin_fee' => 0,
    ]);

    Setting::updateOrCreate(['key' => 'store_name'], ['value' => 'Bizmate Store']);
    Setting::updateOrCreate(['key' => 'regency_id'], ['value' => '3578']);
});

test('guest cannot access checkout if is_checkout_without_login is disabled', function () {
    config(['app.is_checkout_without_login' => false]);

    $response = $this->get(route('checkout.index'));
    $response->assertRedirect(route('login'));
});

test('guest can access cart and add item to guest cart session when is_checkout_without_login is enabled', function () {
    config(['app.is_checkout_without_login' => true]);

    $response = $this->post(route('cart.store'), [
        'product_id' => $this->product->id,
        'quantity' => 2,
    ]);

    $response->assertRedirect();
    $guestCart = session('guest_cart', []);
    expect($guestCart)->not->toBeEmpty();

    $cartResponse = $this->get(route('cart.index'));
    $cartResponse->assertOk();
});

test('guest can access checkout page when is_checkout_without_login is enabled', function () {
    config(['app.is_checkout_without_login' => true]);

    $response = $this->withSession([
        'guest_cart' => [
            "{$this->product->id}_0" => [
                'id' => 'guest-123',
                'product_id' => $this->product->id,
                'product_variant_id' => null,
                'quantity' => 1,
                'is_checked' => true,
            ],
        ],
    ])->get(route('checkout.index'));

    $response->assertOk();
});

test('guest checkout validates mandatory phone and email fields', function () {
    config(['app.is_checkout_without_login' => true]);

    $response = $this->withSession([
        'guest_cart' => [
            "{$this->product->id}_0" => [
                'id' => 'guest-123',
                'product_id' => $this->product->id,
                'product_variant_id' => null,
                'quantity' => 1,
                'is_checked' => true,
            ],
        ],
    ])->post(route('checkout.store'), [
        'customer_name' => 'Tamu Pembeli',
        'payment_method_id' => $this->paymentMethod->id,
        'shipping_courier' => 'self_pickup',
        'shipping_service' => 'Self Pickup',
        'shipping_fee' => 0,
        'pickup_date' => now()->toDateString(),
        'pickup_time' => '10:00',
    ]);

    $response->assertSessionHasErrors(['customer_email', 'customer_phone']);
});

test('guest can checkout successfully with email queued and redirects to track order page', function () {
    Mail::fake();
    config(['app.is_checkout_without_login' => true]);
    Setting::updateOrCreate(['key' => 'self_pickup_enabled'], ['value' => '1']);

    $response = $this->withSession([
        'guest_cart' => [
            "{$this->product->id}_0" => [
                'id' => 'guest-123',
                'product_id' => $this->product->id,
                'product_variant_id' => null,
                'quantity' => 1,
                'is_checked' => true,
            ],
        ],
    ])->post(route('checkout.store'), [
        'customer_name' => 'Budi Santoso',
        'customer_email' => 'budi.santoso@example.com',
        'customer_phone' => '081234567890',
        'payment_method_id' => $this->paymentMethod->id,
        'shipping_courier' => 'self_pickup',
        'shipping_service' => 'Self Pickup',
        'shipping_fee' => 0,
        'pickup_date' => now()->toDateString(),
        'pickup_time' => '10:00',
    ]);

    $transaction = Transaction::where('customer_email', 'budi.santoso@example.com')
        ->orWhereHas('user', fn ($q) => $q->where('email', 'budi.santoso@example.com'))
        ->first();

    expect($transaction)->not->toBeNull();
    $response->assertRedirect(route('order.track', ['trx' => $transaction->transaction_number]));

    // Assert order confirmation email was queued
    Mail::assertQueued(OrderConfirmation::class, function ($mail) {
        return $mail->hasTo('budi.santoso@example.com');
    });
});

test('customer can track order on cek-pesanan page using order number and email or phone', function () {
    config(['app.is_checkout_without_login' => true]);

    $user = User::create([
        'name' => 'Guest Track User',
        'email' => 'trackme@example.com',
        'password' => bcrypt('password'),
    ]);
    $user->assignRole('Customer');

    $transaction = Transaction::create([
        'user_id' => $user->id,
        'transaction_number' => 'TRX-TEST-TRACK-999',
        'total_amount' => 50000,
        'subtotal' => 50000,
        'shipping_fee' => 0,
        'status' => 'menunggu_pembayaran',
        'payment_status' => 'belum_dibayar',
        'payment_method_id' => $this->paymentMethod->id,
        'shipping_courier' => 'self_pickup',
        'shipping_service' => 'Self Pickup',
    ]);

    // Search with wrong contact
    $failResponse = $this->get(route('order.track', [
        'trx' => 'TRX-TEST-TRACK-999',
        'contact' => 'wrong@example.com',
    ]));
    $failResponse->assertOk();
    $failResponse->assertInertia(fn ($page) => $page->where('transaction', null));

    // Search with correct email
    $successResponse = $this->get(route('order.track', [
        'trx' => 'TRX-TEST-TRACK-999',
        'contact' => 'trackme@example.com',
    ]));
    $successResponse->assertOk();
    $successResponse->assertInertia(fn ($page) => $page->where('transaction.id', $transaction->id));
});
