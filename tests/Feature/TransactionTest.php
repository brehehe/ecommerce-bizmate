<?php

use App\Models\Category;
use App\Models\CustomerAddress;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\TransactionPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * Helper to create a completed transaction.
 *
 * @return array{transaction: Transaction, admin: User, customer: User}
 */
function createTestTransaction(): array
{
    $customer = User::factory()->create();
    $admin = User::factory()->create();

    $category = Category::create([
        'name' => 'Test Cat',
        'slug' => 'test-cat',
        'icon' => 'ti-box',
    ]);

    $product = Product::create([
        'name' => 'Test Product',
        'slug' => 'test-product',
        'sku' => 'TEST-P1',
        'category_id' => $category->id,
        'summary' => 'Summary',
        'description' => 'Description',
        'active' => true,
    ]);

    $product->productPrice()->create(['price' => 100000, 'cost' => 60000]);

    ProductStock::create([
        'product_id' => $product->id,
        'stock' => 5,
        'is_unlimited' => false,
    ]);

    $address = CustomerAddress::create([
        'user_id' => $customer->id,
        'receiver_name' => 'Customer Test',
        'phone_number' => '08000000000',
        'label' => 'Rumah',
        'full_address' => 'Jl. Test 1',
        'is_primary' => true,
    ]);

    $paymentMethod = PaymentMethod::create([
        'name' => 'BCA',
        'type' => 'manual',
        'bank_name' => 'BCA',
        'account_number' => '9876543210',
        'account_name' => 'Toko Test',
        'is_active' => true,
        'admin_fee' => 0,
    ]);

    $transaction = Transaction::create([
        'transaction_number' => 'TRX-TEST-00001',
        'user_id' => $customer->id,
        'customer_address_id' => $address->id,
        'payment_method_id' => $paymentMethod->id,
        'status' => 'belum_bayar',
        'subtotal' => 200000,
        'discount_amount' => 0,
        'shipping_fee' => 15000,
        'shipping_discount' => 0,
        'admin_fee' => 0,
        'grand_total' => 215000,
        'shipping_courier' => 'jne',
        'shipping_service' => 'REG',
    ]);

    TransactionItem::create([
        'transaction_id' => $transaction->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'product_sku' => $product->sku,
        'quantity' => 2,
        'hpp' => 60000,
        'harga_jual' => 100000,
        'diskon_item' => 0,
        'harga_akhir' => 100000,
        'subtotal' => 200000,
    ]);

    TransactionPayment::create([
        'transaction_id' => $transaction->id,
        'payment_method_id' => $paymentMethod->id,
        'amount' => 215000,
        'status' => 'pending',
    ]);

    return compact('transaction', 'admin', 'customer');
}

test('customer can view their transaction history', function () {
    ['transaction' => $transaction, 'customer' => $customer] = createTestTransaction();

    $response = $this->actingAs($customer)->get(route('transactions.index'));
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Storefront/TransactionHistory')
        ->has('transactions.data', 1)
        ->where('transactions.data.0.transaction_number', 'TRX-TEST-00001')
    );
});

test('customer can view their transaction detail', function () {
    ['transaction' => $transaction, 'customer' => $customer] = createTestTransaction();

    $response = $this->actingAs($customer)->get(route('transactions.show', $transaction));
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Storefront/TransactionDetail')
        ->where('transaction.transaction_number', 'TRX-TEST-00001')
        ->where('transaction.status', 'belum_bayar')
        ->has('transaction.items', 1)
    );
});

test('customer cannot view other users transaction', function () {
    ['transaction' => $transaction] = createTestTransaction();
    $otherUser = User::factory()->create();

    $response = $this->actingAs($otherUser)->get(route('transactions.show', $transaction));
    $response->assertForbidden();
});

