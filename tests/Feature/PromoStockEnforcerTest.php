<?php

use App\Models\CartItem;
use App\Models\Category;
use App\Models\CustomerAddress;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductStock;
use App\Models\Promotion;
use App\Models\PromotionItem;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->category = Category::create([
        'name' => 'Elektronik',
        'slug' => 'elektronik',
        'icon' => 'ti-cpu',
    ]);

    // Create a product with price and stock
    $this->product = Product::create([
        'name' => 'Test Limited Promo Product',
        'slug' => 'test-limited-promo-product',
        'sku' => 'TEST-SKU-PROMO-1',
        'category_id' => $this->category->id,
        'summary' => 'Summary',
        'description' => 'Description',
        'active' => true,
    ]);

    ProductPrice::create([
        'product_id' => $this->product->id,
        'price' => 100000, // Normal price Rp 100.000
    ]);

    ProductStock::create([
        'product_id' => $this->product->id,
        'stock' => 100,
        'is_unlimited' => false,
    ]);

    // Customer Address
    $this->address = CustomerAddress::create([
        'user_id' => $this->user->id,
        'receiver_name' => 'Test User',
        'phone_number' => '08123456789',
        'label' => 'Rumah',
        'full_address' => 'Jl. Test Address No. 1',
        'province_name' => 'Jawa Barat',
        'regency_name' => 'Bandung',
        'district_name' => 'Coblong',
        'village_name' => 'Dago',
        'postal_code' => '40135',
        'is_primary' => true,
        'province_id' => 9,
        'regency_id' => 23,
    ]);

    // Payment Method
    $this->paymentMethod = PaymentMethod::create([
        'name' => 'Transfer Bank',
        'code' => 'transfer_bank',
        'description' => 'Transfer bank manual',
        'is_active' => true,
    ]);
});

test('it correctly applies dynamic price splitting when quantity exceeds remaining promo stock', function () {
    // 1. Create a Flash Sale promotion with promo_stock = 2
    $promo = Promotion::create([
        'name' => 'Flash Sale Limited',
        'type' => 'flash_sale',
        'start_time' => now()->subMinutes(10),
        'end_time' => now()->addHours(2),
        'is_active' => true,
    ]);

    PromotionItem::create([
        'promotion_id' => $promo->id,
        'product_id' => $this->product->id,
        'discount_type' => 'fixed',
        'discount_value' => 20000, // Potongan Rp 20.000 (Harga promo Rp 80.000)
        'promo_price' => 80000,
        'promo_stock' => 2, // Limit stock = 2
    ]);

    // 2. Add 3 units of the product to the cart
    $cartItem = CartItem::create([
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'quantity' => 3,
        'is_checked' => true,
    ]);

    // 3. Request the cart page (triggers CartController index)
    // This will calculate unit price using split pricing: (2 * 80.000 + 1 * 100.000) / 3 = 86.666,67
    $response = $this->actingAs($this->user)->get(route('cart.index'));
    $response->assertSuccessful();

    // Verify session cart_promo_prices has been saved
    $sessionPrices = session('cart_promo_prices');
    expect($sessionPrices)->toHaveKey("p_{$this->product->id}");
    expect(round((float) $sessionPrices["p_{$this->product->id}"]['price'], 2))->toEqual(86666.67);
    expect($sessionPrices["p_{$this->product->id}"]['is_promo'])->toBeTrue();

    // 4. Proceed to Checkout page (triggers CheckoutController index)
    $checkoutResponse = $this->actingAs($this->user)->get('/checkout');
    $checkoutResponse->assertSuccessful();

    // Verify session checkout_promo_prices is saved
    $checkoutSession = session('checkout_promo_prices');
    expect($checkoutSession)->toHaveKey("p_{$this->product->id}");
    expect(round((float) $checkoutSession["p_{$this->product->id}"]['price'], 2))->toEqual(86666.67);
});

test('it blocks checkout when promo stock gets exhausted by another customer', function () {
    // 1. Create a Promo Produk promotion with promo_stock = 1
    $promo = Promotion::create([
        'name' => 'Promo Produk Limited',
        'type' => 'promo_produk',
        'start_time' => now()->subMinutes(10),
        'end_time' => now()->addHours(2),
        'is_active' => true,
        'settings' => [
            'min_qty' => 1,
        ],
    ]);

    PromotionItem::create([
        'promotion_id' => $promo->id,
        'product_id' => $this->product->id,
        'discount_type' => 'fixed',
        'discount_value' => 20000, // Harga promo Rp 80.000
        'promo_price' => 80000,
        'promo_stock' => 1, // Limit stock = 1
    ]);

    // 2. Customer A adds 1 unit to cart and views cart
    $cartItem = CartItem::create([
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
        'is_checked' => true,
    ]);

    // Acting as Customer A: Load cart to set session
    $this->actingAs($this->user)->get(route('cart.index'));
    $this->actingAs($this->user)->get('/checkout'); // Sets checkout session to expect promo price 80.000

    // 3. Customer B buys the product and consumes the last promo stock!
    $customerB = User::factory()->create();
    $transactionB = Transaction::create([
        'transaction_number' => Transaction::generateNumber(),
        'user_id' => $customerB->id,
        'customer_address_id' => $this->address->id,
        'payment_method_id' => $this->paymentMethod->id,
        'status' => 'belum_bayar',
        'subtotal' => 80000,
        'grand_total' => 80000,
    ]);

    TransactionItem::create([
        'transaction_id' => $transactionB->id,
        'product_id' => $this->product->id,
        'product_name' => $this->product->name,
        'product_sku' => $this->product->sku,
        'quantity' => 1,
        'harga_jual' => 80000,
        'harga_akhir' => 80000,
        'subtotal' => 80000,
        'applied_promotion_id' => $promo->id,
        'promo_quantity_used' => 1, // Consumed!
    ]);

    // 4. Customer A now tries to place order (submits checkout store POST)
    // The remaining promo stock is now 0, so calculated price becomes normal (Rp 100.000).
    // The enforcer should block the checkout, redirect back with error flash.
    $response = $this->actingAs($this->user)->post('/checkout', [
        'customer_address_id' => $this->address->id,
        'payment_method_id' => $this->paymentMethod->id,
        'shipping_courier' => 'jne',
        'shipping_service' => 'REG',
        'shipping_fee' => 10000,
        'notes' => 'Tolong diproses cepat',
    ]);

    // Must block and redirect to cart index with warning flash
    $response->assertRedirect(route('cart.index'));
    $response->assertSessionHas('error', "Promo untuk produk {$this->product->name} telah habis. Harga kembali normal.");
});
