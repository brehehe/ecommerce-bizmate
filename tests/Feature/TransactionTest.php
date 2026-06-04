<?php

use App\Models\Category;
use App\Models\CustomerAddress;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\TransactionPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
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

test('admin can update transaction tracking number and status becomes dikirim', function () {
    ['transaction' => $transaction, 'admin' => $admin] = createTestTransaction();

    $response = $this->actingAs($admin)->post(
        route('admin.transactions.update-tracking', $transaction),
        [
            'tracking_number' => 'RESI123456789',
            'courier_name' => 'JNE Express',
        ]
    );

    $response->assertRedirect();
    $transaction->refresh();

    expect($transaction->status)->toBe('dikirim');
    expect($transaction->tracking_number)->toBe('RESI123456789');
    expect($transaction->courier_name)->toBe('JNE Express');

    // Assure status history is recorded
    $this->assertDatabaseHas('transaction_status_histories', [
        'transaction_id' => $transaction->id,
        'status' => 'dikirim',
    ]);
});

test('admin cannot update transaction tracking number if status is selesai or batal', function () {
    ['transaction' => $transaction, 'admin' => $admin] = createTestTransaction();

    // Mark as selesai first
    $transaction->update(['status' => 'selesai']);

    $response = $this->actingAs($admin)->post(
        route('admin.transactions.update-tracking', $transaction),
        [
            'tracking_number' => 'RESI123456789',
            'courier_name' => 'JNE Express',
        ]
    );

    $response->assertRedirect();
    $response->assertSessionHas('error', 'Tidak dapat memperbarui resi untuk transaksi yang sudah selesai atau batal.');

    $transaction->refresh();
    expect($transaction->status)->toBe('selesai');
    expect($transaction->tracking_number)->toBeNull();
});

test('admin can bulk update transaction status', function () {
    ['transaction' => $trx1, 'admin' => $admin] = createTestTransaction();

    $trx2 = Transaction::create([
        'transaction_number' => 'TRX-TEST-00002',
        'user_id' => $trx1->user_id,
        'customer_address_id' => $trx1->customer_address_id,
        'payment_method_id' => $trx1->payment_method_id,
        'status' => 'belum_bayar',
        'subtotal' => 100000,
        'discount_amount' => 0,
        'shipping_fee' => 15000,
        'shipping_discount' => 0,
        'admin_fee' => 0,
        'grand_total' => 115000,
        'shipping_courier' => 'jne',
        'shipping_service' => 'REG',
    ]);

    $response = $this->actingAs($admin)->post(
        route('admin.transactions.bulk-status'),
        [
            'ids' => [$trx1->id, $trx2->id],
            'status' => 'diproses',
        ]
    );

    $response->assertRedirect();
    $response->assertSessionHas('success', '2 transaksi berhasil diperbarui.');

    $trx1->refresh();
    $trx2->refresh();

    expect($trx1->status)->toBe('diproses');
    expect($trx2->status)->toBe('diproses');
});

test('admin can bulk update transaction tracking numbers and status becomes dikirim', function () {
    ['transaction' => $trx1, 'admin' => $admin] = createTestTransaction();

    $trx2 = Transaction::create([
        'transaction_number' => 'TRX-TEST-00002',
        'user_id' => $trx1->user_id,
        'customer_address_id' => $trx1->customer_address_id,
        'payment_method_id' => $trx1->payment_method_id,
        'status' => 'belum_bayar',
        'subtotal' => 100000,
        'discount_amount' => 0,
        'shipping_fee' => 15000,
        'shipping_discount' => 0,
        'admin_fee' => 0,
        'grand_total' => 115000,
        'shipping_courier' => 'jne',
        'shipping_service' => 'REG',
    ]);

    $response = $this->actingAs($admin)->post(
        route('admin.transactions.bulk-tracking'),
        [
            'tracking_data' => [
                [
                    'id' => $trx1->id,
                    'tracking_number' => 'RESI111',
                    'courier_name' => 'JNE',
                ],
                [
                    'id' => $trx2->id,
                    'tracking_number' => 'RESI222',
                    'courier_name' => 'J&T',
                ],
            ],
        ]
    );

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Nomor resi untuk 2 transaksi berhasil disimpan.');

    $trx1->refresh();
    $trx2->refresh();

    expect($trx1->status)->toBe('dikirim');
    expect($trx1->tracking_number)->toBe('RESI111');
    expect($trx1->courier_name)->toBe('JNE');

    expect($trx2->status)->toBe('dikirim');
    expect($trx2->tracking_number)->toBe('RESI222');
    expect($trx2->courier_name)->toBe('J&T');
});

