<?php

use App\Models\Category;
use App\Models\CoinHistory;
use App\Models\CustomerAddress;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\RefundRequest;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Administrator', 'guard_name' => 'web']);

    // Set default refund settings
    Setting::updateOrCreate(['key' => 'refund_points_enabled'], ['value' => '1']);
    Setting::updateOrCreate(['key' => 'coins_enabled'], ['value' => '1']);
    Setting::updateOrCreate(['key' => 'coin_conversion_rate'], ['value' => '1']);
    Setting::updateOrCreate(['key' => 'refund_min_amount_transfer'], ['value' => '10000']);
    Setting::updateOrCreate(['key' => 'refund_min_amount_points'], ['value' => '5000']);
});

function createTransactionWithStatus(string $status): array
{
    $customer = User::factory()->create();
    $customer->assignRole('Customer');

    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    $category = Category::create([
        'name' => 'Electronics',
        'slug' => 'electronics',
        'icon' => 'ti-device-laptop',
    ]);

    $product = Product::create([
        'name' => 'Laptop Pro',
        'slug' => 'laptop-pro',
        'sku' => 'LAPTOP-PRO-1',
        'category_id' => $category->id,
        'summary' => 'Laptop Summary',
        'description' => 'Laptop Description',
        'active' => true,
    ]);

    $product->productPrice()->create(['price' => 1500000, 'cost' => 1000000]);

    ProductStock::create([
        'product_id' => $product->id,
        'stock' => 10,
        'is_unlimited' => false,
    ]);

    $address = CustomerAddress::create([
        'user_id' => $customer->id,
        'receiver_name' => 'John Doe',
        'phone_number' => '08123456789',
        'label' => 'Kantor',
        'full_address' => 'Gedung A, Lt 2, Jakarta',
        'is_primary' => true,
    ]);

    $paymentMethod = PaymentMethod::create([
        'name' => 'Midtrans VA',
        'type' => 'gateway',
        'bank_name' => 'BCA VA',
        'account_number' => '123456789',
        'account_name' => 'Midtrans Store',
        'is_active' => true,
        'admin_fee' => 0,
    ]);

    $transaction = Transaction::create([
        'transaction_number' => 'TRX-'.time().'-'.rand(100, 999),
        'user_id' => $customer->id,
        'customer_address_id' => $address->id,
        'payment_method_id' => $paymentMethod->id,
        'status' => $status,
        'subtotal' => 1500000,
        'discount_amount' => 0,
        'shipping_fee' => 20000,
        'shipping_discount' => 0,
        'admin_fee' => 0,
        'grand_total' => 1520000,
        'shipping_courier' => 'sicepat',
        'shipping_service' => 'REG',
    ]);

    $txItem = TransactionItem::create([
        'transaction_id' => $transaction->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'product_sku' => $product->sku,
        'quantity' => 1,
        'hpp' => 1000000,
        'harga_jual' => 1500000,
        'diskon_item' => 0,
        'harga_akhir' => 1500000,
        'subtotal' => 1500000,
    ]);

    return compact('transaction', 'admin', 'customer', 'txItem', 'product');
}

