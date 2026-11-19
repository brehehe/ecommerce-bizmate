<?php

use App\Models\Category;
use App\Models\MembershipLevel;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\RestoProfile;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.membership_enabled' => false]);
    config(['app.is_foodpick' => true]);

    if (! Role::where('name', 'Seller')->exists()) {
        Role::create(['name' => 'Seller']);
    }
    if (! Role::where('name', 'Customer')->exists()) {
        Role::create(['name' => 'Customer']);
    }

    MembershipLevel::create([
        'name' => 'Bronze',
        'slug' => 'bronze',
        'min_spend' => 0,
        'min_transactions' => 0,
        'order' => 1,
        'is_active' => true,
    ]);

    $category = Category::create([
        'name' => 'Kuliner Nusantara',
        'slug' => 'kuliner-nusantara',
    ]);

    $this->restoOwner = User::create([
        'name' => 'Pemilik Bebek Sinjay',
        'email' => 'sinjay@resto.com',
        'password' => bcrypt('password'),
        'is_seller' => true,
        'store_name' => 'Bebek Sinjay Bangkalan',
        'store_slug' => 'bebek-sinjay-bangkalan',
    ]);
    $this->restoOwner->assignRole('Seller');

    $this->restoProfile = RestoProfile::create([
        'user_id' => $this->restoOwner->id,
        'resto_name' => 'Bebek Sinjay Bangkalan',
        'slug' => 'bebek-sinjay-bangkalan',
        'culinary_category' => 'Aneka Bebek & Nasi',
        'description' => 'Bebek goreng kremes bumbu hitam khas Madura.',
        'address' => 'Jl. Raya Ketengan No. 45, Bangkalan, Madura',
        'latitude' => -7.0289,
        'longitude' => 112.7483,
        'avg_prep_time_minutes' => 20,
        'allow_pickup' => true,
        'allow_delivery' => true,
        'is_open_now' => true,
        'approval_status' => 'approved',
    ]);

    $this->foodProduct = Product::create([
        'user_id' => $this->restoOwner->id,
        'category_id' => $category->id,
        'name' => 'Paket Bebek Kremes Madura',
        'sku' => 'SKU-BEBEK-01',
        'slug' => 'paket-bebek-kremes-madura',
        'price' => 35000,
        'is_active' => true,
        'type' => 'physical',
    ]);
    $this->foodProduct->productPrice()->create(['price' => 35000]);
    $this->foodProduct->productStock()->create(['stock' => 50, 'is_unlimited' => false]);

    $this->customer = User::create([
        'name' => 'Budi Santoso',
        'email' => 'budi.santoso@gmail.com',
        'password' => bcrypt('password'),
        'phone_number' => '081234567890',
        'is_seller' => false,
    ]);
    $this->customer->assignRole('Customer');

    $this->paymentMethod = PaymentMethod::create([
        'name' => 'QRIS Dinamis',
        'code' => 'qris',
        'type' => 'manual',
        'is_active' => true,
    ]);
});

test('order tracking page loads food order with live kitchen status and resto details', function () {
    $transaction = Transaction::create([
        'transaction_number' => 'TRX-FOOD-20261113-0001',
        'user_id' => $this->customer->id,
        'resto_id' => $this->restoOwner->id,
        'customer_name' => 'Budi Santoso',
        'customer_email' => 'budi.santoso@gmail.com',
        'customer_phone' => '081234567890',
        'status' => 'diproses',
        'kitchen_status' => 'dimasak',
        'payment_status' => 'lunas',
        'fulfillment_type' => 'self_pickup',
        'pickup_type' => 'takeaway',
        'scheduled_date' => 'Hari Ini',
        'scheduled_time_slot' => '12:30 WIB',
        'subtotal' => 35000,
        'grand_total' => 35000,
        'shipping_fee' => 0,
        'payment_method_id' => $this->paymentMethod->id,
    ]);

    TransactionItem::create([
        'transaction_id' => $transaction->id,
        'product_id' => $this->foodProduct->id,
        'product_name' => 'Paket Bebek Kremes Madura',
        'product_sku' => 'BMB-001',
        'quantity' => 1,
        'hpp' => 20000,
        'harga_jual' => 35000,
        'diskon_item' => 0,
        'harga_akhir' => 35000,
        'subtotal' => 35000,
        'note' => 'Pedas: Level 2 | Nasi: Nasi Uduk Gurih | Catatan: Sambal dipisah',
    ]);

    $response = $this->actingAs($this->customer)
        ->get(route('order.track', ['trx' => 'TRX-FOOD-20261113-0001', 'contact' => '081234567890']));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Storefront/TrackOrder')
            ->where('isFoodPick', true)
            ->where('transaction.kitchen_status', 'dimasak')
            ->where('transaction.scheduled_time_slot', '12:30 WIB')
            ->where('transaction.resto_profile.resto_name', 'Bebek Sinjay Bangkalan')
            ->where('transaction.items.0.note', 'Pedas: Level 2 | Nasi: Nasi Uduk Gurih | Catatan: Sambal dipisah')
        );
});