test('customer can upload payment proof', function () {
    Storage::fake('public');

    ['transaction' => $transaction, 'customer' => $customer] = createTestTransaction();

    $file = UploadedFile::fake()->image('proof.jpg');

    $response = $this->actingAs($customer)->post(
        route('transactions.upload-proof', $transaction),
        ['proof_image' => $file]
    );

    $response->assertRedirect();

    // Transaction status updated to 'menunggu'
    $transaction->refresh();
    expect($transaction->status)->toBe('menunggu');

    // Payment proof saved
    $this->assertDatabaseHas('transaction_payments', [
        'transaction_id' => $transaction->id,
    ]);

    Storage::disk('public')->assertExists(
        $transaction->payment->proof_image
    );
});

test('admin can list transactions', function () {
    ['transaction' => $transaction, 'admin' => $admin] = createTestTransaction();

    $response = $this->actingAs($admin)->get(route('admin.transactions.index'));
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Transactions/Index')
        ->has('transactions.data', 1)
    );
});

test('admin can view transaction detail', function () {
    ['transaction' => $transaction, 'admin' => $admin] = createTestTransaction();

    $response = $this->actingAs($admin)->get(route('admin.transactions.show', $transaction));
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Transactions/Show')
        ->where('transaction.id', $transaction->id)
    );
});

test('admin can update transaction status', function () {
    ['transaction' => $transaction, 'admin' => $admin] = createTestTransaction();

    $response = $this->actingAs($admin)->post(
        route('admin.transactions.update-status', $transaction),
        ['status' => 'diproses']
    );

    $response->assertRedirect();
    $transaction->refresh();
    expect($transaction->status)->toBe('diproses');
});

test('admin can confirm payment and status becomes diproses', function () {
    ['transaction' => $transaction, 'admin' => $admin] = createTestTransaction();

    $response = $this->actingAs($admin)->post(
        route('admin.transactions.confirm-payment', $transaction)
    );

    $response->assertRedirect();
    $transaction->refresh();
    expect($transaction->status)->toBe('diproses');

    $this->assertDatabaseHas('transaction_payments', [
        'transaction_id' => $transaction->id,
        'status' => 'confirmed',
        'confirmed_by' => $admin->id,
    ]);
});

test('admin can reject payment and status returns to belum_bayar', function () {
    ['transaction' => $transaction, 'admin' => $admin] = createTestTransaction();

    $response = $this->actingAs($admin)->post(
        route('admin.transactions.reject-payment', $transaction),
        ['notes' => 'Bukti bayar tidak jelas.']
    );

    $response->assertRedirect();
    $transaction->refresh();
    expect($transaction->status)->toBe('belum_bayar');

    $this->assertDatabaseHas('transaction_payments', [
        'transaction_id' => $transaction->id,
        'status' => 'rejected',
        'notes' => 'Bukti bayar tidak jelas.',
    ]);
});

test('admin cancelling transaction restores stock', function () {
    ['transaction' => $transaction, 'admin' => $admin] = createTestTransaction();

    // Get the product stock
    $item = $transaction->items->first();
    $stock = ProductStock::where('product_id', $item->product_id)
        ->whereNull('product_variant_id')
        ->first();

    $stockBefore = $stock->stock;

    $response = $this->actingAs($admin)->post(
        route('admin.transactions.update-status', $transaction),
        ['status' => 'batal', 'cancel_reason' => 'Customer membatalkan.']
    );

    $response->assertRedirect();

    $stock->refresh();
    expect($stock->stock)->toBe($stockBefore + $item->quantity);

    $this->assertDatabaseHas('stock_movements', [
        'product_id' => $item->product_id,
        'transaction_id' => $transaction->id,
        'type' => 'retur',
    ]);
});

test('admin can view stock movements page', function () {
    ['admin' => $admin] = createTestTransaction();

    $response = $this->actingAs($admin)->get(route('admin.stock-movements.index'));
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/StockMovements/Index')
    );
});

test('admin can filter transactions by status', function () {
    ['transaction' => $transaction, 'admin' => $admin] = createTestTransaction();

    // Filter matching - belum_bayar
    $response = $this->actingAs($admin)->get(route('admin.transactions.index', ['status' => 'belum_bayar']));
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->has('transactions.data', 1)
    );

    // Filter not matching - diproses
    $response = $this->actingAs($admin)->get(route('admin.transactions.index', ['status' => 'diproses']));
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->has('transactions.data', 0)
    );
});
