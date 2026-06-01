<?php

use App\Models\Category;
use App\Models\CustomerAddress;
use App\Models\CustomerBankAccount;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ReturnRequest;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Administrator', 'guard_name' => 'web']);
});

function createSelesaiTransaction(): array
{
    $customer = User::factory()->create();
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

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
        'status' => 'selesai',
        'subtotal' => 200000,
        'discount_amount' => 0,
        'shipping_fee' => 15000,
        'shipping_discount' => 0,
        'admin_fee' => 0,
        'grand_total' => 215000,
        'shipping_courier' => 'jne',
        'shipping_service' => 'REG',
    ]);

    $txItem = TransactionItem::create([
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

    return compact('transaction', 'admin', 'customer', 'txItem', 'product');
}

test('customer can view bank accounts page', function () {
    $customer = User::factory()->create();
    $customer->assignRole('Customer');

    $response = $this->actingAs($customer)->get('/profile/bank-accounts');
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Storefront/BankAccounts')
        ->has('bankAccounts')
    );
});

test('customer can add a bank account', function () {
    $customer = User::factory()->create();
    $customer->assignRole('Customer');

    $response = $this->actingAs($customer)->post('/profile/bank-accounts', [
        'bank_name' => 'BCA',
        'account_number' => '1234567890',
        'account_name' => 'Test Account Name',
        'is_primary' => true,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('customer_bank_accounts', [
        'user_id' => $customer->id,
        'bank_name' => 'BCA',
        'account_number' => '1234567890',
        'account_name' => 'Test Account Name',
        'is_primary' => true,
    ]);
});

test('customer can make a bank account primary', function () {
    $customer = User::factory()->create();
    $customer->assignRole('Customer');

    $acc1 = CustomerBankAccount::create([
        'user_id' => $customer->id,
        'bank_name' => 'BCA',
        'account_number' => '1111111111',
        'account_name' => 'Name 1',
        'is_primary' => true,
    ]);

    $acc2 = CustomerBankAccount::create([
        'user_id' => $customer->id,
        'bank_name' => 'BRI',
        'account_number' => '2222222222',
        'account_name' => 'Name 2',
        'is_primary' => false,
    ]);

    $response = $this->actingAs($customer)->post("/profile/bank-accounts/{$acc2->id}/make-primary");

    $response->assertRedirect();

    $acc1->refresh();
    $acc2->refresh();

    expect($acc1->is_primary)->toBeFalse();
    expect($acc2->is_primary)->toBeTrue();
});

test('customer can delete a bank account', function () {
    $customer = User::factory()->create();
    $customer->assignRole('Customer');

    $acc = CustomerBankAccount::create([
        'user_id' => $customer->id,
        'bank_name' => 'BCA',
        'account_number' => '1111111111',
        'account_name' => 'Name 1',
        'is_primary' => true,
    ]);

    $response = $this->actingAs($customer)->delete("/profile/bank-accounts/{$acc->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('customer_bank_accounts', [
        'id' => $acc->id,
    ]);
});

test('customer can submit a return request', function () {
    Storage::fake('public');

    [
        'transaction' => $transaction,
        'customer' => $customer,
        'txItem' => $txItem
    ] = createSelesaiTransaction();

    $mediaFile = UploadedFile::fake()->image('evidence.jpg');

    $response = $this->actingAs($customer)->post("/transactions/{$transaction->id}/return", [
        'type' => 'refund',
        'reason' => 'Produk robek pada jahitan lengan.',
        'items' => [
            [
                'transaction_item_id' => $txItem->id,
                'quantity_returned' => 1,
            ],
        ],
        'media' => [$mediaFile],
    ]);

    $response->assertRedirect();
    $transaction->refresh();

    expect($transaction->return_status)->toBe('menunggu_review');

    $this->assertDatabaseHas('returns', [
        'transaction_id' => $transaction->id,
        'user_id' => $customer->id,
        'status' => 'menunggu_review',
        'type' => 'refund',
        'refund_amount' => 100000.00,
    ]);

    $returnReq = ReturnRequest::where('transaction_id', $transaction->id)->first();
    expect($returnReq->items)->toHaveCount(1);
    expect($returnReq->media)->toHaveCount(1);
});

test('customer cannot request return for non-completed transaction', function () {
    [
        'transaction' => $transaction,
        'customer' => $customer,
        'txItem' => $txItem
    ] = createSelesaiTransaction();

    $transaction->update(['status' => 'diproses']);

    $response = $this->actingAs($customer)->post("/transactions/{$transaction->id}/return", [
        'type' => 'refund',
        'reason' => 'Batal.',
        'items' => [
            [
                'transaction_item_id' => $txItem->id,
                'quantity_returned' => 1,
            ],
        ],
        'media' => [UploadedFile::fake()->image('evidence.jpg')],
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error', 'Retur hanya dapat diajukan untuk pesanan yang sudah selesai.');
});

test('admin can approve a return request', function () {
    [
        'transaction' => $transaction,
        'customer' => $customer,
        'admin' => $admin,
    ] = createSelesaiTransaction();

    $returnReq = ReturnRequest::create([
        'return_number' => ReturnRequest::generateNumber(),
        'transaction_id' => $transaction->id,
        'user_id' => $customer->id,
        'status' => 'menunggu_review',
        'type' => 'refund',
        'reason' => 'Cacat',
        'refund_amount' => 100000,
    ]);

    $response = $this->actingAs($admin)->post("/admin/returns/{$returnReq->id}/approve", [
        'notes_admin' => 'Silakan kirimkan barang ke toko.',
    ]);

    $response->assertRedirect();
    $returnReq->refresh();
    $transaction->refresh();

    expect($returnReq->status)->toBe('disetujui');
    expect($returnReq->notes_admin)->toBe('Silakan kirimkan barang ke toko.');
    expect($transaction->return_status)->toBe('disetujui');
});

test('admin can reject a return request', function () {
    [
        'transaction' => $transaction,
        'customer' => $customer,
        'admin' => $admin,
    ] = createSelesaiTransaction();

    $returnReq = ReturnRequest::create([
        'return_number' => ReturnRequest::generateNumber(),
        'transaction_id' => $transaction->id,
        'user_id' => $customer->id,
        'status' => 'menunggu_review',
        'type' => 'refund',
        'reason' => 'Cacat',
        'refund_amount' => 100000,
    ]);

    $response = $this->actingAs($admin)->post("/admin/returns/{$returnReq->id}/reject", [
        'notes_admin' => 'Bukti foto kurang jelas.',
    ]);

    $response->assertRedirect();
    $returnReq->refresh();
    $transaction->refresh();

    expect($returnReq->status)->toBe('ditolak');
    expect($returnReq->notes_admin)->toBe('Bukti foto kurang jelas.');
    expect($transaction->return_status)->toBe('ditolak');
});

test('customer can input return resi tracking number', function () {
    [
        'transaction' => $transaction,
        'customer' => $customer,
    ] = createSelesaiTransaction();

    $returnReq = ReturnRequest::create([
        'return_number' => ReturnRequest::generateNumber(),
        'transaction_id' => $transaction->id,
        'user_id' => $customer->id,
        'status' => 'disetujui',
        'type' => 'refund',
        'reason' => 'Cacat',
        'refund_amount' => 100000,
    ]);

    $response = $this->actingAs($customer)->post("/returns/{$returnReq->id}/tracking", [
        'return_tracking_number' => 'RESIRETUR123',
        'return_courier_name' => 'J&T',
    ]);

    $response->assertRedirect();
    $returnReq->refresh();
    $transaction->refresh();

    expect($returnReq->status)->toBe('barang_dikirim_customer');
    expect($returnReq->return_tracking_number)->toBe('RESIRETUR123');
    expect($returnReq->return_courier_name)->toBe('J&T');
    expect($transaction->return_status)->toBe('barang_dikirim_customer');
});

test('admin can confirm receipt of returned items and stock is restored', function () {
    [
        'transaction' => $transaction,
        'customer' => $customer,
        'admin' => $admin,
        'product' => $product,
        'txItem' => $txItem
    ] = createSelesaiTransaction();

    $returnReq = ReturnRequest::create([
        'return_number' => ReturnRequest::generateNumber(),
        'transaction_id' => $transaction->id,
        'user_id' => $customer->id,
        'status' => 'barang_dikirim_customer',
        'type' => 'refund',
        'reason' => 'Cacat',
        'refund_amount' => 100000,
    ]);

    $returnReq->items()->create([
        'transaction_item_id' => $txItem->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity_returned' => 1,
        'unit_price' => 100000,
        'refund_subtotal' => 100000,
    ]);

    $stock = ProductStock::where('product_id', $product->id)->first();
    $stockBefore = $stock->stock; // 5

    $response = $this->actingAs($admin)->post("/admin/returns/{$returnReq->id}/confirm-receipt");

    $response->assertRedirect();
    $returnReq->refresh();
    $transaction->refresh();
    $stock->refresh();

    expect($returnReq->status)->toBe('barang_diterima_toko');
    expect($transaction->return_status)->toBe('barang_diterima_toko');
    expect($stock->stock)->toBe($stockBefore + 1); // 6
});

test('admin can confirm receipt of returned items as damaged and stock is not restored', function () {
    [
        'transaction' => $transaction,
        'customer' => $customer,
        'admin' => $admin,
        'product' => $product,
        'txItem' => $txItem
    ] = createSelesaiTransaction();

    $returnReq = ReturnRequest::create([
        'return_number' => ReturnRequest::generateNumber(),
        'transaction_id' => $transaction->id,
        'user_id' => $customer->id,
        'status' => 'barang_dikirim_customer',
        'type' => 'refund',
        'reason' => 'Cacat',
        'refund_amount' => 100000,
    ]);

    $returnReq->items()->create([
        'transaction_item_id' => $txItem->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity_returned' => 1,
        'unit_price' => 100000,
        'refund_subtotal' => 100000,
    ]);

    $stock = ProductStock::where('product_id', $product->id)->first();
    $stockBefore = $stock->stock; // 5

    $response = $this->actingAs($admin)->post("/admin/returns/{$returnReq->id}/confirm-receipt", [
        'stock_action' => 'damaged',
    ]);

    $response->assertRedirect();
    $returnReq->refresh();
    $transaction->refresh();
    $stock->refresh();

    expect($returnReq->status)->toBe('barang_diterima_toko');
    expect($transaction->return_status)->toBe('barang_diterima_toko');
    expect($stock->stock)->toBe($stockBefore); // 5 (not changed!)

    $this->assertDatabaseHas('stock_movements', [
        'product_id' => $product->id,
        'transaction_id' => $transaction->id,
        'type' => 'retur',
        'quantity' => 0,
        'stock_before' => $stockBefore,
        'stock_after' => $stockBefore,
    ]);
});

test('admin can complete refund', function () {
    [
        'transaction' => $transaction,
        'customer' => $customer,
        'admin' => $admin,
    ] = createSelesaiTransaction();

    $returnReq = ReturnRequest::create([
        'return_number' => ReturnRequest::generateNumber(),
        'transaction_id' => $transaction->id,
        'user_id' => $customer->id,
        'status' => 'barang_diterima_toko',
        'type' => 'refund',
        'reason' => 'Cacat',
        'refund_amount' => 100000,
    ]);

    // Process refund (changes status to refund_diproses)
    $response = $this->actingAs($admin)->post("/admin/returns/{$returnReq->id}/process-refund", [
        'notes_admin' => 'Refund segera ditransfer.',
    ]);
    $response->assertRedirect();
    $returnReq->refresh();
    expect($returnReq->status)->toBe('refund_diproses');

    // Complete refund (changes status to selesai)
    $response2 = $this->actingAs($admin)->post("/admin/returns/{$returnReq->id}/complete-refund");
    $response2->assertRedirect();

    $returnReq->refresh();
    $transaction->refresh();

    expect($returnReq->status)->toBe('selesai');
    expect($transaction->return_status)->toBe('selesai');
});

test('admin can process replacement barang', function () {
    [
        'transaction' => $transaction,
        'customer' => $customer,
        'admin' => $admin,
        'product' => $product,
        'txItem' => $txItem
    ] = createSelesaiTransaction();

    $returnReq = ReturnRequest::create([
        'return_number' => ReturnRequest::generateNumber(),
        'transaction_id' => $transaction->id,
        'user_id' => $customer->id,
        'status' => 'barang_diterima_toko',
        'type' => 'penggantian_barang',
        'reason' => 'Cacat',
        'refund_amount' => 100000,
    ]);

    $returnReq->items()->create([
        'transaction_item_id' => $txItem->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity_returned' => 1,
        'unit_price' => 100000,
        'refund_subtotal' => 100000,
    ]);

    $response = $this->actingAs($admin)->post("/admin/returns/{$returnReq->id}/process-replacement");
    $response->assertRedirect();

    $returnReq->refresh();
    $transaction->refresh();

    expect($returnReq->status)->toBe('selesai');
    expect($transaction->return_status)->toBe('selesai');
    expect($returnReq->replacement_transaction_id)->not->toBeNull();

    // Verify replacement transaction is created with status diproses
    $replacementTx = Transaction::find($returnReq->replacement_transaction_id);
    expect($replacementTx->status)->toBe('diproses');
    expect($replacementTx->is_replacement_transaction)->toBeTrue();
    expect($replacementTx->original_transaction_id)->toBe($transaction->id);
    expect($replacementTx->items)->toHaveCount(1);
    expect($replacementTx->items->first()->quantity)->toBe(1);
});
