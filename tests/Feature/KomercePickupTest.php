<?php

use App\Models\Category;
use App\Models\CustomerAddress;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->customer = User::factory()->create();
    $this->admin = User::factory()->create();

    $this->category = Category::create([
        'name' => 'Test Cat',
        'slug' => 'test-cat',
        'icon' => 'ti-box',
    ]);

    $this->product = Product::create([
        'name' => 'Test Product',
        'slug' => 'test-product',
        'sku' => 'TEST-P1',
        'category_id' => $this->category->id,
        'summary' => 'Summary',
        'description' => 'Description',
        'weight' => 2000, // 2kg
        'active' => true,
    ]);

    ProductStock::create([
        'product_id' => $this->product->id,
        'stock' => 10,
        'is_unlimited' => false,
    ]);

    $this->address = CustomerAddress::create([
        'user_id' => $this->customer->id,
        'receiver_name' => 'Customer Test',
        'phone_number' => '08000000000',
        'label' => 'Rumah',
        'full_address' => 'Jl. Test 1',
        'is_primary' => true,
    ]);

    $this->paymentMethod = PaymentMethod::create([
        'name' => 'BCA',
        'type' => 'manual',
        'bank_name' => 'BCA',
        'account_number' => '9876543210',
        'account_name' => 'Toko Test',
        'is_active' => true,
        'admin_fee' => 0,
    ]);

    $this->transaction = Transaction::create([
        'transaction_number' => 'TRX-TEST-00001',
        'user_id' => $this->customer->id,
        'customer_address_id' => $this->address->id,
        'payment_method_id' => $this->paymentMethod->id,
        'status' => 'diproses',
        'subtotal' => 200000,
        'discount_amount' => 0,
        'shipping_fee' => 15000,
        'shipping_discount' => 0,
        'admin_fee' => 0,
        'grand_total' => 215000,
        'shipping_courier' => 'jne',
        'shipping_service' => 'REG',
        'booking_code' => 'BOOK-TEST-123',
    ]);

    $this->item = TransactionItem::create([
        'transaction_id' => $this->transaction->id,
        'product_id' => $this->product->id,
        'product_name' => $this->product->name,
        'product_sku' => $this->product->sku,
        'quantity' => 2, // Total weight = 4kg (4000g)
        'hpp' => 60000,
        'harga_jual' => 100000,
        'diskon_item' => 0,
        'harga_akhir' => 100000,
        'subtotal' => 200000,
    ]);
});

test('it prevents requesting pickup if transaction status is not eligible', function () {
    $this->transaction->update(['status' => 'belum_bayar']);

    $response = $this->actingAs($this->admin)->post("/admin/transactions/{$this->transaction->id}/komerce/pickup", [
        'pickup_time' => now()->addMinutes(120)->format('Y-m-d H:i:s'),
        'vehicle_type' => 'motorcycle',
    ]);

    $response->assertSessionHas('error', 'Pesanan tidak eligible untuk pickup (status harus Diproses atau Dikemas).');
    $this->transaction->refresh();
    expect($this->transaction->status)->toBe('belum_bayar');
});

test('it prevents requesting pickup if booking code is missing', function () {
    $this->transaction->update([
        'status' => 'diproses',
        'booking_code' => null,
    ]);

    $response = $this->actingAs($this->admin)->post("/admin/transactions/{$this->transaction->id}/komerce/pickup", [
        'pickup_time' => now()->addMinutes(120)->format('Y-m-d H:i:s'),
        'vehicle_type' => 'motorcycle',
    ]);

    $response->assertSessionHas('error', 'Kode booking pengiriman tidak ditemukan. Silakan pesan pengiriman terlebih dahulu.');
});

test('it validates that pickup time is at least 90 minutes from now/transaction creation', function () {
    // 60 minutes from now (invalid)
    $response = $this->actingAs($this->admin)->post("/admin/transactions/{$this->transaction->id}/komerce/pickup", [
        'pickup_time' => now()->addMinutes(60)->format('Y-m-d H:i:s'),
        'vehicle_type' => 'motorcycle',
    ]);

    $response->assertSessionHasErrors(['pickup_time']);
});

test('it validates motorcycle weight limit', function () {
    // Current total weight is 4kg. Let's make it 6kg by updating quantity to 3 (3 * 2kg = 6kg)
    $this->item->update(['quantity' => 3]);

    // Motor limit is 5kg. 6kg should fail.
    $response = $this->actingAs($this->admin)->post("/admin/transactions/{$this->transaction->id}/komerce/pickup", [
        'pickup_time' => now()->addMinutes(120)->format('Y-m-d H:i:s'),
        'vehicle_type' => 'motorcycle',
    ]);

    $response->assertSessionHasErrors(['vehicle_type']);
});

test('it validates truck requirement for 10kg or more', function () {
    // Let's make it 12kg by updating quantity to 6 (6 * 2kg = 12kg)
    $this->item->update(['quantity' => 6]);

    // Vehicle = car should fail since weight >= 10kg requires truck
    $response = $this->actingAs($this->admin)->post("/admin/transactions/{$this->transaction->id}/komerce/pickup", [
        'pickup_time' => now()->addMinutes(120)->format('Y-m-d H:i:s'),
        'vehicle_type' => 'car',
    ]);

    $response->assertSessionHasErrors(['vehicle_type']);
});

test('it schedules pickup successfully and updates transaction status to out_for_pickup', function () {
    Http::fake([
        'https://api-sandbox.collaborator.komerce.id/order/api/v1/pickup/request' => Http::response([
            'meta' => [
                'message' => 'Success Request Pickup',
                'code' => 201,
                'status' => 'success',
            ],
            'data' => [
                [
                    'status' => 'success',
                    'order_no' => 'BOOK-TEST-123',
                    'awb' => 'KOMERKOMBOOK-TEST-123',
                ],
            ],
        ], 201),
    ]);

    $response = $this->actingAs($this->admin)->post("/admin/transactions/{$this->transaction->id}/komerce/pickup", [
        'pickup_time' => now()->addMinutes(120)->format('Y-m-d H:i:s'),
        'vehicle_type' => 'motorcycle',
    ]);

    $response->assertSessionHas('success');
    $this->transaction->refresh();
    expect($this->transaction->status)->toBe('out_for_pickup');
    expect($this->transaction->tracking_number)->toBe('KOMERKOMBOOK-TEST-123');
});
