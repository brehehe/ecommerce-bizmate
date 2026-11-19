<?php

use App\Models\Category;
use App\Models\MembershipLevel;
use App\Models\MenuModifier;
use App\Models\Product;
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
        'name' => 'Chef Madura',
        'email' => 'chef@madura.com',
        'password' => bcrypt('password'),
        'is_seller' => true,
        'store_name' => 'Bebek Madura Asli',
        'store_slug' => 'bebek-madura',
    ]);
    $this->seller->assignRole('Seller');

    $this->restoProfile = RestoProfile::create([
        'user_id' => $this->seller->id,
        'resto_name' => 'Bebek Madura Asli',
        'slug' => 'bebek-madura',
        'culinary_category' => 'Aneka Ayam & Bebek',
        'address' => 'Jl. Ijen No. 10',
        'latitude' => -7.978000,
        'longitude' => 112.620000,
        'avg_prep_time_minutes' => 20,
        'allow_pickup' => true,
        'allow_delivery' => true,
        'max_delivery_radius_km' => 8.0,
        'is_open_now' => true,
        'approval_status' => 'approved',
    ]);

    $category = Category::create([
        'name' => 'Kuliner',
        'slug' => 'kuliner',
    ]);

    $this->product = Product::create([
        'user_id' => $this->seller->id,
        'category_id' => $category->id,
        'name' => 'Bebek Hitam Spesial',
        'slug' => 'bebek-hitam-spesial',
        'sku' => 'SKU-BEBEK-HITAM',
        'is_active' => true,
    ]);
    $this->product->productPrice()->create(['price' => 38000]);
    $this->product->productStock()->create(['stock' => 100]);
});

test('customer can checkout with dine-in and table number', function () {
    $customer = User::create([
        'name' => 'Doni DineIn',
        'email' => 'doni@test.com',
        'password' => bcrypt('password'),
    ]);
    $customer->assignRole('Customer');

    $trx = Transaction::create([
        'user_id' => $customer->id,
        'resto_id' => $this->seller->id,
        'transaction_number' => 'TRX-DINEIN-007',
        'total_amount' => 38000,
        'shipping_cost' => 0,
        'shipping_courier' => 'self_pickup',
        'fulfillment_type' => 'dine_in',
        'pickup_type' => 'dine_in',
        'table_number' => 'Meja 07',
        'status' => 'pesanan_masuk',
    ]);

    expect($trx->fulfillment_type)->toBe('dine_in');
    expect($trx->pickup_type)->toBe('dine_in');
    expect($trx->table_number)->toBe('Meja 07');
});

test('haversine shipping calculation uses resto coordinates and returns culinary etd', function () {
    $this->actingAs($this->seller);

    // Customer address ~2.5 km away from Resto
    $response = $this->postJson('/checkout/shipping-cost', [
        'courier' => 'store_courier',
        'resto_id' => $this->seller->id,
        'destination' => 'Malang',
        'weight' => 500,
        'latitude' => -7.960000,
        'longitude' => 112.620000,
    ]);

    $response->assertStatus(200);
    $data = $response->json('results.0');

    expect($data['name'])->toBe('Kurir Resto');
    expect($data['costs'][0]['service'])->toBe('Resto Delivery');
    expect($data['costs'][0]['cost'][0]['etd'])->toBe('20-30 menit');
});

test('haversine shipping rejects delivery if destination exceeds resto max radius', function () {
    $this->actingAs($this->seller);

    // Coordinate far away (~30 km away, exceeding resto max 8 km)
    $response = $this->postJson('/checkout/shipping-cost', [
        'courier' => 'store_courier',
        'resto_id' => $this->seller->id,
        'destination' => 'Surabaya',
        'weight' => 500,
        'latitude' => -7.700000,
        'longitude' => 112.600000,
    ]);

    $response->assertStatus(422);
    $response->assertJsonStructure(['error']);
});

test('resto owner can manage dynamic menu modifiers', function () {
    $this->actingAs($this->seller);

    // 1. View modifiers page
    $indexResponse = $this->get("/admin/products/{$this->product->id}/modifiers");
    $indexResponse->assertStatus(200);
    $indexResponse->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Products/Modifiers')
        ->where('product.name', 'Bebek Hitam Spesial')
        ->has('product.modifiers')
    );

    // 2. Create modifier group
    $createResponse = $this->post("/admin/products/{$this->product->id}/modifiers", [
        'group_name' => 'Level Pedas Sambal',
        'is_required' => true,
        'is_multiple' => false,
        'items' => [
            ['name' => 'Level 1: Sedang', 'additional_price' => 0, 'is_default' => true],
            ['name' => 'Level 2: Pedas', 'additional_price' => 0, 'is_default' => false],
            ['name' => 'Level 3: Ekstra Pedas Gila', 'additional_price' => 3000, 'is_default' => false],
        ],
    ]);
    $createResponse->assertRedirect();
    $createResponse->assertSessionHas('success');

    $modifier = MenuModifier::where('product_id', $this->product->id)->first();
    expect($modifier)->not->toBeNull();
    expect($modifier->group_name)->toBe('Level Pedas Sambal');
    expect($modifier->items()->count())->toBe(3);

    // 3. Update modifier group
    $updateResponse = $this->put("/admin/products/{$this->product->id}/modifiers/{$modifier->id}", [
        'group_name' => 'Level Pedas Sambal Super',
        'is_required' => true,
        'is_multiple' => false,
        'items' => [
            ['name' => 'Level 0: Tidak Pedas', 'additional_price' => 0, 'is_default' => false],
            ['name' => 'Level 1: Sedang', 'additional_price' => 0, 'is_default' => true],
            ['name' => 'Level 2: Pedas', 'additional_price' => 2000, 'is_default' => false],
            ['name' => 'Level 3: Pedas Mampus', 'additional_price' => 5000, 'is_default' => false],
        ],
    ]);
    $updateResponse->assertRedirect();

    $modifier->refresh();
    expect($modifier->group_name)->toBe('Level Pedas Sambal Super');
    expect($modifier->items()->count())->toBe(4);

    // 4. Delete modifier group
    $deleteResponse = $this->delete("/admin/products/{$this->product->id}/modifiers/{$modifier->id}");
    $deleteResponse->assertRedirect();

    expect(MenuModifier::where('id', $modifier->id)->exists())->toBeFalse();
});

test('unauthorized user cannot manage another seller product modifiers', function () {
    $otherSeller = User::create([
        'name' => 'Chef Lain',
        'email' => 'lain@seller.com',
        'password' => bcrypt('password'),
        'is_seller' => true,
        'store_name' => 'Resto Lain',
    ]);
    $otherSeller->assignRole('Seller');

    $this->actingAs($otherSeller);

    $response = $this->get("/admin/products/{$this->product->id}/modifiers");
    $response->assertStatus(403);
});
