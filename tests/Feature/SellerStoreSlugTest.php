<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('storefront seller store page is reachable with uppercase slug', function () {
    $seller = User::factory()->create([
        'is_seller' => true,
        'is_active' => true,
        'store_slug' => 'hasbi-GKQy',
    ]);

    $this->get('/hasbi-GKQy')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Storefront/SellerStore'));

    $this->get('/hasbi-gkqy')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Storefront/SellerStore'));
});

test('storefront seller store page returns 404 for unknown slug', function () {
    User::factory()->create([
        'is_seller' => true,
        'is_active' => true,
        'store_slug' => 'hasbi-gkqy',
    ]);

    $this->get('/toko-yang-tidak-ada')->assertNotFound();
});

test('storefront resolves legacy store-{name}-{uuid} fallback links', function () {
    $seller = User::factory()->create([
        'name' => 'Kokoh Yanuar',
        'is_seller' => true,
        'is_active' => true,
        'store_slug' => null,
    ]);

    $this->get('/store-kokoh-yanuar-'.$seller->id)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Storefront/SellerStore'));
});

test('storefront legacy fallback link returns 404 when uuid does not belong to a seller', function () {
    $customer = User::factory()->create([
        'name' => 'Kokoh Yanuar',
        'is_seller' => false,
        'store_slug' => null,
    ]);

    $this->get('/store-kokoh-yanuar-'.$customer->id)->assertNotFound();
});

test('storefront seller store page lists the seller products', function () {
    $category = Category::create(['name' => 'Sepatu', 'slug' => 'sepatu']);

    $seller = User::factory()->create([
        'is_seller' => true,
        'is_active' => true,
        'store_slug' => 'hasbi-gkqy',
    ]);

    $otherSeller = User::factory()->create([
        'is_seller' => true,
        'is_active' => true,
        'store_slug' => 'toko-lain-abc',
    ]);

    $sellerProduct = Product::create([
        'name' => 'Sepatu Hasbi',
        'slug' => 'sepatu-hasbi',
        'sku' => 'HB01',
        'category_id' => $category->id,
        'user_id' => $seller->id,
        'active' => true,
        'listing_expires_at' => now()->addDays(30),
    ]);
    $sellerProduct->productPrice()->create(['product_id' => $sellerProduct->id, 'price' => 100000]);

    $otherProduct = Product::create([
        'name' => 'Sepatu Toko Lain',
        'slug' => 'sepatu-toko-lain',
        'sku' => 'TL01',
        'category_id' => $category->id,
        'user_id' => $otherSeller->id,
        'active' => true,
        'listing_expires_at' => now()->addDays(30),
    ]);

    $this->get('/hasbi-gkqy')
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($sellerProduct) {
            $page->component('Storefront/SellerStore')
                ->has('products.data', 1)
                ->where('products.data.0.name', 'Sepatu Hasbi')
                ->where('products.data.0.id', $sellerProduct->id)
                ->where('products.total', 1)
                ->where('filters.q', '')
                ->where('filters.sort', 'latest');
        });
});

test('storefront seller store page allows store owner to access product create', function () {
    $seller = User::factory()->create([
        'is_seller' => true,
        'is_active' => true,
        'store_slug' => 'hasbi-gkqy',
    ]);

    $this->get('/hasbi-gkqy')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Storefront/SellerStore')
            ->where('seller.id', $seller->id)
            ->where('seller.store_slug', 'hasbi-gkqy')
        );

    $this->actingAs($seller)
        ->get('/admin/products/create')
        ->assertOk();
});