test('admin can print transaction invoice', function () {
    ['transaction' => $transaction, 'admin' => $admin] = createTestTransaction();

    $response = $this->actingAs($admin)->get(route('admin.transactions.print-invoice', $transaction));
    $response->assertOk();
    $response->assertViewIs('print.invoice');
    $response->assertSee($transaction->transaction_number);
});

test('admin can print shipping label', function () {
    ['transaction' => $transaction, 'admin' => $admin] = createTestTransaction();

    $response = $this->actingAs($admin)->get(route('admin.transactions.print-shipping-label', $transaction));
    $response->assertOk();
    $response->assertViewIs('print.shipping-label');
    $response->assertSee($transaction->transaction_number);
});

test('customer can print their transaction invoice', function () {
    ['transaction' => $transaction, 'customer' => $customer] = createTestTransaction();

    $response = $this->actingAs($customer)->get(route('transactions.print-invoice-customer', $transaction));
    $response->assertOk();
    $response->assertViewIs('print.invoice');
    $response->assertSee($transaction->transaction_number);
});

test('customer cannot print others transaction invoice', function () {
    ['transaction' => $transaction] = createTestTransaction();
    $otherCustomer = User::factory()->create();

    $response = $this->actingAs($otherCustomer)->get(route('transactions.print-invoice-customer', $transaction));
    $response->assertForbidden();
});

test('transaction sets payment_expires_at when created and status is belum_bayar', function () {
    Carbon::setTestNow(now());
    Setting::updateOrCreate(['key' => 'payment_expiry_hours'], ['value' => '12']);
    ['transaction' => $transaction] = createTestTransaction();

    expect($transaction->payment_expires_at)->not->toBeNull();
    $diffInHours = (int) round(now()->diffInMinutes($transaction->payment_expires_at) / 60);
    expect($diffInHours)->toBe(12);

    Carbon::setTestNow();
});

test('transaction sets auto_complete_at when status changes to dikirim', function () {
    Carbon::setTestNow(now());
    Setting::updateOrCreate(['key' => 'auto_complete_days'], ['value' => '5']);
    ['transaction' => $transaction] = createTestTransaction();

    $transaction->update(['status' => 'dikirim']);
    expect($transaction->auto_complete_at)->not->toBeNull();
    $diffInDays = (int) round(now()->diffInHours($transaction->auto_complete_at) / 24);
    expect($diffInDays)->toBe(5);

    Carbon::setTestNow();
});

test('processAutoStatusUpdates cancels expired unpaid transactions', function () {
    ['transaction' => $transaction] = createTestTransaction();
    $transaction->updateQuietly(['payment_expires_at' => now()->subMinutes(1)]);

    Transaction::processAutoStatusUpdates();

    $transaction->refresh();
    expect($transaction->status)->toBe('batal');
    expect($transaction->cancel_reason)->toContain('Pembatalan otomatis oleh sistem');
});

test('processAutoStatusUpdates completes shipped transactions when auto_complete_at is passed', function () {
    ['transaction' => $transaction] = createTestTransaction();
    $transaction->updateQuietly([
        'status' => 'dikirim',
        'auto_complete_at' => now()->subMinutes(1),
    ]);

    Transaction::processAutoStatusUpdates();

    $transaction->refresh();
    expect($transaction->status)->toBe('selesai');
});

