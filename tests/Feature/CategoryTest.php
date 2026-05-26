<?php

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('user can access category page by slug', function () {
    $category = Category::create([
        'name' => 'Elektronik',
        'slug' => 'elektronik',
        'icon' => 'ti-cpu',
    ]);

    $product = Product::create([
        'name' => 'Laptop ASUS',
        'slug' => 'laptop-asus',
        'sku' => 'LAP-ASUS',
        'category_id' => $category->id,
        'summary' => 'Summary',
        'description' => 'Description',
        'active' => true,
    ]);

    $response = $this->get("/category/{$category->slug}");

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Storefront/Category')
        ->where('category.name', 'Elektronik')
        ->has('products.data', 1)
        ->where('products.data.0.name', 'Laptop ASUS')
    );
});

test('user can access category page by id', function () {
    $category = Category::create([
        'name' => 'Pakaian',
        'slug' => 'pakaian',
        'icon' => 'ti-shirt',
    ]);

    $product = Product::create([
        'name' => 'Kemeja Batik',
        'slug' => 'kemeja-batik',
        'sku' => 'BATIK-KEM',
        'category_id' => $category->id,
        'summary' => 'Summary',
        'description' => 'Description',
        'active' => true,
    ]);

    $response = $this->get("/category/{$category->id}");

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Storefront/Category')
        ->where('category.name', 'Pakaian')
        ->has('products.data', 1)
        ->where('products.data.0.name', 'Kemeja Batik')
    );
});

test('category page filters products by search query and prices', function () {
    $category = Category::create([
        'name' => 'Makanan',
        'slug' => 'makanan',
        'icon' => 'ti-tools',
    ]);

    $product1 = Product::create([
        'name' => 'Bakso Sapi',
        'slug' => 'bakso-sapi',
        'sku' => 'BAKSO-1',
        'category_id' => $category->id,
        'summary' => 'Summary',
        'description' => 'Description',
        'active' => true,
    ]);

    $product2 = Product::create([
        'name' => 'Nasi Goreng',
        'slug' => 'nasi-goreng',
        'sku' => 'NASGOR-2',
        'category_id' => $category->id,
        'summary' => 'Summary',
        'description' => 'Description',
        'active' => true,
    ]);

    $product1->productPrice()->create(['price' => 15000]);
    $product2->productPrice()->create(['price' => 25000]);

    // Test keyword search
    $responseSearch = $this->get("/category/{$category->slug}?q=Bakso");
    $responseSearch->assertOk();
    $responseSearch->assertInertia(fn (Assert $page) => $page
        ->component('Storefront/Category')
        ->has('products.data', 1)
        ->where('products.data.0.name', 'Bakso Sapi')
    );

    // Test price range filter
    $responsePrice = $this->get("/category/{$category->slug}?min_price=20000");
    $responsePrice->assertOk();
    $responsePrice->assertInertia(fn (Assert $page) => $page
        ->component('Storefront/Category')
        ->has('products.data', 1)
        ->where('products.data.0.name', 'Nasi Goreng')
    );
});
