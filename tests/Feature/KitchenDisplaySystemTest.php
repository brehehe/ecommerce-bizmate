<?php

use App\Models\Category;
use App\Models\MembershipLevel;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.membership_enabled' => false]);

    // Seed required roles if not present
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
        'name' => 'Kuliner',
        'slug' => 'kuliner',
    ]);

    $this->sellerA = User::create([
        'name' => 'Chef Budi',
        'email' => 'budi@dapur.com',
        'password' => bcrypt('password'),
        'is_seller' => true,
        'store_name' => 'Dapur Budi',
    ]);
    $this->sellerA->assignRole('Seller');

    $this->product = Product::create([
        'user_id' => $this->sellerA->id,
        'name' => 'Bebek Kremes',
        'slug' => 'bebek-kremes',
        'sku' => 'BEBEK-01',
        'category_id' => $category->id,
        'active' => true,
    ]);

    $this->sellerB = User::create([
        'name' => 'Chef Doni',
        'email' => 'doni@dapur.com',
        'password' => bcrypt('password'),
        'is_seller' => true,
        'store_name' => 'Dapur Doni',
    ]);
    $this->sellerB->assignRole('Seller');

    $this->customer = User::create([
        'name' => 'Siti Aminah',
        'email' => 'siti@gmail.com',
        'password' => bcrypt('password'),
    ]);
    $this->customer->assignRole('Customer');

    $this->paymentMethod = PaymentMethod::create([
        'name' => 'QRIS',
        'code' => 'qris',
        'type' => 'manual',
        'is_active' => true,
    ]);

    // Create a transaction for Seller A
    $this->trxA = Transaction::create([
        'transaction_number' => 'TRX-FOOD-001',
        'user_id' => $this->customer->id,
        'payment_method_id' => $this->paymentMethod->id,
        'status' => 'belum_bayar',
        'kitchen_status' => 'pesanan_masuk',
        'resto_id' => $this->sellerA->id,
        'fulfillment_type' => 'self_pickup',
        'pickup_type' => 'takeaway',
        'shipping_courier' => 'self_pickup',
        'shipping_service' => 'takeaway',
        'scheduled_time_slot' => '12:30',
        'subtotal' => 50000,
        'grand_total' => 50000,
    ]);

    TransactionItem::create([
        'transaction_id' => $this->trxA->id,
        'product_id' => $this->product->id,
        'product_name' => 'Bebek Kremes',
        'product_sku' => 'BEBEK-01',
        'quantity' => 2,
        'harga_jual' => 25000,
        'subtotal' => 50000,
        'note' => 'Sambal dipisah',
    ]);
});

test('authorized seller can access KDS and see incoming tickets', function () {
    $response = $this->actingAs($this->sellerA)->get(route('admin.kitchen.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Kitchen/Index')
        ->has('tickets.pesanan_masuk', 1)
        ->where('tickets.pesanan_masuk.0.transaction_number', 'TRX-FOOD-001')
        ->where('tickets.pesanan_masuk.0.scheduled_time_slot', '12:30')
        ->where('tickets.pesanan_masuk.0.items.0.name', 'Bebek Kremes')
    );
});

test('seller can update ticket status from pesanan_masuk to dimasak', function () {
    $response = $this->actingAs($this->sellerA)->post(route('admin.kitchen.update-status', $this->trxA->id), [
        'kitchen_status' => 'dimasak',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->trxA->refresh();
    expect($this->trxA->kitchen_status)->toBe('dimasak')
        ->and($this->trxA->status)->toBe('diproses');

    expect($this->trxA->statusHistories()->where('status', 'diproses')->exists())->toBeTrue();
});

test('seller can update ticket status to siap_diambil for self pickup', function () {
    $response = $this->actingAs($this->sellerA)->post(route('admin.kitchen.update-status', $this->trxA->id), [
        'kitchen_status' => 'siap_diambil',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->trxA->refresh();
    expect($this->trxA->kitchen_status)->toBe('siap_diambil')
        ->and($this->trxA->status)->toBe('ready_for_pickup');
});

test('seller can update ticket status to selesai', function () {
    $response = $this->actingAs($this->sellerA)->post(route('admin.kitchen.update-status', $this->trxA->id), [
        'kitchen_status' => 'selesai',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->trxA->refresh();
    expect($this->trxA->kitchen_status)->toBe('selesai')
        ->and($this->trxA->status)->toBe('selesai');
});

test('unauthorized seller cannot update tickets belonging to other resto', function () {
    $response = $this->actingAs($this->sellerB)->post(route('admin.kitchen.update-status', $this->trxA->id), [
        'kitchen_status' => 'dimasak',
    ]);

    $response->assertSessionHas('error', 'Anda tidak memiliki hak akses untuk pesanan ini.');

    $this->trxA->refresh();
    expect($this->trxA->kitchen_status)->toBe('pesanan_masuk');
});

test('customer without admin or seller role is blocked from KDS by not_customer middleware', function () {
    $response = $this->actingAs($this->customer)->get(route('admin.kitchen.index'));
    // not_customer middleware blocks customer
    expect(in_array($response->getStatusCode(), [403, 302]))->toBeTrue();
});

test('get request to update-status does not throw 405 and safely redirects to kitchen index', function () {
    $response = $this->actingAs($this->sellerA)->get(route('admin.kitchen.update-status', $this->trxA->id));

    $response->assertRedirect(route('admin.kitchen.index'));
});

test('resto owner or seller can view order tracking directly without entering contact info', function () {
    $response = $this->actingAs($this->sellerA)->get('/cek-pesanan?trx='.$this->trxA->transaction_number);

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Storefront/TrackOrder')
        ->where('searchTrx', $this->trxA->transaction_number)
        ->has('transaction')
        ->where('errorMessage', null)
    );
});
