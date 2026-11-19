<?php

use App\Models\MembershipLevel;
use App\Models\PaymentMethod;
use App\Models\RestoProfile;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.membership_enabled' => false]);
    config(['app.is_foodpick' => true]);

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

    $this->seller = User::create([
        'name' => 'Chef Jangkung',
        'email' => 'jangkung@sinjay.com',
        'password' => bcrypt('password'),
        'is_seller' => true,
        'store_name' => 'Bebek Sinjay Jangkung',
        'store_slug' => 'bebek-sinjay',
        'store_address' => 'Jl. Soekarno Hatta No. 45',
    ]);
    $this->seller->assignRole('Seller');

    $this->restoProfile = RestoProfile::create([
        'user_id' => $this->seller->id,
        'resto_name' => 'Bebek Sinjay Jangkung',
        'slug' => 'bebek-sinjay',
        'culinary_category' => 'Aneka Ayam & Bebek',
        'description' => 'Bebek goreng kremes bumbu hitam khas Madura.',
        'address' => 'Jl. Soekarno Hatta No. 45',
        'latitude' => -7.983908,
        'longitude' => 112.621391,
        'avg_prep_time_minutes' => 20,
        'allow_pickup' => true,
        'allow_delivery' => true,
        'max_delivery_radius_km' => 10,
        'is_open_now' => true,
        'approval_status' => 'approved',
    ]);
});

test('storefront homepage shares culinary directory data when IS_FOODPICK is true', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Storefront/Home')
        ->has('restoPartners')
        ->has('culinaryCategories')
        ->where('isFoodPick', true)
    );
});

test('resto profile management page renders with current resto data', function () {
    $this->actingAs($this->seller);

    $response = $this->get('/admin/resto-profile');

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/RestoProfile/Index')
        ->where('restoProfile.resto_name', 'Bebek Sinjay Jangkung')
        ->where('restoProfile.culinary_category', 'Aneka Ayam & Bebek')
        ->where('restoProfile.avg_prep_time_minutes', 20)
        ->has('culinaryCategories')
    );
});

