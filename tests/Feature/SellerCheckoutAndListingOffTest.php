<?php

use App\Models\Category;
use App\Models\MembershipLevel;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.membership_enabled' => false]);

    if (! Role::where('name', 'Seller')->exists()) {
        Role::create(['name' => 'Seller']);
    }
    if (! Role::where('name', 'Admin')->exists()) {
        Role::create(['name' => 'Admin']);
    }

    MembershipLevel::create([
        'name' => 'Bronze',
        'slug' => 'bronze',
        'min_spend' => 0,
        'min_transactions' => 0,
        'order' => 1,
        'is_active' => true,
    ]);

    $this->category = Category::create([
        'name' => 'Badminton',
        'slug' => 'badminton',
    ]);

    $this->seller = User::create([
        'name' => 'Sport Seller',
        'email' => 'seller@sport.com',
        'password' => bcrypt('password'),
        'is_seller' => true,
        'store_name' => 'Sport Shop',
        'store_slug' => 'sport-shop',
    ]);
    $this->seller->assignRole('Seller');
});

test('show_checkout_settings prop is passed to inertia when is_seller is true', function () {
    config([
        'app.is_seller' => true,
        'app.show_checkout_settings' => true,
    ]);

    $product = Product::create([
        'user_id' => $this->seller->id,
        'category_id' => $this->category->id,
        'name' => 'Raket Badminton Li-Ning',
        'sku' => 'SKU-LINING-900',
        'slug' => 'raket-badminton-li-ning',
        'price' => 1500000,
        'active' => true,
        'type' => 'physical',
    ]);
    $product->productPrice()->create(['price' => 1500000]);
    $product->productStock()->create(['stock' => 10, 'is_unlimited' => false]);

    $response = $this->get('/products/'.$product->id);

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Storefront/Product')
            ->where('app_config.is_seller_enabled', true)
            ->where('app_config.show_checkout_settings', true)
            ->where('settings.show_checkout_settings', true)
        );
});

test('show_checkout_settings can be toggled false via config', function () {
    config([
        'app.is_seller' => true,
        'app.show_checkout_settings' => false,
    ]);

    $product = Product::create([
        'user_id' => $this->seller->id,
        'category_id' => $this->category->id,
        'name' => 'Raket Badminton Yonex',
        'sku' => 'SKU-YONEX-01',
        'slug' => 'raket-badminton-yonex',
        'price' => 1200000,
        'active' => true,
        'type' => 'physical',
    ]);
    $product->productPrice()->create(['price' => 1200000]);
    $product->productStock()->create(['stock' => 5, 'is_unlimited' => false]);

    $response = $this->get('/products/'.$product->id);

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Storefront/Product')
            ->where('app_config.show_checkout_settings', false)
            ->where('settings.show_checkout_settings', false)
        );
});

test('products appear directly without paying listing fees when IS_LISTING_OFF is true or default', function () {
    config([
        'app.is_seller' => true,
        'app.is_listing_off' => true,
    ]);

    expect(Product::isListingFeeEnabled())->toBeFalse();

    $product = Product::create([
        'user_id' => $this->seller->id,
        'category_id' => $this->category->id,
        'name' => 'Sepatu Badminton Lining',
        'sku' => 'SKU-SEPATU-01',
        'slug' => 'sepatu-badminton-lining',
        'price' => 750000,
        'active' => true,
        'type' => 'physical',
        'listing_expires_at' => null, // No listing payment made
    ]);
    $product->productPrice()->create(['price' => 750000]);
    $product->productStock()->create(['stock' => 5, 'is_unlimited' => false]);

    // Product is not expired
    expect($product->isListingExpired())->toBeFalse();

    // Product is included in activeAndNotExpired query
    $activeProducts = Product::activeAndNotExpired()->pluck('id');
    expect($activeProducts)->toContain($product->id);

    // Product detail page loads with 200 OK without 404
    $response = $this->get('/products/'.$product->id);
    $response->assertOk();
});

test('admin products listing does not mark products as expired or require posting fee when fee is off', function () {
    config([
        'app.is_seller' => true,
        'app.is_listing_off' => true,
    ]);

    $product = Product::create([
        'user_id' => $this->seller->id,
        'category_id' => $this->category->id,
        'name' => 'Tas Raket Badminton',
        'sku' => 'SKU-TAS-01',
        'slug' => 'tas-raket-badminton',
        'price' => 300000,
        'active' => true,
        'type' => 'physical',
        'listing_expires_at' => null,
    ]);
    $product->productPrice()->create(['price' => 300000]);
    $product->productStock()->create(['stock' => 5, 'is_unlimited' => false]);

    $admin = User::create([
        'name' => 'Super Admin',
        'email' => 'admin@sport.com',
        'password' => bcrypt('password'),
        'is_seller' => false,
    ]);
    $admin->assignRole('Admin');

    $response = $this->actingAs($admin)->get(route('admin.products.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Products/Index')
            ->where('listingPricing.fee_enabled', false)
        );
});
