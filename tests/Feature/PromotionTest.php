<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->category = Category::create([
        'name' => 'Elektronik',
        'slug' => 'elektronik',
        'icon' => 'ti-cpu',
    ]);

    // Create a product to target
    $this->product = Product::create([
        'name' => 'Test Product',
        'slug' => 'test-product',
        'sku' => 'TEST-SKU-1',
        'category_id' => $this->category->id,
        'summary' => 'Summary',
        'description' => 'Description',
        'active' => true,
    ]);
});

test('admin can access promotions index page', function () {
    $response = $this->actingAs($this->user)->get(route('admin.promotions.index'));
    $response->assertOk();
});

test('admin can create promotion', function () {
    $promoData = [
        'name' => 'Diskon Kilat',
        'type' => 'promo_toko',
        'discount_type' => 'percentage',
        'discount_value' => 15,
        'start_time' => now()->toDateTimeString(),
        'end_time' => now()->addDays(5)->toDateTimeString(),
        'is_active' => true,
        'items' => [
            [
                'product_id' => $this->product->id,
                'discount_type' => 'percentage',
                'discount_value' => 15,
                'promo_stock' => 50,
            ],
        ],
    ];

    $response = $this->actingAs($this->user)->post(route('admin.promotions.store'), $promoData);

    $response->assertRedirect(route('admin.promotions.index'));
    $this->assertDatabaseHas('promotions', [
        'name' => 'Diskon Kilat',
        'type' => 'promo_toko',
        'discount_value' => 15.00,
    ]);

    $this->assertDatabaseHas('promotion_items', [
        'product_id' => $this->product->id,
        'discount_value' => 15.00,
        'promo_stock' => 50,
    ]);
});

test('admin can update promotion', function () {
    $promotion = Promotion::create([
        'name' => 'Old Promo',
        'type' => 'voucher_belanja',
        'code' => 'OLDVOUCHER',
        'discount_type' => 'fixed',
        'discount_value' => 20000,
        'start_time' => now(),
        'end_time' => now()->addDays(1),
        'is_active' => true,
    ]);

    $updateData = [
        'name' => 'Updated Promo',
        'type' => 'voucher_belanja',
        'code' => 'NEWVOUCHER',
        'discount_type' => 'fixed',
        'discount_value' => 25000,
        'start_time' => now()->toDateTimeString(),
        'end_time' => now()->addDays(2)->toDateTimeString(),
        'is_active' => false,
    ];

    $response = $this->actingAs($this->user)->put(route('admin.promotions.update', $promotion), $updateData);

    $response->assertRedirect(route('admin.promotions.index'));
    $this->assertDatabaseHas('promotions', [
        'id' => $promotion->id,
        'name' => 'Updated Promo',
        'code' => 'NEWVOUCHER',
        'discount_value' => 25000.00,
        'is_active' => false,
    ]);
});

test('admin can delete promotion', function () {
    $promotion = Promotion::create([
        'name' => 'Promo to Delete',
        'type' => 'flash_sale',
        'start_time' => now(),
        'end_time' => now()->addHours(2),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)->delete(route('admin.promotions.destroy', $promotion));

    $response->assertRedirect();
    $this->assertDatabaseMissing('promotions', [
        'id' => $promotion->id,
    ]);
});

test('admin can toggle promotion active status', function () {
    $promotion = Promotion::create([
        'name' => 'Promo to Toggle',
        'type' => 'special_deals',
        'start_time' => now(),
        'end_time' => now()->addHours(2),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)->post(route('admin.promotions.toggle-active', $promotion));

    $response->assertRedirect();
    $this->assertDatabaseHas('promotions', [
        'id' => $promotion->id,
        'is_active' => false,
    ]);
});

test('admin can create bundling_gift promotion with multiple buy and get items', function () {
    $promoData = [
        'name' => 'Beli 2 Dapat 1',
        'type' => 'bundling_gift',
        'start_time' => now()->toDateTimeString(),
        'end_time' => now()->addDays(5)->toDateTimeString(),
        'is_active' => true,
        'settings' => [
            'bundle' => [
                'buy_items' => [
                    ['product_id' => $this->product->id, 'product_variant_id' => null, 'qty' => 2],
                ],
                'get_items' => [
                    ['product_id' => $this->product->id, 'product_variant_id' => null, 'qty' => 1, 'discount_type' => 'free', 'discount_value' => 0],
                ],
            ],
        ],
    ];

    $response = $this->actingAs($this->user)->post(route('admin.promotions.store'), $promoData);

    $response->assertRedirect(route('admin.promotions.index'));
    $this->assertDatabaseHas('promotions', [
        'name' => 'Beli 2 Dapat 1',
        'type' => 'bundling_gift',
    ]);

    $promo = Promotion::where('name', 'Beli 2 Dapat 1')->first();
    expect($promo->settings['bundle']['buy_items'])->toHaveCount(1);
    expect($promo->settings['bundle']['get_items'])->toHaveCount(1);
});

test('admin can create promo_produk with min_qty', function () {
    $promoData = [
        'name' => 'Promo Produk Beli 3 Lebih Hemat',
        'type' => 'promo_produk',
        'discount_type' => 'percentage',
        'discount_value' => 15,
        'start_time' => now()->toDateTimeString(),
        'end_time' => now()->addDays(5)->toDateTimeString(),
        'is_active' => true,
        'settings' => [
            'min_qty' => 3,
        ],
        'items' => [
            [
                'product_id' => $this->product->id,
                'discount_type' => 'percentage',
                'discount_value' => 15,
                'promo_stock' => 50,
            ],
        ],
    ];

    $response = $this->actingAs($this->user)->post(route('admin.promotions.store'), $promoData);

    $response->assertRedirect(route('admin.promotions.index'));
    $this->assertDatabaseHas('promotions', [
        'name' => 'Promo Produk Beli 3 Lebih Hemat',
        'type' => 'promo_produk',
        'discount_value' => 15.00,
    ]);

    $promo = Promotion::where('name', 'Promo Produk Beli 3 Lebih Hemat')->first();
    expect($promo->settings['min_qty'])->toBe(3);
});

test('admin can create voucher_belanja with can_stack_with_promos setting', function () {
    $promoData = [
        'name' => 'Voucher Non-Stackable 15%',
        'type' => 'voucher_belanja',
        'code' => 'NONSTACK15',
        'discount_type' => 'percentage',
        'discount_value' => 15,
        'min_purchase' => 100000,
        'start_time' => now()->toDateTimeString(),
        'end_time' => now()->addDays(5)->toDateTimeString(),
        'is_active' => true,
        'settings' => [
            'can_stack_with_promos' => false,
        ],
    ];

    $response = $this->actingAs($this->user)->post(route('admin.promotions.store'), $promoData);

    $response->assertRedirect(route('admin.promotions.index'));
    $this->assertDatabaseHas('promotions', [
        'name' => 'Voucher Non-Stackable 15%',
        'type' => 'voucher_belanja',
        'code' => 'NONSTACK15',
    ]);

    $promo = Promotion::where('code', 'NONSTACK15')->first();
    expect($promo->settings['can_stack_with_promos'])->toBe(false);
});
