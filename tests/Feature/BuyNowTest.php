<?php

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('buy now temporarily unchecks other cart items and redirects to checkout', function () {
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

    // Assert that the first cart item is now unchecked
    $cartItem1->refresh();
    expect($cartItem1->is_checked)->toBeFalse();

    // Assert that the new cart item exists and is checked
    $cartItem2 = CartItem::where('user_id', $user->id)
        ->where('product_id', $product2->id)
        ->first();

    expect($cartItem2)->not->toBeNull();
    expect($cartItem2->is_checked)->toBeTrue();
    expect($cartItem2->quantity)->toBe(1);
});
