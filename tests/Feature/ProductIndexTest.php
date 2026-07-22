<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->category = Category::create([
        'name' => 'Electronic',
        'slug' => 'electronic',
    ]);

    // Create 15 products
    for ($i = 1; $i <= 15; $i++) {
        $product = Product::create([
            'name' => "Product $i",
            'slug' => "product-$i",
            'sku' => "SKU-$i",
            'category_id' => $this->category->id,
            'description' => "Description for Product $i",
        ]);
        $product->productPrice()->create(['price' => 1000 * $i]);
        $product->productStock()->create(['stock' => $i]);
    }
});

test('admin can view products index with default pagination (10 per page)', function () {
    $response = $this->actingAs($this->user)->get(route('admin.products.index'));

    $response->assertOk();

    $props = $response->original->getData()['page']['props'];
    expect($props['products']['data'])->toHaveCount(10);
    expect($props['products']['total'])->toBe(15);
    expect($props['filters']['per_page'])->toBe('10');
});

test('admin can view products index with custom per_page pagination', function () {
    $response = $this->actingAs($this->user)->get(route('admin.products.index', ['per_page' => 5]));

    $response->assertOk();

    $props = $response->original->getData()['page']['props'];
    expect($props['products']['data'])->toHaveCount(5);
    expect($props['products']['total'])->toBe(15);
    expect($props['filters']['per_page'])->toBe('5');
});

test('admin can view all products on a single page by setting per_page to all', function () {
    $response = $this->actingAs($this->user)->get(route('admin.products.index', ['per_page' => 'all']));

    $response->assertOk();

    $props = $response->original->getData()['page']['props'];
    expect($props['products']['data'])->toHaveCount(15);
    expect($props['products']['total'])->toBe(15);
    expect($props['filters']['per_page'])->toBe('all');
});