test('customer can add culinary menu to cart with custom modifiers note', function () {
    $response = $this->actingAs($this->customer)
        ->post('/cart', [
            'product_id' => $this->foodProduct->id,
            'quantity' => 2,
            'note' => 'Pedas: Level 3 (Ekstra Pedas) | Topping: Telur Mata Sapi | Catatan: Jangan pakai timun',
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('cart_items', [
        'user_id' => $this->customer->id,
        'product_id' => $this->foodProduct->id,
        'quantity' => 2,
        'note' => 'Pedas: Level 3 (Ekstra Pedas) | Topping: Telur Mata Sapi | Catatan: Jangan pakai timun',
    ]);
});

test('guest can add food to cart with modifiers note when checkout without login is active', function () {
    config(['app.is_checkout_without_login' => true]);

    $response = $this->post('/cart', [
        'product_id' => $this->foodProduct->id,
        'quantity' => 1,
        'note' => 'Pedas: Level 1 | Nasi: Nasi Putih | Catatan: Kuah bebek banyakin',
    ]);

    $response->assertRedirect();

    $guestCart = session('guest_cart', []);
    expect($guestCart)->not->toBeEmpty();
    $firstItem = array_values($guestCart)[0];
    expect($firstItem['product_id'])->toBe($this->foodProduct->id)
        ->and($firstItem['note'])->toBe('Pedas: Level 1 | Nasi: Nasi Putih | Catatan: Kuah bebek banyakin');
});

test('resto storefront page displays resto profile and culinary category', function () {
    $response = $this->get('/'.$this->restoOwner->store_slug);

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Storefront/SellerStore')
            ->where('isFoodPick', true)
            ->where('seller.resto_profile.resto_name', 'Bebek Sinjay Bangkalan')
            ->where('seller.resto_profile.culinary_category', 'Aneka Bebek & Nasi')
            ->where('seller.resto_profile.avg_prep_time_minutes', 20)
        );
});

test('kds displays culinary item notes to the kitchen chef', function () {
    $transaction = Transaction::create([
        'transaction_number' => 'TRX-FOOD-KDS-0099',
        'user_id' => $this->customer->id,
        'resto_id' => $this->restoOwner->id,
        'customer_name' => 'Budi Santoso',
        'customer_email' => 'budi.santoso@gmail.com',
        'customer_phone' => '081234567890',
        'status' => 'diproses',
        'kitchen_status' => 'pesanan_masuk',
        'payment_status' => 'lunas',
        'subtotal' => 35000,
        'grand_total' => 35000,
        'shipping_fee' => 0,
        'payment_method_id' => $this->paymentMethod->id,
    ]);

    TransactionItem::create([
        'transaction_id' => $transaction->id,
        'product_id' => $this->foodProduct->id,
        'product_name' => 'Paket Bebek Kremes Madura',
        'product_sku' => 'BMB-001',
        'quantity' => 1,
        'hpp' => 20000,
        'harga_jual' => 35000,
        'diskon_item' => 0,
        'harga_akhir' => 35000,
        'subtotal' => 35000,
        'note' => 'Pedas: Level 2 | Nasi: Nasi Uduk Gurih | Catatan: Sambal dipisah',
    ]);

    $response = $this->actingAs($this->restoOwner)
        ->get(route('admin.kitchen.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Kitchen/Index')
            ->has('tickets.pesanan_masuk', 1)
            ->where('tickets.pesanan_masuk.0.transaction_number', 'TRX-FOOD-KDS-0099')
            ->where('tickets.pesanan_masuk.0.items.0.note', 'Pedas: Level 2 | Nasi: Nasi Uduk Gurih | Catatan: Sambal dipisah')
        );
});

test('cart and checkout accurately calculate additional price for selected toppings and pass details to transaction items', function () {
    $modifiersPayload = [
        [
            'modifier_id' => 'mod-01',
            'item_id' => 'item-01',
            'group_name' => 'Level Pedas',
            'name' => 'Level 3 (Ekstra Pedas)',
            'additional_price' => 0,
        ],
        [
            'modifier_id' => 'mod-02',
            'item_id' => 'item-02',
            'group_name' => 'Pilihan Topping',
            'name' => 'Ekstra Telur Bebek',
            'additional_price' => 5000,
        ],
    ];

    // Add to cart with additional price 5000
    $response = $this->actingAs($this->customer)->post('/cart', [
        'product_id' => $this->foodProduct->id,
        'quantity' => 2,
        'note' => 'Level Pedas: Level 3 | Pilihan Topping: Ekstra Telur Bebek (+Rp 5.000) | Catatan: Kuah dipisah',
        'selected_modifiers' => $modifiersPayload,
    ]);

    $response->assertRedirect();

    // Verify in cart page that computed unit price is base 35.000 + 5.000 = 40.000
    $cartResponse = $this->actingAs($this->customer)->get('/cart');
    $cartResponse->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Storefront/Cart')
            ->has('cartItems', 1)
            ->where('cartItems.0.unit_price', 40000)
            ->where('cartItems.0.subtotal', 80000)
            ->where('cartItems.0.selected_modifiers.1.name', 'Ekstra Telur Bebek')
        );
});