test('customer can submit a refund request with bank transfer', function () {
    [
        'transaction' => $transaction,
        'customer' => $customer,
    ] = createTransactionWithStatus('menunggu');

    $response = $this->actingAs($customer)->post("/transactions/{$transaction->id}/refund-request", [
        'reason' => 'Saya berubah pikiran.',
        'refund_method' => 'transfer',
        'bank_name' => 'BCA',
        'account_number' => '8888777766',
        'account_name' => 'John Doe',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('refund_requests', [
        'transaction_id' => $transaction->id,
        'user_id' => $customer->id,
        'status' => 'menunggu_konfirmasi',
        'refund_method' => 'transfer',
        'bank_name' => 'BCA',
        'account_number' => '8888777766',
        'account_name' => 'John Doe',
        'refund_amount' => 1520000,
    ]);
});

test('customer can submit a refund request with loyalty points', function () {
    [
        'transaction' => $transaction,
        'customer' => $customer,
    ] = createTransactionWithStatus('diproses');

    $response = $this->actingAs($customer)->post("/transactions/{$transaction->id}/refund-request", [
        'reason' => 'Ingin diganti ke poin saja.',
        'refund_method' => 'poin',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('refund_requests', [
        'transaction_id' => $transaction->id,
        'user_id' => $customer->id,
        'status' => 'menunggu_konfirmasi',
        'refund_method' => 'poin',
        'refund_amount' => 1520000,
    ]);
});

test('customer cannot submit refund request if transaction amount is below minimal limit', function () {
    [
        'transaction' => $transaction,
        'customer' => $customer,
    ] = createTransactionWithStatus('menunggu');

    // Make minimal limit higher than grand_total
    Setting::updateOrCreate(['key' => 'refund_min_amount_transfer'], ['value' => '2000000']);

    $response = $this->actingAs($customer)->post("/transactions/{$transaction->id}/refund-request", [
        'reason' => 'Batal.',
        'refund_method' => 'transfer',
        'bank_name' => 'BCA',
        'account_number' => '12345',
        'account_name' => 'John',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');

    $this->assertDatabaseMissing('refund_requests', [
        'transaction_id' => $transaction->id,
    ]);
});

test('admin can approve a refund request with bank transfer', function () {
    [
        'transaction' => $transaction,
        'customer' => $customer,
        'admin' => $admin,
        'product' => $product,
    ] = createTransactionWithStatus('menunggu');

    $refundRequest = RefundRequest::create([
        'refund_number' => RefundRequest::generateNumber(),
        'transaction_id' => $transaction->id,
        'user_id' => $customer->id,
        'status' => 'menunggu_konfirmasi',
        'refund_method' => 'transfer',
        'reason' => 'Alasan pembatalan.',
        'bank_name' => 'BCA',
        'account_number' => '8888777766',
        'account_name' => 'John Doe',
        'refund_amount' => $transaction->grand_total,
    ]);

    $stockBefore = ProductStock::where('product_id', $product->id)->first()->stock; // 10

    $response = $this->actingAs($admin)->post("/admin/refunds/{$refundRequest->id}/approve", [
        'notes_admin' => 'Akan diproses transfer.',
    ]);

    $response->assertRedirect();

    $refundRequest->refresh();
    $transaction->refresh();

    expect($refundRequest->status)->toBe('disetujui');
    expect($refundRequest->notes_admin)->toBe('Akan diproses transfer.');
    expect($transaction->status)->toBe('batal');

    // Verify stock is restored (+1)
    $stockAfter = ProductStock::where('product_id', $product->id)->first()->stock;
    expect($stockAfter)->toBe($stockBefore + 1);
});

test('admin can approve a refund request with loyalty points and credit is instant', function () {
    [
        'transaction' => $transaction,
        'customer' => $customer,
        'admin' => $admin,
        'product' => $product,
    ] = createTransactionWithStatus('diproses');

    $refundRequest = RefundRequest::create([
        'refund_number' => RefundRequest::generateNumber(),
        'transaction_id' => $transaction->id,
        'user_id' => $customer->id,
        'status' => 'menunggu_konfirmasi',
        'refund_method' => 'poin',
        'reason' => 'Alasan pembatalan.',
        'refund_amount' => $transaction->grand_total,
    ]);

    $coinsBefore = $customer->coins_balance; // 0
    $stockBefore = ProductStock::where('product_id', $product->id)->first()->stock; // 10

    $response = $this->actingAs($admin)->post("/admin/refunds/{$refundRequest->id}/approve", [
        'notes_admin' => 'Refund poin instan.',
    ]);

    $response->assertRedirect();

    $refundRequest->refresh();
    $transaction->refresh();
    $customer->refresh();

    expect($refundRequest->status)->toBe('selesai');
    expect($transaction->status)->toBe('batal');

    // Coins should be credited (1520000 grand total / 1 conversion rate = 1520000 coins)
    expect($customer->coins_balance)->toBe($coinsBefore + 1520000);

    // Verify CoinHistory is created
    $this->assertDatabaseHas('coin_histories', [
        'user_id' => $customer->id,
        'transaction_id' => $transaction->id,
        'amount' => 1520000,
        'type' => 'refund',
    ]);

    // Verify stock is restored
    $stockAfter = ProductStock::where('product_id', $product->id)->first()->stock;
    expect($stockAfter)->toBe($stockBefore + 1);
});

test('admin can reject a refund request', function () {
    [
        'transaction' => $transaction,
        'customer' => $customer,
        'admin' => $admin,
    ] = createTransactionWithStatus('menunggu');

    $refundRequest = RefundRequest::create([
        'refund_number' => RefundRequest::generateNumber(),
        'transaction_id' => $transaction->id,
        'user_id' => $customer->id,
        'status' => 'menunggu_konfirmasi',
        'refund_method' => 'transfer',
        'reason' => 'Alasan pembatalan.',
        'bank_name' => 'BCA',
        'account_number' => '8888777766',
        'account_name' => 'John Doe',
        'refund_amount' => $transaction->grand_total,
    ]);

    $response = $this->actingAs($admin)->post("/admin/refunds/{$refundRequest->id}/reject", [
        'notes_admin' => 'Alasan tidak valid.',
    ]);

    $response->assertRedirect();

    $refundRequest->refresh();
    $transaction->refresh();

    expect($refundRequest->status)->toBe('ditolak');
    expect($refundRequest->notes_admin)->toBe('Alasan tidak valid.');
    expect($transaction->status)->toBe('menunggu'); // remains unchanged
});

test('admin can complete transfer refund', function () {
    [
        'transaction' => $transaction,
        'customer' => $customer,
        'admin' => $admin,
    ] = createTransactionWithStatus('menunggu');

    $refundRequest = RefundRequest::create([
        'refund_number' => RefundRequest::generateNumber(),
        'transaction_id' => $transaction->id,
        'user_id' => $customer->id,
        'status' => 'disetujui',
        'refund_method' => 'transfer',
        'reason' => 'Alasan pembatalan.',
        'bank_name' => 'BCA',
        'account_number' => '8888777766',
        'account_name' => 'John Doe',
        'refund_amount' => $transaction->grand_total,
    ]);

    $response = $this->actingAs($admin)->post("/admin/refunds/{$refundRequest->id}/complete");

    $response->assertRedirect();

    $refundRequest->refresh();
    expect($refundRequest->status)->toBe('selesai');
    expect($refundRequest->refunded_at)->not->toBeNull();
});
