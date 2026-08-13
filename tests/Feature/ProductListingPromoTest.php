<?php

use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can update price upload promo settings', function () {
    $admin = User::factory()->create();

    $response = $this->actingAs($admin)
        ->post(route('admin.master-data.price-upload.update'), [
            'product_listing_daily_rate' => 1000,
            'product_listing_max_custom_days' => 15,
            'product_listing_custom_daily_rate' => 1000,
            'product_listing_fee_enabled' => true,
            'product_listing_first_upload_free' => true,
            'product_listing_date_promo_enabled' => true,
            'product_listing_date_promo_name' => 'Promo Merdeka 50%',
            'product_listing_date_promo_start' => now()->subDay()->toDateTimeString(),
            'product_listing_date_promo_end' => now()->addDays(7)->toDateTimeString(),
            'product_listing_date_promo_type' => 'percentage',
            'product_listing_date_promo_value' => 50,
        ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('settings', [
        'key' => 'product_listing_first_upload_free',
        'value' => '1',
    ]);
    $this->assertDatabaseHas('settings', [
        'key' => 'product_listing_date_promo_enabled',
        'value' => '1',
    ]);
    $this->assertDatabaseHas('settings', [
        'key' => 'product_listing_date_promo_name',
        'value' => 'Promo Merdeka 50%',
    ]);
});

test('first product upload free promo auto activates listing when fee is 0', function () {
    config(['app.is_seller' => true]);

    Setting::create(['key' => 'product_listing_fee_enabled', 'value' => '1']);
    Setting::create(['key' => 'product_listing_first_upload_free', 'value' => '1']);

    $seller = User::factory()->create(['is_seller' => true]);
    $product = Product::create([
        'user_id' => $seller->id,
        'name' => 'Sepatu Promo Test',
        'slug' => 'sepatu-promo-test',
        'sku' => 'PROMO-001',
        'active' => false,
    ]);

    $response = $this->actingAs($seller)
        ->post(route('admin.products.get-qris-listing', $product->id), [
            'listing_duration_type' => 15,
        ]);

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'free_auto_activated' => true,
        'status' => 'paid',
    ]);

    $this->assertDatabaseHas('product_listing_payments', [
        'product_id' => $product->id,
        'user_id' => $seller->id,
        'amount' => 0,
        'status' => 'paid',
        'promo_name' => 'Promo Upload Pertama (Gratis)',
    ]);

    $product->refresh();
    expect($product->active)->toBeTrue();
});