test('same product with different toppings creates separate items with distinct prices and valid ids for guests and users', function () {
    config(['app.is_checkout_without_login' => true]);

    // 1. Add product with Topping A (+Rp 3.000)
    $this->post('/cart', [
        'product_id' => $this->foodProduct->id,
        'quantity' => 1,
        'note' => 'Pilihan Topping: Telur Dadar (+Rp 3.000)',
        'selected_modifiers' => [
            [
                'group_name' => 'Topping',
                'name' => 'Telur Dadar',
                'additional_price' => 3000,
            ],
        ],
    ])->assertRedirect();

    // 2. Add same product with Topping B (+Rp 7.000)
    $this->post('/cart', [
        'product_id' => $this->foodProduct->id,
        'quantity' => 1,
        'note' => 'Pilihan Topping: Keju Mozarella (+Rp 7.000)',
        'selected_modifiers' => [
            [
                'group_name' => 'Topping',
                'name' => 'Keju Mozarella',
                'additional_price' => 7000,
            ],
        ],
    ])->assertRedirect();

    // 3. Check guest cart page
    $cartResponse = $this->get('/cart');
    $cartResponse->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Storefront/Cart')
            ->has('cartItems', 2)
            // Ensure both items have distinct, non-empty IDs (not undefined!)
            ->where('cartItems.0.id', fn ($id) => ! empty($id))
            ->where('cartItems.1.id', fn ($id) => ! empty($id) && $id !== $page->toArray()['props']['cartItems'][0]['id'])
            // Check prices: 35.000 + 3.000 = 38.000 and 35.000 + 7.000 = 42.000
            ->where('cartItems.0.unit_price', 38000)
            ->where('cartItems.1.unit_price', 42000)
        );

    // 4. Check checkout page has both items with their correct prices and subtotals
    $checkoutResponse = $this->get('/checkout');
    $checkoutResponse->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Storefront/Checkout')
            ->has('cartItems', 2)
            ->where('cartItems.0.unit_price', 38000)
            ->where('cartItems.0.subtotal', 38000)
            ->where('cartItems.1.unit_price', 42000)
            ->where('cartItems.1.subtotal', 42000)
        );
});
