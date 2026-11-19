<?php

use App\Actions\Cart\AddToCartAction;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Courier;
use App\Models\MenuModifier;
use App\Models\MenuModifierItem;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\RestoProfile;
use App\Models\User;
use App\Services\Checkout\ShippingCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->category = Category::create([
        'name' => 'Kuliner',
        'slug' => 'kuliner',
    ]);

    $this->restoUserA = User::create([
        'name' => 'Resto Bebek Sinjay',
        'email' => 'sinjay@bizmate.id',
        'password' => bcrypt('password'),
    ]);

    $this->restoUserB = User::create([
        'name' => 'Kopi Kenangan Senja',
        'email' => 'kenangan@bizmate.id',
        'password' => bcrypt('password'),
    ]);

    $this->customer = User::create([
        'name' => 'Budi Santoso',
        'email' => 'budi@gmail.com',
        'password' => bcrypt('password'),
    ]);

    $this->productA1 = Product::create([
        'user_id' => $this->restoUserA->id,
        'name' => 'Bebek Goreng Kremes',
        'slug' => 'bebek-goreng-kremes',
        'sku' => 'BEBEK-01',
        'category_id' => $this->category->id,
        'active' => true,
    ]);
    $this->productA1->productPrice()->create(['price' => 35000, 'cost' => 20000]);
    ProductStock::create(['product_id' => $this->productA1->id, 'stock' => 50, 'is_unlimited' => false]);

    $this->productA2 = Product::create([
        'user_id' => $this->restoUserA->id,
        'name' => 'Ayam Geprek Madu',
        'slug' => 'ayam-geprek-madu',
        'sku' => 'AYAM-01',
        'category_id' => $this->category->id,
        'active' => true,
    ]);
    $this->productA2->productPrice()->create(['price' => 25000, 'cost' => 15000]);
    ProductStock::create(['product_id' => $this->productA2->id, 'stock' => 50, 'is_unlimited' => false]);

    $this->productB1 = Product::create([
        'user_id' => $this->restoUserB->id,
        'name' => 'Kopi Susu Gula Aren',
        'slug' => 'kopi-susu-gula-aren',
        'sku' => 'KOPI-01',
        'category_id' => $this->category->id,
        'active' => true,
    ]);
    $this->productB1->productPrice()->create(['price' => 18000, 'cost' => 8000]);
    ProductStock::create(['product_id' => $this->productB1->id, 'stock' => 50, 'is_unlimited' => false]);
});

test('non-foodpick mode allows items from different sellers in cart without reset', function () {
    config(['app.is_foodpick' => false]);
    $action = app(AddToCartAction::class);

    $res1 = $action->execute($this->customer, $this->productA1->id, null, 1);
    expect($res1['success'])->toBeTrue()
        ->and($res1['cart_was_reset'])->toBeFalse();

    $res2 = $action->execute($this->customer, $this->productB1->id, null, 1);
    expect($res2['success'])->toBeTrue()
        ->and($res2['cart_was_reset'])->toBeFalse();

    $cartCount = CartItem::where('user_id', $this->customer->id)->count();
    expect($cartCount)->toBe(2);
});

test('foodpick mode keeps cart items when adding from the same resto', function () {
    config(['app.is_foodpick' => true]);
    $action = app(AddToCartAction::class);

    $res1 = $action->execute($this->customer, $this->productA1->id, null, 1);
    expect($res1['success'])->toBeTrue()
        ->and($res1['cart_was_reset'])->toBeFalse();

    $res2 = $action->execute($this->customer, $this->productA2->id, null, 1);
    expect($res2['success'])->toBeTrue()
        ->and($res2['cart_was_reset'])->toBeFalse();

    $items = CartItem::where('user_id', $this->customer->id)->get();
    expect($items)->toHaveCount(2);
});

test('foodpick mode automatically resets cart when adding from a different resto', function () {
    config(['app.is_foodpick' => true]);
    $action = app(AddToCartAction::class);

    // 1. Add item from Resto A
    $action->execute($this->customer, $this->productA1->id, null, 2);
    expect(CartItem::where('user_id', $this->customer->id)->count())->toBe(1);

    // 2. Add item from Resto B -> Must trigger auto-reset!
    $res = $action->execute($this->customer, $this->productB1->id, null, 1);

    expect($res['success'])->toBeTrue()
        ->and($res['cart_was_reset'])->toBeTrue();

    // 3. Verify only Resto B's item is in the cart
    $items = CartItem::where('user_id', $this->customer->id)->get();
    expect($items)->toHaveCount(1)
        ->and($items->first()->product_id)->toBe($this->productB1->id)
        ->and($items->first()->quantity)->toBe(1);
});

test('foodpick mode automatically resets guest cart session when switching resto', function () {
    config(['app.is_foodpick' => true]);
    $action = app(AddToCartAction::class);

    // 1. Guest adds item from Resto A
    $res1 = $action->execute(null, $this->productA1->id, null, 1);
    expect($res1['success'])->toBeTrue()
        ->and($res1['cart_was_reset'])->toBeFalse();
    expect(session('guest_cart'))->toHaveCount(1);

    // 2. Guest adds item from Resto B -> Auto-reset!
    $res2 = $action->execute(null, $this->productB1->id, null, 2);
    expect($res2['success'])->toBeTrue()
        ->and($res2['cart_was_reset'])->toBeTrue();

    $guestCart = session('guest_cart');
    expect($guestCart)->toHaveCount(1);
    $firstItem = array_values($guestCart)[0];
    expect($firstItem['product_id'])->toBe($this->productB1->id)
        ->and($firstItem['quantity'])->toBe(2);
});

