<?php

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('buy now stores item in session and redirects to checkout without modifying cart database', function () {
    $user = User::factory()->create();
    $category = Category::create([
        'name' => 'Elektronik',
        'slug' => 'elektronik',
    ]);

    // Product 1 (already in cart)
    $product1 = Product::create([
        'name' => 'Laptop Test',
        'slug' => 'laptop-test',
        'sku' => 'LAP-TEST',
        'category_id' => $category->id,
        'active' => true,
    ]);
    $product1->productPrice()->create(['price' => 5000000, 'cost' => 3000000]);
    ProductStock::create([
        'product_id' => $product1->id,
        'stock' => 10,
        'is_unlimited' => false,
    ]);

    // Add product 1 to cart as checked
    $cartItem1 = CartItem::create([
        'user_id' => $user->id,
        'product_id' => $product1->id,
        'quantity' => 2,
        'is_checked' => true,
    ]);

    // Product 2 (the one we will Buy Now)
    $product2 = Product::create([
        'name' => 'Mouse Test',
        'slug' => 'mouse-test',
        'sku' => 'MSE-TEST',
        'category_id' => $category->id,
        'active' => true,
    ]);
    $product2->productPrice()->create(['price' => 100000, 'cost' => 50000]);
    ProductStock::create([
        'product_id' => $product2->id,
        'stock' => 5,
        'is_unlimited' => false,
    ]);

    // Request Buy Now on product 2
    $response = $this->actingAs($user)->post('/cart', [
        'product_id' => $product2->id,
        'quantity' => 1,
        'buy_now' => true,
    ]);

    // Assert redirect to checkout.index
    $response->assertRedirect(route('checkout.index'));

    // Assert that the first cart item is still checked
    $cartItem1->refresh();
    expect($cartItem1->is_checked)->toBeTrue();

    // Assert that no new cart item was created in the database for product 2
    $cartItem2Exists = CartItem::where('user_id', $user->id)
        ->where('product_id', $product2->id)
        ->exists();
    expect($cartItem2Exists)->toBeFalse();

    // Assert that the session has the buy now item
    $response->assertSessionHas('buy_now_item', [
        'product_id' => $product2->id,
        'product_variant_id' => null,
        'quantity' => 1,
    ]);
});

test('cart and checkout pages provide non-zero unit_price and subtotal for products', function () {
    $user = User::factory()->create();
    $category = Category::create([
        'name' => 'Olahraga',
        'slug' => 'olahraga',
    ]);

    $product = Product::create([
        'name' => 'Raket Yonex Astrox',
        'slug' => 'raket-yonex-astrox',
        'sku' => 'RAK-ASTROX',
        'category_id' => $category->id,
        'active' => true,
    ]);
    $product->productPrice()->create(['price' => 2450000, 'cost' => 1800000]);
    ProductStock::create([
        'product_id' => $product->id,
        'stock' => 10,
        'is_unlimited' => false,
    ]);

    CartItem::create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'is_checked' => true,
    ]);

    // Test Cart Index
    $cartResponse = $this->actingAs($user)->get(route('cart.index'));
    $cartResponse->assertOk();
    $cartResponse->assertInertia(fn (Assert $page) => $page
        ->component('Storefront/Cart')
        ->has('cartItems.0', fn (Assert $item) => $item
            ->where('unit_price', 2450000)
            ->where('subtotal', 4900000)
            ->etc()
        )
    );

    // Test Checkout Index
    $checkoutResponse = $this->actingAs($user)->get(route('checkout.index'));
    $checkoutResponse->assertOk();
    $checkoutResponse->assertInertia(fn (Assert $page) => $page
        ->component('Storefront/Checkout')
        ->has('cartItems.0', fn (Assert $item) => $item
            ->where('unit_price', 2450000)
            ->where('subtotal', 4900000)
            ->etc()
        )
    );
});
