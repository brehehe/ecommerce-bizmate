<?php

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Courier;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\CourierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('admin can access couriers index page', function () {
    $response = $this->actingAs($this->user)->get(route('admin.master-data.couriers'));
    $response->assertOk();
});

test('admin can create a courier', function () {
    $response = $this->actingAs($this->user)->post(route('admin.master-data.couriers.store'), [
        'code' => 'tiki',
        'name' => 'TIKI Express',
        'is_active' => true,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('couriers', [
        'code' => 'tiki',
        'name' => 'TIKI Express',
        'is_active' => true,
    ]);
});

test('admin can update a courier', function () {
    $courier = Courier::create([
        'code' => 'jne',
        'name' => 'JNE',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)->put(route('admin.master-data.couriers.update', $courier), [
        'code' => 'jne-yes',
        'name' => 'JNE YES',
        'is_active' => false,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('couriers', [
        'id' => $courier->id,
        'code' => 'jne-yes',
        'name' => 'JNE YES',
        'is_active' => false,
    ]);
});

test('admin can toggle courier active status', function () {
    $courier = Courier::create([
        'code' => 'pos',
        'name' => 'POS Indonesia',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)->post(route('admin.master-data.couriers.toggle-active', $courier));

    $response->assertRedirect();
    $this->assertDatabaseHas('couriers', [
        'id' => $courier->id,
        'is_active' => false,
    ]);
});

test('admin can delete a courier', function () {
    $courier = Courier::create([
        'code' => 'wahana',
        'name' => 'Wahana',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)->delete(route('admin.master-data.couriers.destroy', $courier));

    $response->assertRedirect();
    $this->assertSoftDeleted('couriers', [
        'id' => $courier->id,
    ]);
});

test('checkout page returns only active couriers', function () {
    Courier::create(['code' => 'jne', 'name' => 'JNE', 'is_active' => true]);
    Courier::create(['code' => 'tiki', 'name' => 'TIKI', 'is_active' => false]);

    $category = Category::create(['name' => 'Cat', 'slug' => 'cat', 'icon' => 'ti-home']);
    $product = Product::create([
        'name' => 'Prod', 'slug' => 'prod', 'sku' => 'PROD', 'category_id' => $category->id, 'active' => true,
    ]);
    $product->productPrice()->create(['price' => 1000, 'cost' => 500]);
    CartItem::create([
        'user_id' => $this->user->id, 'product_id' => $product->id, 'quantity' => 1, 'is_checked' => true,
    ]);

    $response = $this->actingAs($this->user)->get(route('checkout.index'));
    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Storefront/Checkout')
        ->has('couriers', 1)
        ->where('couriers.0.code', 'jne')
    );
});

test('courier seeder seeds only specific couriers', function () {
    $seeder = new CourierSeeder;
    $seeder->run();

    $couriers = Courier::all();
    expect($couriers)->toHaveCount(22);

    $courierCodes = $couriers->pluck('code')->toArray();
    expect($courierCodes)->toEqualCanonicalizing([
        'gojek', 'grab', 'deliveree', 'jne', 'tiki', 'ninja', 'lion', 'rara',
        'sicepat', 'jnt', 'idexpress', 'rpx', 'jdl', 'wahana', 'pos', 'anteraja',
        'sap', 'paxel', 'borzo', 'lalamove', 'sentralcargo', 'dash_express',
    ]);

    foreach ($couriers as $courier) {
        expect($courier->is_active)->toBeTrue();
    }
});

test('admin can create resto courier and courier can log in at /kurir/login', function () {
    $response = $this->actingAs($this->user)->post(route('admin.master-data.resto-couriers.store'), [
        'name' => 'Budi Kurir',
        'email' => 'budikurir@test.com',
        'phone_number' => '08123456789',
        'password' => 'secret123',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('users', [
        'name' => 'Budi Kurir',
        'email' => 'budikurir@test.com',
        'phone_number' => '08123456789',
    ]);

    $courier = User::where('email', 'budikurir@test.com')->first();
    expect($courier->hasRole('Kurir Toko'))->toBeTrue();

    // Log out admin then courier logs in at /kurir/login
    auth()->logout();
    $loginResponse = $this->post('/kurir/login', [
        'email' => 'budikurir@test.com',
        'password' => 'secret123',
    ]);
    $loginResponse->assertRedirect(route('kurir.dashboard'));
    $this->assertAuthenticatedAs($courier);
});

test('admin can reset resto courier password', function () {
    $courier = User::factory()->create([
        'email' => 'driver@test.com',
        'password' => 'oldpassword',
    ]);
    if (! Role::where('name', 'Kurir Toko')->where('guard_name', 'web')->exists()) {
        Role::create(['name' => 'Kurir Toko', 'guard_name' => 'web']);
    }
    $courier->assignRole('Kurir Toko');

    $response = $this->actingAs($this->user)->post(route('admin.master-data.resto-couriers.reset-password', $courier), [
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertRedirect();

    // Log out admin then courier logs in with new password
    auth()->logout();
    $loginResponse = $this->post('/kurir/login', [
        'email' => 'driver@test.com',
        'password' => 'newpassword123',
    ]);
    $loginResponse->assertRedirect(route('kurir.dashboard'));
    $this->assertAuthenticatedAs($courier);
});

test('seller only sees couriers belonging to their own resto in master data', function () {
    $sellerA = User::factory()->create(['is_seller' => true]);
    $sellerB = User::factory()->create(['is_seller' => true]);

    if (! Role::where('name', 'Kurir Toko')->where('guard_name', 'web')->exists()) {
        Role::create(['name' => 'Kurir Toko', 'guard_name' => 'web']);
    }

    $courierA = User::factory()->create(['name' => 'Kurir A', 'resto_id' => $sellerA->id]);
    $courierA->assignRole('Kurir Toko');

    $courierB = User::factory()->create(['name' => 'Kurir B', 'resto_id' => $sellerB->id]);
    $courierB->assignRole('Kurir Toko');

    $response = $this->actingAs($sellerA)->get(route('admin.master-data.couriers'));
    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Admin/MasterData/Couriers')
        ->has('restoCouriers', 1)
        ->where('restoCouriers.0.name', 'Kurir A')
    );
});