test('customer can extend the auto-complete deadline of a shipped transaction', function () {
    Carbon::setTestNow(now());
    Setting::updateOrCreate(['key' => 'extend_auto_complete_days'], ['value' => '4']);
    ['transaction' => $transaction, 'customer' => $customer] = createTestTransaction();

    $transaction->updateQuietly([
        'status' => 'dikirim',
        'auto_complete_at' => now()->addDays(5),
        'is_extended' => false,
    ]);

    $response = $this->actingAs($customer)->post(
        route('transactions.extend-auto-complete', $transaction)
    );

    $response->assertRedirect();
    $transaction->refresh();

    expect($transaction->is_extended)->toBeTrue();
    $diffInDays = (int) round(now()->diffInHours($transaction->auto_complete_at) / 24);
    expect($diffInDays)->toBe(9);

    Carbon::setTestNow();
});

test('customer cannot extend the auto-complete deadline twice', function () {
    ['transaction' => $transaction, 'customer' => $customer] = createTestTransaction();

    $transaction->updateQuietly([
        'status' => 'dikirim',
        'auto_complete_at' => now()->addDays(5),
        'is_extended' => true,
    ]);

    $response = $this->actingAs($customer)->post(
        route('transactions.extend-auto-complete', $transaction)
    );

    $response->assertRedirect();
    $response->assertSessionHas('error', 'Jangka waktu konfirmasi pesanan ini sudah pernah diperpanjang sebelumnya.');
});

test('admin can find a transaction by transaction number, booking code, or tracking number', function () {
    ['transaction' => $transaction, 'admin' => $admin] = createTestTransaction();

    // Set a booking code and tracking number
    $transaction->update([
        'booking_code' => 'BK-123456',
        'tracking_number' => 'TRK-987654',
    ]);

    // Find by transaction number
    $response = $this->actingAs($admin)->get('/admin/transactions/find-by-number/'.$transaction->transaction_number);
    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'id' => $transaction->id,
        'redirect_url' => "/admin/transactions/{$transaction->id}",
    ]);

    // Find by booking code
    $response = $this->actingAs($admin)->get('/admin/transactions/find-by-number/BK-123456');
    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'id' => $transaction->id,
    ]);

    // Find by tracking number
    $response = $this->actingAs($admin)->get('/admin/transactions/find-by-number/TRK-987654');
    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'id' => $transaction->id,
    ]);

    // Find non-existent code returns 404
    $response = $this->actingAs($admin)->get('/admin/transactions/find-by-number/INVALID-CODE');
    $response->assertStatus(404);
    $response->assertJson([
        'success' => false,
        'message' => 'Transaksi tidak ditemukan.',
    ]);
});

test('store courier transaction automatically logs booking code, tracking resi, and status history updates', function () {
    ['transaction' => $transaction, 'admin' => $admin] = createTestTransaction();

    // Set courier to store_courier
    $transaction->update(['shipping_courier' => 'store_courier']);

    // 1. Generate booking code
    $response = $this->actingAs($admin)->post(
        route('admin.transactions.update-tracking', $transaction),
        ['booking_code' => 'ST-BK-123456']
    );
    $response->assertRedirect();
    $this->assertDatabaseHas('transaction_status_histories', [
        'transaction_id' => $transaction->id,
        'description' => 'Kode Booking Sudah dibuat',
    ]);

    // 2. Update status to out_for_pickup
    $response = $this->actingAs($admin)->post(
        route('admin.transactions.update-status', $transaction),
        ['status' => 'out_for_pickup']
    );
    $response->assertRedirect();
    $this->assertDatabaseHas('transaction_status_histories', [
        'transaction_id' => $transaction->id,
        'status' => 'out_for_pickup',
        'description' => 'Sudah dipick',
    ]);

    // 3. Update tracking number (resi)
    $response = $this->actingAs($admin)->post(
        route('admin.transactions.update-tracking', $transaction),
        ['tracking_number' => 'ST-TRK-777888']
    );
    $response->assertRedirect();
    $this->assertDatabaseHas('transaction_status_histories', [
        'transaction_id' => $transaction->id,
        'description' => 'Resi Telah dibuat',
    ]);

    // Status should remain 'out_for_pickup' and not automatically change to 'dikirim'
    expect($transaction->fresh()->status)->toBe('out_for_pickup');
});