test('resto profile can be updated by resto owner', function () {
    $this->actingAs($this->seller);

    $response = $this->put('/admin/resto-profile', [
        'resto_name' => 'Bebek Sinjay Super Pedas',
        'culinary_category' => 'Aneka Ayam & Bebek',
        'description' => 'Bebek bumbu hitam pedas nampol.',
        'address' => 'Jl. Borobudur No. 12',
        'latitude' => -7.950000,
        'longitude' => 112.630000,
        'avg_prep_time_minutes' => 25,
        'max_delivery_radius_km' => 12,
        'allow_pickup' => true,
        'allow_delivery' => true,
        'is_open_now' => true,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->restoProfile->refresh();
    expect($this->restoProfile->resto_name)->toBe('Bebek Sinjay Super Pedas');
    expect($this->restoProfile->address)->toBe('Jl. Borobudur No. 12');
    expect($this->restoProfile->avg_prep_time_minutes)->toBe(25);
});

test('resto owner can toggle emergency open and closed status', function () {
    $this->actingAs($this->seller);

    expect($this->restoProfile->is_open_now)->toBeTrue();

    // Toggle to closed
    $response = $this->post('/admin/resto-profile/toggle-open');
    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->restoProfile->refresh();
    expect($this->restoProfile->is_open_now)->toBeFalse();

    // Toggle back to open
    $this->post('/admin/resto-profile/toggle-open');
    $this->restoProfile->refresh();
    expect($this->restoProfile->is_open_now)->toBeTrue();
});

test('courier dispatch page renders orders and allows status updates', function () {
    $this->actingAs($this->seller);

    $trx = Transaction::create([
        'user_id' => $this->seller->id,
        'resto_id' => $this->seller->id,
        'transaction_number' => 'TRX-FOOD-DISPATCH-001',
        'total_amount' => 45000,
        'shipping_cost' => 10000,
        'shipping_courier' => 'store_courier',
        'fulfillment_type' => 'store_courier',
        'status' => 'siap_diambil',
        'shipping_address' => 'Jl. Cengger Ayam No. 8',
    ]);

    $response = $this->get('/admin/courier-dispatch');
    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/CourierDispatch/Index')
        ->has('transactions.data', 1)
        ->where('counts.ready', 1)
    );

    // Update status to 'dikirim' (Kurir Berangkat)
    $updateResponse = $this->post("/admin/courier-dispatch/{$trx->id}/status", [
        'status' => 'dikirim',
        'notes' => 'Kurir sedang dalam perjalanan',
    ]);
    $updateResponse->assertRedirect();

    $trx->refresh();
    expect($trx->status)->toBe('dikirim');

    // Update status to 'selesai'
    $completeResponse = $this->post("/admin/courier-dispatch/{$trx->id}/status", [
        'status' => 'selesai',
        'notes' => 'Pesanan diterima pelanggan',
    ]);
    $completeResponse->assertRedirect();

    $trx->refresh();
    expect($trx->status)->toBe('selesai');
    expect($trx->delivery_arrived_at)->not->toBeNull();
});

test('convenience shortcut routes redirect to admin pages', function () {
    $this->actingAs($this->seller);

    $this->get('/resto/profile')->assertRedirect(route('admin.resto-profile.index'));
    $this->get('/resto/courier')->assertRedirect(route('admin.courier-dispatch.index'));
    $this->get('/resto/kds')->assertRedirect(route('admin.kitchen.index'));
});

test('resto can register new courier account in courier dispatch', function () {
    $this->actingAs($this->seller);

    $response = $this->post(route('admin.courier-dispatch.store-courier'), [
        'name' => 'Bambang Driver',
        'email' => 'bambang.driver@gmail.com',
        'phone_number' => '081298765432',
        'password' => 'secret123',
    ]);

    $response->assertRedirect(route('admin.courier-dispatch.index'));
    $response->assertSessionHas('success');

    $courier = User::where('email', 'bambang.driver@gmail.com')->first();
    expect($courier)->not->toBeNull();
    expect($courier->name)->toBe('Bambang Driver');
    expect($courier->hasRole('Kurir Toko'))->toBeTrue();
});

test('resto can assign courier to a delivery order', function () {
    $this->actingAs($this->seller);

    $courier = User::create([
        'name' => 'Agus Kurir',
        'email' => 'agus.kurir@gmail.com',
        'phone_number' => '081344445555',
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);

    if (! Role::where('name', 'Kurir Toko')->exists()) {
        Role::create(['name' => 'Kurir Toko', 'guard_name' => 'web']);
    }
    $courier->assignRole('Kurir Toko');

    $trx = Transaction::create([
        'transaction_number' => 'TRX-FOOD-DISPATCH-99',
        'user_id' => $this->seller->id,
        'resto_id' => $this->seller->id,
        'status' => 'diproses',
        'kitchen_status' => 'dimasak',
        'payment_status' => 'paid',
        'shipping_courier' => 'store_courier',
        'fulfillment_type' => 'store_courier',
        'subtotal' => 50000,
        'grand_total' => 60000,
        'shipping_fee' => 10000,
    ]);

    $response = $this->post(route('admin.courier-dispatch.assign-courier', $trx->id), [
        'courier_user_id' => $courier->id,
    ]);

    $response->assertRedirect(route('admin.courier-dispatch.index'));
    $response->assertSessionHas('success');

    $trx->refresh();
    expect($trx->courier_user_id)->toBe($courier->id);
    expect($trx->courier_name)->toBe('Agus Kurir');
});

test('completing COD delivery order automatically marks payment_status as paid', function () {
    $this->actingAs($this->seller);

    $pm = PaymentMethod::create([
        'name' => 'Bayar di Tempat (COD / Kasir Resto)',
        'type' => 'manual',
        'is_active' => true,
    ]);

    $trx = Transaction::create([
        'transaction_number' => 'TRX-FOOD-COD-001',
        'user_id' => $this->seller->id,
        'resto_id' => $this->seller->id,
        'status' => 'dikirim',
        'payment_method_id' => $pm->id,
        'payment_status' => 'unpaid',
        'shipping_courier' => 'store_courier',
        'fulfillment_type' => 'store_courier',
        'subtotal' => 30000,
        'grand_total' => 30000,
    ]);

    $response = $this->post("/admin/courier-dispatch/{$trx->id}/status", [
        'status' => 'selesai',
        'notes' => 'Makanan tiba dan uang tunai diterima kurir',
    ]);

    $response->assertRedirect();
    $trx->refresh();

    expect($trx->status)->toBe('selesai');
    expect($trx->payment_status)->toBe('paid');
});
