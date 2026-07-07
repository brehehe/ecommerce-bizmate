<?php

use App\Models\CartItem;
use App\Models\Category;
use App\Models\CustomerAddress;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Promotion;
use App\Models\Setting;
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

test('double voucher applies both discounts successfully', function () {
    $voucherBelanja = Promotion::create([
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

    $voucherOngkir = Promotion::create([
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
        'code' => 'DISKON10,GRATIS',
        'subtotal' => 10000000,
        'shipping_fee' => 25000,
    ]);

    $response->assertOk()->assertJson([
        'valid' => true,
        'discount_amount' => 1000000.0,
        'shipping_discount' => 25000.0,
    ]);
});

test('per-user limit restricts usage successfully', function () {
    $voucher = Promotion::create([
        'name' => 'Diskon 10% Batas User',
        'type' => 'voucher_belanja',
        'code' => 'BATASUSER',
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'min_purchase' => 0,
        'max_discount' => null,
        'start_time' => now()->subHour(),
        'end_time' => now()->addDay(),
        'is_active' => true,
        'settings' => [
            'max_uses_per_user' => 1,
        ],
    ]);

    // Apply first time - should be valid
    $response1 = $this->actingAs($this->user)->post(route('checkout.apply-voucher'), [
        'code' => 'BATASUSER',
        'subtotal' => 10000000,
        'shipping_fee' => 25000,
    ]);
    $response1->assertOk()->assertJson(['valid' => true]);

    // Create a previous transaction with this voucher for the user
    Transaction::create([
        'transaction_number' => Transaction::generateNumber(),
        'user_id' => $this->user->id,
        'customer_address_id' => $this->address->id,
        'payment_method_id' => $this->paymentMethod->id,
        'status' => 'belum_bayar',
        'subtotal' => 10000000,
        'discount_amount' => 1000000,
        'shipping_fee' => 25000,
        'shipping_discount' => 0,
        'admin_fee' => 0,
        'application_fee' => 0,
        'grand_total' => 9025000,
        'voucher_code' => 'BATASUSER',
    ]);

    // Apply second time - should be invalid because user limit is reached
    $response2 = $this->actingAs($this->user)->post(route('checkout.apply-voucher'), [
        'code' => 'BATASUSER',
        'subtotal' => 10000000,
        'shipping_fee' => 25000,
    ]);
    $response2->assertOk()->assertJson([
        'valid' => false,
        'message' => 'Batas penggunaan voucher "BATASUSER" per user telah tercapai.',
    ]);
});

test('non-stackable voucher does not apply to promo items', function () {
    // 1. Create a promo_produk campaign for the laptop product
    $promoProduct = Promotion::create([
        'name' => 'Promo Laptop',
        'type' => 'promo_produk',
        'start_time' => now()->subHour(),
        'end_time' => now()->addDay(),
        'is_active' => true,
        'settings' => [
            'min_qty' => 1,
        ],
    ]);
    $promoProduct->items()->create([
        'product_id' => $this->product->id,
        'discount_type' => 'percentage',
        'discount_value' => 20, // 20% discount
    ]);

    // 2. Create another product that is normal priced (not on promotion)
    $normalProduct = Product::create([
        'name' => 'Normal Product',
        'slug' => 'normal-product',
        'sku' => 'NORMAL-TEST',
        'category_id' => $this->category->id,
        'active' => true,
    ]);
    $normalProduct->productPrice()->create(['price' => 1000000, 'cost' => 500000]);
    ProductStock::create([
        'product_id' => $normalProduct->id,
        'stock' => 10,
        'is_unlimited' => false,
    ]);

    // 3. Put both in the cart:
    // We already have a cart item for Laptop Test (qty = 2).
    // Let's create a cart item for the normal product (qty = 1).
    CartItem::create([
        'user_id' => $this->user->id,
        'product_id' => $normalProduct->id,
        'quantity' => 1,
        'is_checked' => true,
    ]);

    // 4. Create a non-stackable voucher (can_stack_with_promos = false)
    Promotion::create([
        'name' => 'Voucher Non-Stackable 10%',
        'type' => 'voucher_belanja',
        'code' => 'NONSTACK10',
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'min_purchase' => 0,
        'start_time' => now()->subHour(),
        'end_time' => now()->addDay(),
        'is_active' => true,
        'settings' => [
            'can_stack_with_promos' => false,
        ],
    ]);

    // 5. Test apply-voucher
    // Total checkout subtotal will be:
    // Laptop: 2 pcs * 4,000,000 (after 20% discount) = 8,000,000 (is_promo = true)
    // Normal Product: 1 pc * 1,000,000 = 1,000,000 (is_promo = false)
    // Total subtotal = 9,000,000.
    // Non-stackable voucher of 10% should only apply to Normal Product (10% of 1,000,000 = 100,000).
    $response = $this->actingAs($this->user)->post(route('checkout.apply-voucher'), [
        'code' => 'NONSTACK10',
        'subtotal' => 9000000,
        'shipping_fee' => 20000,
    ]);

    $response->assertOk()->assertJson([
        'valid' => true,
        'discount_amount' => 100000.0, // Only 10% of normal product
    ]);
});

test('stackable voucher applies to all items including promo items', function () {
    // 1. Create a promo_produk campaign for the laptop product
    $promoProduct = Promotion::create([
        'name' => 'Promo Laptop 2',
        'type' => 'promo_produk',
        'start_time' => now()->subHour(),
        'end_time' => now()->addDay(),
        'is_active' => true,
        'settings' => [
            'min_qty' => 1,
        ],
    ]);
    $promoProduct->items()->create([
        'product_id' => $this->product->id,
        'discount_type' => 'percentage',
        'discount_value' => 20, // 20% discount
    ]);

    // 2. Create another product that is normal priced (not on promotion)
    $normalProduct = Product::create([
        'name' => 'Normal Product 2',
        'slug' => 'normal-product-2',
        'sku' => 'NORMAL-TEST-2',
        'category_id' => $this->category->id,
        'active' => true,
    ]);
    $normalProduct->productPrice()->create(['price' => 1000000, 'cost' => 500000]);
    ProductStock::create([
        'product_id' => $normalProduct->id,
        'stock' => 10,
        'is_unlimited' => false,
    ]);

    // 3. Put both in the cart:
    // We already have a cart item for Laptop Test (qty = 2).
    // Let's create a cart item for the normal product (qty = 1).
    CartItem::create([
        'user_id' => $this->user->id,
        'product_id' => $normalProduct->id,
        'quantity' => 1,
        'is_checked' => true,
    ]);

    // 4. Create a stackable voucher (can_stack_with_promos = true)
    Promotion::create([
        'name' => 'Voucher Stackable 10%',
        'type' => 'voucher_belanja',
        'code' => 'STACKABLE10',
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'min_purchase' => 0,
        'start_time' => now()->subHour(),
        'end_time' => now()->addDay(),
        'is_active' => true,
        'settings' => [
            'can_stack_with_promos' => true,
        ],
    ]);

    // 5. Test apply-voucher
    // Total subtotal = 9,000,000.
    // Stackable voucher of 10% should apply to full subtotal (10% of 9,000,000 = 900,000).
    $response = $this->actingAs($this->user)->post(route('checkout.apply-voucher'), [
        'code' => 'STACKABLE10',
        'subtotal' => 9000000,
        'shipping_fee' => 20000,
    ]);

    $response->assertOk()->assertJson([
        'valid' => true,
        'discount_amount' => 900000.0, // 10% of entire 9,000,000
    ]);
});

test('customer earns coins when coins are enabled but not used/redeemed', function () {
    Setting::updateOrCreate(['key' => 'coins_enabled'], ['value' => '1']);
    Setting::updateOrCreate(['key' => 'coin_earning_method'], ['value' => 'proportional']);
    Setting::updateOrCreate(['key' => 'coin_earning_rate_rupiah'], ['value' => '1000']);
    Setting::updateOrCreate(['key' => 'coin_earning_rate_coins'], ['value' => '1']);

    $response = $this->actingAs($this->user)->post(route('checkout.store'), [
        'customer_address_id' => $this->address->id,
        'payment_method_id' => $this->paymentMethod->id,
        'shipping_courier' => 'jne',
        'shipping_service' => 'REG',
        'shipping_fee' => 25000,
        'use_coins' => false,
    ]);

    $response->assertRedirect();
    $transaction = Transaction::where('user_id', $this->user->id)->first();
    expect($transaction)->not->toBeNull();
    // Subtotal: 2 * 5.000.000 = 10.000.000
    // Proportional earn rate: 10.000.000 / 1000 * 1 = 10.000 coins
    expect((int) $transaction->coins_earned)->toBe(10000);
    expect((int) $transaction->coins_redeemed)->toBe(0);
});

test('customer earns no coins when they redeem/use coins for checkout', function () {
    Setting::updateOrCreate(['key' => 'coins_enabled'], ['value' => '1']);
    Setting::updateOrCreate(['key' => 'coin_conversion_rate'], ['value' => '1']);
    Setting::updateOrCreate(['key' => 'coin_min_purchase_redeem'], ['value' => '10000']);
    Setting::updateOrCreate(['key' => 'coin_max_redeem_per_txn'], ['value' => '50000']);
    Setting::updateOrCreate(['key' => 'coin_max_redeem_percentage'], ['value' => '100']);
    Setting::updateOrCreate(['key' => 'coin_earning_method'], ['value' => 'proportional']);
    Setting::updateOrCreate(['key' => 'coin_earning_rate_rupiah'], ['value' => '1000']);
    Setting::updateOrCreate(['key' => 'coin_earning_rate_coins'], ['value' => '1']);

    // Set user coins balance to 1000 coins
    $this->user->update(['coins_balance' => 1000]);

    $response = $this->actingAs($this->user)->post(route('checkout.store'), [
        'customer_address_id' => $this->address->id,
        'payment_method_id' => $this->paymentMethod->id,
        'shipping_courier' => 'jne',
        'shipping_service' => 'REG',
        'shipping_fee' => 25000,
        'use_coins' => true,
    ]);

    $response->assertRedirect();
    $transaction = Transaction::where('user_id', $this->user->id)->first();
    expect($transaction)->not->toBeNull();
    // Subtotal: 10.000.000. Min purchase met.
    // Redeemed coins: 1000 coins (value = Rp 1000).
    // Grand total before coins: 10.025.000. Grand total after coins: 10.024.000.
    expect((int) $transaction->coins_redeemed)->toBe(1000);
    // Since coins were redeemed, coins_earned must be exactly 0!
    expect((int) $transaction->coins_earned)->toBe(0);
});

test('customer can get domestic shipping cost for gosend and grab', function () {
    // Set origin regency id
    Setting::updateOrCreate(['key' => 'regency_id'], ['value' => '3578']);
    // Set latitude and longitude for store
    Setting::updateOrCreate(['key' => 'latitude'], ['value' => '-7.3037207705528235']);
    Setting::updateOrCreate(['key' => 'longitude'], ['value' => '112.71463853127416']);

    // Set coordinates for address (close distance, e.g., in Surabaya)
    $this->address->update([
        'latitude' => -7.250445,
        'longitude' => 112.75083,
        'regency_id' => 3578,
    ]);

    // Query shipping cost for GoSend
    $response = $this->actingAs($this->user)->post(route('checkout.shipping-cost'), [
        'destination' => '3578',
        'weight' => 1000,
        'courier' => 'gojek', // also maps to gosend
    ]);

    $response->assertOk();
    $data = $response->json();
    expect($data['results'])->toHaveCount(1);
    expect($data['results'][0]['code'])->toBe('gosend');
    expect($data['results'][0]['costs'])->toHaveCount(2); // Instant and Sameday

    // Query shipping cost for Grab
    $responseGrab = $this->actingAs($this->user)->post(route('checkout.shipping-cost'), [
        'destination' => '3578',
        'weight' => 1000,
        'courier' => 'grab',
    ]);

    $responseGrab->assertOk();
    $dataGrab = $responseGrab->json();
    expect($dataGrab['results'][0]['code'])->toBe('grab');
});

test('shipping cost fails if gosend or grab distance is greater than 50km', function () {
    // Set origin regency id
    Setting::updateOrCreate(['key' => 'regency_id'], ['value' => '3578']);
    // Set latitude and longitude for store
    Setting::updateOrCreate(['key' => 'latitude'], ['value' => '-7.3037207705528235']);
    Setting::updateOrCreate(['key' => 'longitude'], ['value' => '112.71463853127416']);

    // Set coordinates for address (far distance, e.g. Jakarta coordinates)
    $this->address->update([
        'latitude' => -6.2088,
        'longitude' => 106.8456,
        'regency_id' => '3173',
    ]);

    $response = $this->actingAs($this->user)->post(route('checkout.shipping-cost'), [
        'destination' => '3173',
        'weight' => 1000,
        'courier' => 'gojek',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('error', 'Jarak pengiriman terlalu jauh untuk kurir instant (maksimal 50 km). Jarak saat ini: 659.4 km.');
});

test('checkout fails when COD is selected with incompatible couriers like GoSend or Lion', function () {
    // 1. Create a payment method for COD
    $paymentMethod = PaymentMethod::create([
        'name' => 'Cash On Delivery (COD)',
        'type' => 'manual',
        'is_active' => true,
        'admin_fee' => 2500,
    ]);

    // 2. Put a product in the cart
    CartItem::create([
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
        'is_checked' => true,
    ]);

    // 3. Attempt checkout with GoSend and COD
    $response = $this->actingAs($this->user)->post(route('checkout.store'), [
        'customer_address_id' => $this->address->id,
        'payment_method_id' => $paymentMethod->id,
        'shipping_courier' => 'gosend',
        'shipping_service' => 'Instant',
        'shipping_fee' => 15000,
    ]);

    $response->assertSessionHas('error', 'Metode pembayaran Cash on Delivery (COD) tidak didukung oleh kurir gosend.');

    // 4. Attempt checkout with Lion and COD
    $response2 = $this->actingAs($this->user)->post(route('checkout.store'), [
        'customer_address_id' => $this->address->id,
        'payment_method_id' => $paymentMethod->id,
        'shipping_courier' => 'lion',
        'shipping_service' => 'Reguler',
        'shipping_fee' => 10000,
    ]);

    $response2->assertSessionHas('error', 'Metode pembayaran Cash on Delivery (COD) tidak didukung oleh kurir lion.');
});

test('customer can get tiered store courier shipping cost based on distance', function () {
    // 1. Configure store courier settings
    Setting::updateOrCreate(['key' => 'store_courier_enabled'], ['value' => '1']);
    Setting::updateOrCreate(['key' => 'latitude'], ['value' => '1.0']);
    Setting::updateOrCreate(['key' => 'longitude'], ['value' => '1.0']);
    Setting::updateOrCreate(['key' => 'store_courier_type'], ['value' => 'radius_tiered']);
    Setting::updateOrCreate(['key' => 'store_courier_max_radius'], ['value' => '20']);
    Setting::updateOrCreate(['key' => 'store_courier_tiered_rates'], ['value' => json_encode([
        ['max_distance' => 5, 'fee' => 10000],
        ['max_distance' => 10, 'fee' => 15000],
        ['max_distance' => 15, 'fee' => 20000],
    ])]);

    // 2. Helper to set address distance
    $setDistance = function ($distance) {
        $lat = 1.0 + ($distance / 111.1949);
        $this->address->update([
            'latitude' => $lat,
            'longitude' => 1.0,
        ]);
    };

    // 3. Test tier 1 (4 km -> fee should be 10000)
    $setDistance(4.0);
    $response = $this->actingAs($this->user)->post(route('checkout.shipping-cost'), [
        'weight' => 1000,
        'courier' => 'store_courier',
        'address_id' => $this->address->id,
    ]);
    $response->assertOk();
    $data = $response->json();
    expect($data['results'][0]['costs'][0]['cost'][0]['value'])->toBe(10000);

    // 4. Test tier 2 (8 km -> fee should be 15000)
    $setDistance(8.0);
    $response = $this->actingAs($this->user)->post(route('checkout.shipping-cost'), [
        'weight' => 1000,
        'courier' => 'store_courier',
        'address_id' => $this->address->id,
    ]);
    $response->assertOk();
    $data = $response->json();
    expect($data['results'][0]['costs'][0]['cost'][0]['value'])->toBe(15000);

    // 5. Test tier 3 (12 km -> fee should be 20000)
    $setDistance(12.0);
    $response = $this->actingAs($this->user)->post(route('checkout.shipping-cost'), [
        'weight' => 1000,
        'courier' => 'store_courier',
        'address_id' => $this->address->id,
    ]);
    $response->assertOk();
    $data = $response->json();
    expect($data['results'][0]['costs'][0]['cost'][0]['value'])->toBe(20000);

    // 6. Test fallback (18 km -> fee should fallback to highest tier: 20000)
    $setDistance(18.0);
    $response = $this->actingAs($this->user)->post(route('checkout.shipping-cost'), [
        'weight' => 1000,
        'courier' => 'store_courier',
        'address_id' => $this->address->id,
    ]);
    $response->assertOk();
    $data = $response->json();
    expect($data['results'][0]['costs'][0]['cost'][0]['value'])->toBe(20000);

    // 7. Test exceeds max radius (25 km -> should return 422 error)
    $setDistance(25.0);
    $response = $this->actingAs($this->user)->post(route('checkout.shipping-cost'), [
        'weight' => 1000,
        'courier' => 'store_courier',
        'address_id' => $this->address->id,
    ]);
    $response->assertStatus(422);
    $response->assertJsonPath('error', 'Jarak pengiriman melebihi radius maksimal Kurir Toko (maksimal 20 km). Jarak saat ini: 25 km.');
});

test('customer can get store courier shipping cost with distance rounded up', function () {
    // 1. Configure store courier settings
    Setting::updateOrCreate(['key' => 'store_courier_enabled'], ['value' => '1']);
    Setting::updateOrCreate(['key' => 'latitude'], ['value' => '1.0']);
    Setting::updateOrCreate(['key' => 'longitude'], ['value' => '1.0']);
    Setting::updateOrCreate(['key' => 'store_courier_type'], ['value' => 'radius']);
    Setting::updateOrCreate(['key' => 'store_courier_per_km_fee'], ['value' => '10000']);
    Setting::updateOrCreate(['key' => 'store_courier_max_radius'], ['value' => '20']);
    Setting::updateOrCreate(['key' => 'store_courier_round_up'], ['value' => '1']);

    // 2. Helper to set address distance
    $setDistance = function ($distance) {
        $lat = 1.0 + ($distance / 111.1949);
        $this->address->update([
            'latitude' => $lat,
            'longitude' => 1.0,
        ]);
    };

    // 3. Test fractional distance (0.2 km -> rounded up to 1 km -> cost should be 10000)
    $setDistance(0.2);
    $response = $this->actingAs($this->user)->post(route('checkout.shipping-cost'), [
        'weight' => 1000,
        'courier' => 'store_courier',
        'address_id' => $this->address->id,
    ]);
    $response->assertOk();
    $data = $response->json();
    expect((float) $data['results'][0]['costs'][0]['cost'][0]['value'])->toBe(10000.0);

    // 4. Turn off round up (0.2 km -> exact calculation -> cost should be 2000)
    Setting::updateOrCreate(['key' => 'store_courier_round_up'], ['value' => '0']);
    $response = $this->actingAs($this->user)->post(route('checkout.shipping-cost'), [
        'weight' => 1000,
        'courier' => 'store_courier',
        'address_id' => $this->address->id,
    ]);
    $response->assertOk();
    $data = $response->json();
    expect((float) $data['results'][0]['costs'][0]['cost'][0]['value'])->toBe(2000.0);
});

test('points voucher is resolved with correct points based on new or existing user status', function () {
    // 1. Enable coins
    Setting::updateOrCreate(['key' => 'coins_enabled'], ['value' => '1']);

    // 2. Create points voucher
    $voucher = Promotion::create([
        'name' => 'Voucher Koin Baru',
        'type' => 'voucher_belanja',
        'code' => 'KOINBARU',
        'discount_type' => 'fixed',
        'discount_value' => 0,
        'start_time' => now()->subDay(),
        'end_time' => now()->addDay(),
        'is_active' => true,
        'settings' => [
            'is_points_voucher' => true,
            'points_new_user' => 10000,
            'points_existing_user' => 5000,
        ],
    ]);

    // 3. Resolve for new user (no transactions)
    $response = $this->actingAs($this->user)->post(route('checkout.apply-voucher'), [
        'code' => 'KOINBARU',
        'subtotal' => 100000,
        'shipping_fee' => 10000,
    ]);
    $response->assertOk();
    $data = $response->json();
    expect($data['valid'])->toBeTrue();
    expect((int) $data['discount_amount'])->toBe(0);
    expect($data['is_points_voucher'])->toBeTrue();
    expect($data['voucher_points'])->toBe(10000);

    // 4. Create an active transaction for the user so they become an existing user
    Transaction::create([
        'transaction_number' => 'TRX-PAST-001',
        'user_id' => $this->user->id,
        'payment_method_id' => $this->paymentMethod->id,
        'status' => 'selesai',
        'subtotal' => 50000,
        'discount_amount' => 0,
        'shipping_fee' => 10000,
        'grand_total' => 60000,
    ]);

    // 5. Resolve for existing user
    $response = $this->actingAs($this->user)->post(route('checkout.apply-voucher'), [
        'code' => 'KOINBARU',
        'subtotal' => 100000,
        'shipping_fee' => 10000,
    ]);
    $response->assertOk();
    $data = $response->json();
    expect($data['valid'])->toBeTrue();
    expect($data['voucher_points'])->toBe(5000);
});

test('checkout stores correct coins_earned when points voucher is used and bypasses normal earning', function () {
    // 1. Enable coins and configure proportional earning (1000 Rp = 1 Coin)
    Setting::updateOrCreate(['key' => 'coins_enabled'], ['value' => '1']);
    Setting::updateOrCreate(['key' => 'coin_conversion_rate'], ['value' => '1']);
    Setting::updateOrCreate(['key' => 'coin_earning_method'], ['value' => 'proportional']);
    Setting::updateOrCreate(['key' => 'coin_earning_rate_rupiah'], ['value' => '1000']);
    Setting::updateOrCreate(['key' => 'coin_earning_rate_coins'], ['value' => '1']);

    // 2. Create points voucher
    $voucher = Promotion::create([
        'name' => 'Voucher Koin Baru',
        'type' => 'voucher_belanja',
        'code' => 'KOINBARU',
        'discount_type' => 'fixed',
        'discount_value' => 0,
        'start_time' => now()->subDay(),
        'end_time' => now()->addDay(),
        'is_active' => true,
        'settings' => [
            'is_points_voucher' => true,
            'points_new_user' => 10000,
            'points_existing_user' => 5000,
        ],
    ]);

    // Subtotal is 1000000 (from 2 * 5000000 Laptop).
    // Normally proportional points would be 1000000 / 1000 * 1 = 1000 points.
    // But points voucher for new user grants 10000 points, which should override/bypass it!

    $response = $this->actingAs($this->user)->post(route('checkout.store'), [
        'customer_address_id' => $this->address->id,
        'payment_method_id' => $this->paymentMethod->id,
        'shipping_courier' => 'jne',
        'shipping_service' => 'REG',
        'shipping_fee' => 15000,
        'voucher_code' => 'KOINBARU',
    ]);

    $response->assertRedirect();
    $transaction = Transaction::latest('created_at')->first();
    expect($transaction->voucher_code)->toBe('KOINBARU');
    expect((int) $transaction->discount_amount)->toBe(0);
    // User is new -> should get exactly 10000 points, overriding regular 1000 proportional points
    expect((int) $transaction->coins_earned)->toBe(10000);
});