test('foodpick resto profiles and menu modifiers relationships work properly', function () {
    $resto = RestoProfile::create([
        'user_id' => $this->restoUserA->id,
        'resto_name' => 'Bebek Sinjay Madura',
        'slug' => 'bebek-sinjay-madura',
        'culinary_category' => 'Aneka Bebek & Ayam',
        'address' => 'Jl. Soekarno Hatta No. 10',
        'latitude' => -7.98261450,
        'longitude' => 112.63078920,
        'operating_hours' => ['senin' => ['open' => '09:00', 'close' => '21:00']],
        'avg_prep_time_minutes' => 20,
        'allow_pickup' => true,
        'allow_delivery' => true,
        'is_open_now' => true,
    ]);

    expect($resto->resto_name)->toBe('Bebek Sinjay Madura')
        ->and($resto->user->id)->toBe($this->restoUserA->id)
        ->and($this->restoUserA->restoProfile->resto_name)->toBe('Bebek Sinjay Madura');

    $modifier = MenuModifier::create([
        'product_id' => $this->productA1->id,
        'group_name' => 'Level Pedas',
        'is_required' => true,
        'is_multiple' => false,
    ]);

    $item1 = MenuModifierItem::create([
        'menu_modifier_id' => $modifier->id,
        'name' => 'Level 1 - Sedang',
        'additional_price' => 0,
        'is_default' => true,
    ]);

    $item2 = MenuModifierItem::create([
        'menu_modifier_id' => $modifier->id,
        'name' => 'Level 3 - Ekstra Pedas',
        'additional_price' => 3000,
        'is_default' => false,
    ]);

    expect($modifier->items)->toHaveCount(2)
        ->and($this->productA1->menuModifiers)->toHaveCount(1)
        ->and($this->productA1->menuModifiers->first()->items->first()->name)->toBe('Level 1 - Sedang');
});

test('checkout redirects with error if cart somehow contains multiple restos in foodpick mode', function () {
    config(['app.is_foodpick' => true]);

    // Forcefully insert two items from different restos
    CartItem::create([
        'user_id' => $this->customer->id,
        'product_id' => $this->productA1->id,
        'quantity' => 1,
        'is_checked' => true,
    ]);
    CartItem::create([
        'user_id' => $this->customer->id,
        'product_id' => $this->productB1->id,
        'quantity' => 1,
        'is_checked' => true,
    ]);

    $response = $this->actingAs($this->customer)->get(route('checkout.index'));
    $response->assertRedirect(route('cart.index'));
    $response->assertSessionHas('error', 'Pesanan makanan hanya boleh berasal dari 1 Resto dalam satu transaksi.');
});

test('checkout page correctly passes the 6 foodpick environment flags and hides expedition', function () {
    config([
        'app.is_foodpick' => true,
        'app.foodpick' => [
            'allow_store_courier' => true,
            'allow_self_pickup' => true,
            'hide_expedition' => true,
            'default_prep_minutes' => 20,
            'slot_interval_minutes' => 15,
        ],
    ]);

    // Create a standard expedition courier
    Courier::create([
        'name' => 'JNE Express',
        'code' => 'jne',
        'is_active' => true,
    ]);

    // Add item from single resto
    CartItem::create([
        'user_id' => $this->customer->id,
        'product_id' => $this->productA1->id,
        'quantity' => 1,
        'is_checked' => true,
    ]);

    $response = $this->actingAs($this->customer)->get(route('checkout.index'));
    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Storefront/Checkout')
        ->where('isFoodPick', true)
        ->where('isStoreCourierEnabled', true)
        ->where('isSelfPickupEnabled', true)
        ->where('foodpick.allow_store_courier', true)
        ->where('foodpick.allow_self_pickup', true)
        ->where('foodpick.hide_expedition', true)
        ->where('foodpick.default_prep_minutes', 20)
        ->where('foodpick.slot_interval_minutes', 15)
        ->where('couriers', []) // Expedition couriers hidden!
    );
});

test('shipping calculation service guards against regular expedition when foodpick hide expedition is active', function () {
    config([
        'app.is_foodpick' => true,
        'app.foodpick' => [
            'allow_store_courier' => true,
            'allow_self_pickup' => true,
            'hide_expedition' => true,
            'default_prep_minutes' => 20,
            'slot_interval_minutes' => 15,
        ],
    ]);

    $service = app(ShippingCalculationService::class);

    // Regular expedition should be rejected
    $expeditionResponse = $service->calculate(['courier' => 'jne']);
    expect($expeditionResponse->getStatusCode())->toBe(422)
        ->and($expeditionResponse->getData(true)['error'])->toContain('Ekspedisi logistik reguler dinonaktifkan');

    // Self pickup should be allowed
    $selfPickupResponse = $service->calculate(['courier' => 'self_pickup']);
    expect($selfPickupResponse->getStatusCode())->toBe(200)
        ->and($selfPickupResponse->getData(true)['results'][0]['code'])->toBe('self_pickup');
});
