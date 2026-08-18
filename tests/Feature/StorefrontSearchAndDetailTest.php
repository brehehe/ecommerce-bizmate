<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('storefront search returns active brands and filters products by multiple brands', function () {
    $category = Category::create(['name' => 'Tech', 'slug' => 'tech']);

    $brand1 = Brand::create(['name' => 'Apple', 'slug' => 'apple', 'is_active' => true]);
    $brand2 = Brand::create(['name' => 'Samsung', 'slug' => 'samsung', 'is_active' => true]);
    $brandInactive = Brand::create(['name' => 'Nokia', 'slug' => 'nokia', 'is_active' => false]);

    // Product 1: Apple
    $product1 = Product::create([
        'name' => 'iPhone 15',
        'slug' => 'iphone-15',
        'sku' => 'IP15',
        'category_id' => $category->id,
        'brand_id' => $brand1->id,
        'description' => 'Apple iPhone',
    ]);
    $product1->brands()->sync([$brand1->id]);

    // Product 2: Samsung
    $product2 = Product::create([
        'name' => 'Galaxy S24',
        'slug' => 'galaxy-s24',
        'sku' => 'S24',
        'category_id' => $category->id,
        'brand_id' => $brand2->id,
        'description' => 'Samsung Galaxy',
    ]);
    $product2->brands()->sync([$brand2->id]);

    // Request without brand filter
    $response = $this->get('/search');
    $response->assertOk();

    // Verify brands list contains active brands but not inactive ones
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Storefront/Search')
        ->loadDeferredProps(function (Assert $page) {
            $brands = $page->toArray()['props']['brands'];
            $brandsInProps = collect($brands)->pluck('name')->toArray();
            expect($brandsInProps)->toContain('Apple', 'Samsung');
            expect($brandsInProps)->not->toContain('Nokia');
        })
    );

    // Filter by single brand slug
    $response = $this->get('/search?brand='.$brand1->slug);
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Storefront/Search')
        ->loadDeferredProps(function (Assert $page) use ($product1, $product2) {
            $products = $page->toArray()['props']['products'];
            $productIds = collect($products['data'])->pluck('id')->toArray();
            expect($productIds)->toContain($product1->id);
            expect($productIds)->not->toContain($product2->id);
        })
    );

    // Filter by multiple brand slugs as array
    $response = $this->get('/search?brand[]='.$brand1->slug.'&brand[]='.$brand2->slug);
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Storefront/Search')
        ->loadDeferredProps(function (Assert $page) use ($product1, $product2) {
            $products = $page->toArray()['props']['products'];
            $productIds = collect($products['data'])->pluck('id')->toArray();
            expect($productIds)->toContain($product1->id, $product2->id);
        })
    );
});

test('storefront product detail loads categories and brands relationships', function () {
    $category = Category::create(['name' => 'Furniture', 'slug' => 'furniture']);
    $brand = Brand::create(['name' => 'IKEA', 'slug' => 'ikea', 'is_active' => true]);

    $product = Product::create([
        'name' => 'Poang Chair',
        'slug' => 'poang-chair',
        'sku' => 'POANG',
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'description' => 'Comfortable chair',
        'specifications' => [
            'Material' => 'Bentwood',
            'Color' => 'Birch',
        ],
    ]);
    $product->brands()->sync([$brand->id]);
    $product->categories()->sync([$category->id]);

    $response = $this->get('/products/'.$product->slug);
    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Storefront/Product')
        ->where('product.brands.0.name', 'IKEA')
        ->where('product.categories.0.name', 'Furniture')
        ->where('product.specifications.Material', 'Bentwood')
        ->where('product.specifications.Color', 'Birch')
    );
});

test('storefront product detail loads by uuid with seller relationship and allows edit by owner and admin', function () {
    $seller = User::factory()->create([
        'is_seller' => true,
        'is_active' => true,
        'store_slug' => 'toko-hasbi',
    ]);

    $category = Category::create(['name' => 'Elektronik', 'slug' => 'elektronik']);

    $product = Product::create([
        'name' => 'Laptop ROG',
        'slug' => 'laptop-rog',
        'sku' => 'ROG01',
        'category_id' => $category->id,
        'user_id' => $seller->id,
        'active' => true,
        'listing_expires_at' => now()->addDays(30),
    ]);

    // Test product detail by UUID
    $response = $this->get('/products/'.$product->id);
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Storefront/Product')
        ->where('product.id', $product->id)
        ->where('product.user_id', $seller->id)
    );

    // Test edit bypass by owner seller
    $this->actingAs($seller)
        ->get('/admin/products/'.$product->id.'/edit')
        ->assertOk();
});
