<?php

use App\Models\Category;
use App\Models\MembershipLevel;
use App\Models\Product;
use App\Models\RestoProfile;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.membership_enabled' => false]);
    config(['app.is_foodpick' => true]);

    if (! Role::where('name', 'Super Admin')->exists()) {
        Role::create(['name' => 'Super Admin']);
    }
    if (! Role::where('name', 'Admin')->exists()) {
        Role::create(['name' => 'Admin']);
    }
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

    $this->admin = User::create([
        'name' => 'Admin Dapur',
        'email' => 'admin@dapur.com',
        'password' => bcrypt('password'),
    ]);
    $this->admin->assignRole('Super Admin');

    $this->seller1 = User::create([
        'name' => 'Chef Juna',
        'email' => 'juna@bebek.com',
        'password' => bcrypt('password'),
        'phone_number' => '081234567890',
        'is_seller' => true,
        'store_name' => 'Bebek Juna Mantap',
        'store_slug' => 'bebek-juna',
    ]);
    $this->seller1->assignRole('Seller');

    $this->restoProfile1 = RestoProfile::create([
        'user_id' => $this->seller1->id,
        'resto_name' => 'Bebek Juna Mantap',
        'slug' => 'bebek-juna',
        'culinary_category' => 'Aneka Bebek',
        'address' => 'Jl. Ijen No. 10, Malang',
        'latitude' => -7.980000,
        'longitude' => 112.630000,
        'is_open_now' => true,
        'approval_status' => 'approved',
    ]);

    $this->seller2 = User::create([
        'name' => 'Chef Arnold',
        'email' => 'arnold@gelato.com',
        'password' => bcrypt('password'),
        'phone_number' => '081299998888',
        'is_seller' => true,
        'store_name' => 'Gelato Arnold',
        'store_slug' => 'gelato-arnold',
    ]);
    $this->seller2->assignRole('Seller');

    $this->customer = User::create([
        'name' => 'Budi Santoso',
        'email' => 'budi@santoso.com',
        'password' => bcrypt('password'),
        'phone_number' => '085678901234',
    ]);
    $this->customer->assignRole('Customer');

    $this->category = Category::create([
        'name' => 'Aneka Bebek',
        'slug' => 'aneka-bebek',
    ]);

    $this->product = Product::create([
        'user_id' => $this->seller1->id,
        'category_id' => $this->category->id,
        'name' => 'Bebek Goreng Kremes',
        'slug' => 'bebek-goreng-kremes',
        'sku' => 'BEBEK-01',
        'price' => 35000,
        'stock' => 50,
        'is_active' => true,
        'is_approved' => true,
    ]);

    $this->transaction = Transaction::create([
        'user_id' => $this->customer->id,
        'resto_id' => $this->seller1->id,
        'transaction_number' => 'TRX-FOOD-20261113-0099',
        'total_amount' => 70000,
        'shipping_cost' => 0,
        'shipping_courier' => 'self_pickup',
        'fulfillment_type' => 'dine_in',
        'pickup_type' => 'dine_in',
        'table_number' => 'Meja 09',
        'scheduled_date' => '2026-11-13',
        'scheduled_time_slot' => '12:30',
        'grand_total' => 70000,
        'status' => 'diproses',
        'kitchen_status' => 'dimasak',
        'payment_status' => 'lunas',
        'customer_name' => 'Budi Santoso',
        'customer_phone' => '085678901234',
        'customer_email' => 'budi@santoso.com',
        'notes' => 'Sambal dipisah di wadah kecil ya koki',
    ]);

    TransactionItem::create([
        'transaction_id' => $this->transaction->id,
        'product_id' => $this->product->id,
        'product_name' => 'Bebek Goreng Kremes',
        'product_sku' => 'BEBEK-01',
        'quantity' => 2,
        'harga_jual' => 35000,
        'harga_akhir' => 35000,
        'subtotal' => 70000,
        'note' => json_encode([
            'Level Pedas' => 'Level 2: Pedas Nampol',
            'Pilihan Nasi' => 'Nasi Uduk Gurih',
            'Catatan' => 'Minta lalapan kubis goreng',
        ]),
    ]);
});

test('admin can access kitchen thermal print slip in 58mm and 80mm format', function () {
    $this->actingAs($this->admin);

    // 58mm format
    $response58 = $this->get("/admin/kitchen/{$this->transaction->id}/print?format=58mm&type=kitchen");
    $response58->assertOk();
    $response58->assertViewIs('print.thermal-receipt');
    $response58->assertSee('format-58mm');
    $response58->assertSee('Bebek Juna Mantap');
    $response58->assertSee('Bebek Goreng Kremes');
    $response58->assertSee('Level 2: Pedas Nampol');
    $response58->assertSee('Meja 09');

    // 80mm format
    $response80 = $this->get("/admin/kitchen/{$this->transaction->id}/print?format=80mm&type=kitchen");
    $response80->assertOk();
    $response80->assertSee('format-80mm');
});

test('admin can access cashier thermal print receipt', function () {
    $this->actingAs($this->admin);

    $response = $this->get("/admin/transactions/{$this->transaction->id}/print-thermal?format=58mm&type=cashier");
    $response->assertOk();
    $response->assertViewIs('print.thermal-receipt');
    $response->assertSee('No. Nota');
    $response->assertSee('TRX-FOOD-20261113-0099');
    $response->assertSee('Rp 70.000', false);
});

test('resto owner seller can access own order thermal receipt but other seller is denied', function () {
    config(['app.is_seller' => true]);

    // Seller 1 (Owner) can print
    $this->actingAs($this->seller1);
    $responseOwner = $this->get("/admin/kitchen/{$this->transaction->id}/print?format=58mm&type=kitchen");
    $responseOwner->assertOk();

    // Seller 2 cannot print Seller 1's order
    $this->actingAs($this->seller2);
    $responseOther = $this->get("/admin/kitchen/{$this->transaction->id}/print?format=58mm&type=kitchen");
    $responseOther->assertStatus(403);
});

test('customer can print thermal receipt for own transaction', function () {
    $this->actingAs($this->customer);

    $response = $this->get("/transactions/{$this->transaction->id}/print-thermal?format=58mm");
    $response->assertOk();
    $response->assertViewIs('print.thermal-receipt');
    $response->assertSee('Budi Santoso');
});

test('guest customer can print thermal receipt with valid contact parameter', function () {
    // Valid phone match
    $responseValidPhone = $this->get("/transactions/{$this->transaction->id}/print-thermal?contact=085678901234");
    $responseValidPhone->assertOk();
    $responseValidPhone->assertSee('TRX-FOOD-20261113-0099');

    // Valid email match
    $responseValidEmail = $this->get("/transactions/{$this->transaction->id}/print-thermal?contact=budi@santoso.com");
    $responseValidEmail->assertOk();

    // Invalid contact is denied
    $responseInvalid = $this->get("/transactions/{$this->transaction->id}/print-thermal?contact=089999999999");
    $responseInvalid->assertStatus(403);

    // No contact is denied
    $responseNoAuth = $this->get("/transactions/{$this->transaction->id}/print-thermal");
    $responseNoAuth->assertStatus(403);
});
