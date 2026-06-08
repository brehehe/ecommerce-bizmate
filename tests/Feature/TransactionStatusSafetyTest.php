<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createTransactionWithStatus(string $status): array
{
    $customer = User::factory()->create();
    $admin = User::factory()->create();

    $category = Category::create([
        'name' => 'Test Cat',
        'slug' => 'test-cat',
        'icon' => 'ti-box',
    ]);

    $rand = rand(1000, 9999);
    $product = Product::create([
        'name' => 'Test Product '.$rand,
        'slug' => 'test-product-'.$rand,
        'sku' => 'TEST-P1-'.$rand,
        'category_id' => $category->id,
        'active' => true,
    ]);

    $product->productPrice()->create(['price' => 100000, 'cost' => 60000]);

    ProductStock::create([
        'product_id' => $product->id,
        'stock' => 5,
        'is_unlimited' => false,
    ]);

    $transaction = Transaction::create([
        'user_id' => $customer->id,
        'transaction_number' => 'TRX-'.time().'-'.rand(100, 999),
        'status' => $status,
        'subtotal' => 100000,
        'shipping_fee' => 10000,
        'total' => 110000,
        'receiver_name' => 'Receiver',
        'phone_number' => '0812345678',
        'full_address' => 'Full Address',
        'province_name' => 'Province',
        'regency_name' => 'Regency',
        'district_name' => 'District',
        'village_name' => 'Village',
        'postal_code' => '12345',
        'courier_code' => 'jne',
        'courier_service' => 'REG',
    ]);

    return ['transaction' => $transaction, 'admin' => $admin];
}

test('admin cannot revert transaction status to previous step', function () {
    ['transaction' => $transaction, 'admin' => $admin] = createTransactionWithStatus('dikirim');

    // Try to revert status to diproses
    $response = $this->actingAs($admin)->post(
        route('admin.transactions.update-status', $transaction),
        ['status' => 'diproses']
    );

    $response->assertRedirect();
    $response->assertSessionHas('error', 'Status transaksi tidak dapat diubah kembali ke status sebelumnya.');

    $transaction->refresh();
    expect($transaction->status)->toBe('dikirim');
});

test('admin can progress transaction status forward', function () {
    ['transaction' => $transaction, 'admin' => $admin] = createTransactionWithStatus('diproses');

    // Progress status to dikemas
    $response = $this->actingAs($admin)->post(
        route('admin.transactions.update-status', $transaction),
        ['status' => 'dikemas']
    );

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Status transaksi berhasil diperbarui.');

    $transaction->refresh();
    expect($transaction->status)->toBe('dikemas');
});

test('bulk status update skips reversion of statuses', function () {
    $admin = User::factory()->create();

    ['transaction' => $trx1] = createTransactionWithStatus('diproses');
    ['transaction' => $trx2] = createTransactionWithStatus('dikirim');

    // Try to bulk update both to diproses
    $response = $this->actingAs($admin)->post(
        route('admin.transactions.bulk-status'),
        [
            'ids' => [$trx1->id, $trx2->id],
            'status' => 'diproses',
        ]
    );

    $response->assertRedirect();

    $trx1->refresh();
    $trx2->refresh();

    // trx1 is already diproses, so it remains diproses or is skipped
    expect($trx1->status)->toBe('diproses');

    // trx2 is dikirim (further along), so it should not be reverted to diproses
    expect($trx2->status)->toBe('dikirim');
});
