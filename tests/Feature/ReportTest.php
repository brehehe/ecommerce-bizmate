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
 * Setup data for testing reports.
 */
function setupReportTestData(): User
{
    Role::firstOrCreate(['name' => 'Customer', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $customer = User::factory()->create();
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
        'receiver_name' => 'Budi',
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

test('admin can view sales report', function () {
    $admin = setupReportTestData();

    $response = $this->actingAs($admin)->get(route('admin.reports.sales'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Reports/Sales')
        ->has('metrics')
        ->where('metrics.net_sales', 307000)
        ->where('metrics.order_count', 1)
        ->where('metrics.items_sold', 2)
        ->has('salesTrend')
        ->has('salesTrendPaginated')
        ->has('paymentDistribution')
        ->has('statusDistribution')
    );
});

test('admin can view product sales report', function () {
    $admin = setupReportTestData();

    $response = $this->actingAs($admin)->get(route('admin.reports.products'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Reports/Products')
        ->has('productSales')
        ->where('productSales.data.0.product_sku', 'LMP-H1')
        ->where('productSales.data.0.qty_sold', 2)
        ->has('metrics')
        ->where('metrics.total_qty_sold', 2)
        ->where('metrics.total_revenue', 300000)
    );
});

test('admin can view profit loss report', function () {
    $admin = setupReportTestData();

    $response = $this->actingAs($admin)->get(route('admin.reports.profit-loss'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Reports/ProfitLoss')
        ->has('metrics')
        ->where('metrics.product_revenue', 300000)
        ->where('metrics.admin_fee_revenue', 2000)
        ->where('metrics.total_cogs', 180000)
        ->where('metrics.gross_profit', 122000) // 302000 - 180000
        ->where('metrics.voucher_discounts', 10000)
        ->where('metrics.shipping_discounts', 5000)
        ->where('metrics.net_profit', 107000) // 122000 - 15000
        ->has('trendData')
    );
});

test('admin can view customer activity report', function () {
    $admin = setupReportTestData();

    $response = $this->actingAs($admin)->get(route('admin.reports.customers'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Reports/Customers')
        ->has('customers')
        ->where('customers.data.0.orders_count', 1)
        ->where('customers.data.0.total_spent', 307000)
        ->where('customers.data.0.average_order_value', 307000)
        ->where('customers.data.0.total_discounts', 10000)
        ->where('customers.data.0.total_coins_redeemed', 0)
        ->has('metrics')
        ->where('metrics.new_customers', 1)
        ->where('metrics.active_customers', 1)
    );
});

test('admin can view customer transactions history json', function () {
    $admin = setupReportTestData();
    $customer = User::whereHas('roles', function ($q) {
        $q->where('name', 'Customer');
    })->first();

    $response = $this->actingAs($admin)
        ->get(route('admin.reports.customers.transactions', $customer));

    $response->assertOk();
    $response->assertJsonPath('data.0.transaction_number', 'TRX-20260529-00001');
    $response->assertJsonPath('data.0.grand_total', '307000.00');
    $response->assertJsonPath('data.0.discount_amount', '10000.00');
    $response->assertJsonPath('data.0.items_count', 1);
});

test('admin can view stocks and valuation report', function () {
    $admin = setupReportTestData();

    $response = $this->actingAs($admin)->get(route('admin.reports.stocks'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Reports/Stocks')
        ->has('stocks')
        ->where('stocks.data.0.sku', 'LMP-H1')
        ->where('stocks.data.0.stock', 10)
        ->has('metrics')
        ->where('metrics.total_sku', 1)
        ->where('metrics.total_stock', 10)
        ->where('metrics.total_asset_value', 900000) // 10 * 90000 cost
        ->where('metrics.total_retail_value', 1500000) // 10 * 150000 price
        ->where('metrics.potential_profit', 600000)
    );
});

test('admin can view couriers report', function () {
    $admin = setupReportTestData();

    $response = $this->actingAs($admin)->get(route('admin.reports.couriers'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Reports/Couriers')
        ->has('metrics')
        ->has('shippingSummary')
        ->has('rajaongkirBreakdown')
        ->has('courierPerformance')
        ->has('recentDeliveries')
    );
});
