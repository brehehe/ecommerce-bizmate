<?php

use App\Models\Category;
use App\Models\CustomerAddress;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Setup test data for dashboard testing.
 */
function setupDashboardTestData(): User
{
    Role::firstOrCreate(['name' => 'Customer', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $customer = User::factory()->create(['name' => 'Ahmad Subagyo', 'email' => 'ahmad@email.com']);
    $customer->assignRole('Customer');

    $category = Category::create([
        'name' => 'Dekorasi',
        'slug' => 'dekorasi',
        'icon' => 'ti-home',
    ]);

    $product = Product::create([
        'name' => 'Lampu Hias',
        'slug' => 'lampu-hias',
        'sku' => 'LMP-H1',
        'category_id' => $category->id,
        'summary' => 'Lampu hias premium',
        'description' => 'Lampu hias premium LED',
        'active' => true,
    ]);

    $product->productPrice()->create(['price' => 150000, 'cost' => 90000]);
    $product->productStock()->create(['stock' => 10, 'is_unlimited' => false]);

    $address = CustomerAddress::create([
        'user_id' => $customer->id,
        'receiver_name' => 'Ahmad Subagyo',
        'phone_number' => '081234567890',
        'label' => 'Rumah',
        'full_address' => 'Jl. Mawar No. 10',
        'is_primary' => true,
    ]);

    $paymentMethod = PaymentMethod::create([
        'name' => 'Transfer Bank BCA',
        'type' => 'manual',
        'bank_name' => 'BCA',
        'account_number' => '1234567890',
        'account_name' => 'PT Toko',
        'is_active' => true,
        'admin_fee' => 2000,
    ]);

    $transaction = Transaction::create([
        'transaction_number' => 'TRX-20260529-00001',
        'user_id' => $customer->id,
        'customer_address_id' => $address->id,
        'payment_method_id' => $paymentMethod->id,
        'status' => 'selesai',
        'subtotal' => 300000,
        'discount_amount' => 10000,
        'shipping_fee' => 20000,
        'shipping_discount' => 5000,
        'admin_fee' => 2000,
        'grand_total' => 307000,
    ]);

    TransactionItem::create([
        'transaction_id' => $transaction->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'product_sku' => $product->sku,
        'quantity' => 2,
        'hpp' => 90000,
        'harga_jual' => 150000,
        'diskon_item' => 0,
        'harga_akhir' => 150000,
        'subtotal' => 300000,
    ]);

    return $admin;
}

test('admin can view dashboard statistics', function () {
    $admin = setupDashboardTestData();

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Dashboard')
        ->has('stats')
        ->where('stats.revenueFormatted', 'Rp 307.000')
        ->where('stats.ordersCount', 1)
        ->where('stats.activeProductsCount', 1)
        ->where('stats.customersCount', 1)
        ->has('orderStats')
        ->where('orderStats.newCount', 0)
        ->where('orderStats.readyCount', 0)
        ->where('orderStats.shippingCount', 0)
        ->has('recentOrders')
        ->where('recentOrders.0.customer', 'Ahmad Subagyo')
        ->where('recentOrders.0.status', 'Paid')
        ->has('topProducts')
        ->where('topProducts.0.name', 'Lampu Hias')
        ->where('topProducts.0.sales', 2)
        ->has('chartData')
    );
});

test('admin can filter dashboard statistics', function () {
    $admin = setupDashboardTestData();

    // today filter (hari_ini)
    $response = $this->actingAs($admin)->get(route('admin.dashboard', ['filter' => 'hari_ini']));
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Dashboard')
        ->where('currentFilter', 'hari_ini')
    );

    // month filter (1_bulan)
    $response = $this->actingAs($admin)->get(route('admin.dashboard', ['filter' => '1_bulan']));
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Dashboard')
        ->where('currentFilter', '1_bulan')
    );

    // last year filter (tahun_lalu)
    $response = $this->actingAs($admin)->get(route('admin.dashboard', ['filter' => 'tahun_lalu']));
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Dashboard')
        ->where('currentFilter', 'tahun_lalu')
    );
});

test('admin can search and paginate stock information on dashboard', function () {
    $admin = setupDashboardTestData();

    // Create another product to test search and pagination
    $category = Category::first();
    $product2 = Product::create([
        'name' => 'Meja Lipat',
        'slug' => 'meja-lipat',
        'sku' => 'MJ-L1',
        'category_id' => $category->id,
        'summary' => 'Meja lipat portable',
        'description' => 'Meja lipat portable',
        'active' => true,
    ]);
    $product2->productPrice()->create(['price' => 200000, 'cost' => 120000]);
    $product2->productStock()->create(['stock' => 5, 'is_unlimited' => false]);

    // Test default stock listing (sorted by stock ASC: Meja Lipat first with stock 5, then Lampu Hias with stock 10)
    $response = $this->actingAs($admin)->get(route('admin.dashboard'));
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Dashboard')
        ->has('productStockInfo')
        ->has('productStockInfo.data', 2)
        ->where('productStockInfo.data.0.name', 'Meja Lipat')
        ->where('productStockInfo.data.1.name', 'Lampu Hias')
    );

    // Test search filter (search for "Lampu")
    $response = $this->actingAs($admin)->get(route('admin.dashboard', ['search' => 'Lampu']));
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Dashboard')
        ->where('search', 'Lampu')
        ->has('productStockInfo.data', 1)
        ->where('productStockInfo.data.0.name', 'Lampu Hias')
    );

    // Test search filter (search for "MJ-L1" SKU)
    $response = $this->actingAs($admin)->get(route('admin.dashboard', ['search' => 'MJ-L1']));
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Dashboard')
        ->where('search', 'MJ-L1')
        ->has('productStockInfo.data', 1)
        ->where('productStockInfo.data.0.name', 'Meja Lipat')
    );
});
