<?php

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Courier;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

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
