<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
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

    $pageProps = $response->original->getData()['page']['props'];
    $productInProps = $pageProps['product'];

    // Verify relationships loaded
    expect($productInProps['brands'])->toHaveCount(1);
    expect($productInProps['brands'][0]['name'])->toBe('IKEA');
    expect($productInProps['categories'])->toHaveCount(1);
    expect($productInProps['categories'][0]['name'])->toBe('Furniture');

    // Verify specifications are correct
    expect($productInProps['specifications'])->toBeArray()
        ->toHaveKey('Material', 'Bentwood')
        ->toHaveKey('Color', 'Birch');
});
