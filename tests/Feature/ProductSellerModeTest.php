<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('product create and edit pages expose seller and variant configuration', function () {
    $user = User::factory()->create();
    $category = Category::create([
        'name' => 'Elektronik',
        'slug' => 'elektronik',
    ]);
    $product = Product::create([
        'name' => 'Produk Uji',
        'slug' => 'produk-uji',
        'sku' => 'PRD-TEST-001',
        'category_id' => $category->id,
        'description' => 'Produk untuk pengujian mode seller.',
    ]);

    config([
        'app.is_seller' => false,
        'app.enable_product_variants' => false,
    ]);

    $this->actingAs($user)
        ->get(route('admin.products.create'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Products/Create')
            ->where('isSellerMode', false)
            ->where('enableProductVariants', false));

    $this->actingAs($user)
        ->get(route('admin.products.edit', $product))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Products/Edit')
            ->where('isSellerMode', false)
            ->where('enableProductVariants', false));

    config([
        'app.is_seller' => true,
        'app.enable_product_variants' => true,
    ]);

    $this->actingAs($user)
        ->get(route('admin.products.create'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Products/Create')
            ->where('isSellerMode', true)
            ->where('enableProductVariants', true));

    $this->actingAs($user)
        ->get(route('admin.products.edit', $product))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Products/Edit')
            ->where('isSellerMode', true)
            ->where('enableProductVariants', true));
});
