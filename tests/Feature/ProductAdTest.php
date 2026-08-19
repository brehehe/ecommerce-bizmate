<?php

use App\Models\Product;
use App\Models\ProductAd;
use App\Models\SellerAdWallet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('seller can view ad index page with wallet and kpis', function () {
    $seller = User::factory()->create(['is_seller' => true]);
    $wallet = SellerAdWallet::getOrCreateForUser($seller->id);
    $wallet->update(['balance' => 100000]);

    $product = Product::create([
        'user_id' => $seller->id,
        'name' => 'Raket Padel Pro',
        'slug' => 'raket-padel-pro',
        'sku' => 'PADEL-001',
        'active' => true,
    ]);

    ProductAd::create([
        'user_id' => $seller->id,
        'product_id' => $product->id,
        'ad_type' => 'cpc',
        'bid_per_click' => 500,
        'daily_budget' => 20000,
        'status' => 'active',
    ]);

    $response = $this->actingAs($seller)->get(route('admin.ads.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Promote/Index')
        ->has('wallet')
        ->has('kpi')
        ->has('campaigns', 1)
        ->has('availableProducts')
    );
});

test('seller can create and update a product ad campaign', function () {
    $seller = User::factory()->create(['is_seller' => true]);
    $wallet = SellerAdWallet::getOrCreateForUser($seller->id);
    $wallet->update(['balance' => 50000]);

    $product = Product::create([
        'user_id' => $seller->id,
        'name' => 'Bola Padel Tour',
        'slug' => 'bola-padel-tour',
        'sku' => 'BOLA-001',
        'active' => true,
    ]);

    $response = $this->actingAs($seller)->post(route('admin.ads.store'), [
        'product_id' => $product->id,
        'ad_type' => 'cpc',
        'bid_per_click' => 400,
        'daily_budget' => 15000,
        'show_badge' => true,
        'placements' => ['home', 'search'],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('product_ads', [
        'user_id' => $seller->id,
        'product_id' => $product->id,
        'bid_per_click' => 400,
        'daily_budget' => 15000,
        'status' => 'active',
        'show_badge' => true,
    ]);

    $ad = ProductAd::where('product_id', $product->id)->first();
    expect($ad->placements)->toEqual(['home', 'search']);

    // Toggle pause status and update placements / show_badge
    $this->actingAs($seller)->put(route('admin.ads.update', $ad->id), [
        'status' => 'paused',
        'bid_per_click' => 600,
        'daily_budget' => 25000,
        'show_badge' => false,
        'placements' => ['search', 'category'],
    ])->assertRedirect();

    $this->assertDatabaseHas('product_ads', [
        'id' => $ad->id,
        'status' => 'paused',
        'bid_per_click' => 600,
        'daily_budget' => 25000,
        'show_badge' => false,
    ]);

    expect($ad->fresh()->placements)->toEqual(['search', 'category']);
});

test('seller can request top up and confirm top up status', function () {
    $seller = User::factory()->create(['is_seller' => true]);

    $response = $this->actingAs($seller)->postJson(route('admin.ads.topup'), [
        'amount' => 50000,
    ]);

    $response->assertOk();
    $response->assertJsonStructure([
        'success',
        'order_id',
        'amount',
        'qr_image',
    ]);

    $orderId = $response->json('order_id');
    $this->assertDatabaseHas('seller_ad_transactions', [
        'order_id' => $orderId,
        'user_id' => $seller->id,
        'amount' => 50000,
        'status' => 'pending',
    ]);

    // Check & auto confirm in simulation mode
    $checkResponse = $this->actingAs($seller)->postJson(route('admin.ads.topup.check-status'), [
        'order_id' => $orderId,
        'auto_confirm' => true,
    ]);

    $checkResponse->assertOk();
    $checkResponse->assertJson([
        'success' => true,
        'status' => 'paid',
    ]);

    $this->assertDatabaseHas('seller_ad_wallets', [
        'user_id' => $seller->id,
        'balance' => 50000,
    ]);
});

test('ad click tracking deducts wallet balance and records click log', function () {
    $seller = User::factory()->create(['is_seller' => true]);
    $wallet = SellerAdWallet::getOrCreateForUser($seller->id);
    $wallet->update(['balance' => 50000]);

    $product = Product::create([
        'user_id' => $seller->id,
        'name' => 'Grip Padel Overgrip',
        'slug' => 'grip-padel-overgrip',
        'sku' => 'GRIP-001',
        'active' => true,
    ]);

    $ad = ProductAd::create([
        'user_id' => $seller->id,
        'product_id' => $product->id,
        'ad_type' => 'cpc',
        'bid_per_click' => 300,
        'daily_budget' => 10000,
        'status' => 'active',
    ]);

    $response = $this->postJson(route('api.ads.track-click'), [
        'product_id' => $product->id,
    ]);

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'billed' => true,
        'cost' => 300,
    ]);

    // Wallet balance should now be 50,000 - 300 = 49,700
    expect($wallet->fresh()->balance)->toEqual(49700.0);
    expect($ad->fresh()->clicks_count)->toBe(1);
    expect($ad->fresh()->spent_today)->toEqual(300.0);

    $this->assertDatabaseHas('product_ad_clicks', [
        'product_ad_id' => $ad->id,
        'product_id' => $product->id,
        'cost' => 300,
    ]);

    $this->assertDatabaseHas('seller_ad_transactions', [
        'user_id' => $seller->id,
        'type' => 'click_cost',
        'amount' => 300,
        'balance_after' => 49700,
    ]);
});

test('anti click fraud prevents multiple cpc deductions from same IP within 60 minutes', function () {
    $seller = User::factory()->create(['is_seller' => true]);
    $wallet = SellerAdWallet::getOrCreateForUser($seller->id);
    $wallet->update(['balance' => 50000]);

    $product = Product::create([
        'user_id' => $seller->id,
        'name' => 'Kaos Padel Team',
        'slug' => 'kaos-padel-team',
        'sku' => 'KAOS-001',
        'active' => true,
    ]);

    $ad = ProductAd::create([
        'user_id' => $seller->id,
        'product_id' => $product->id,
        'ad_type' => 'cpc',
        'bid_per_click' => 500,
        'daily_budget' => 10000,
        'status' => 'active',
    ]);

    // First click - billed
    $res1 = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.100'])
        ->postJson(route('api.ads.track-click'), ['product_id' => $product->id]);
    $res1->assertJson(['billed' => true, 'cost' => 500]);
    expect($wallet->fresh()->balance)->toEqual(49500.0);

    // Second click from same IP within 60 min - free click, not billed again
    $res2 = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.100'])
        ->postJson(route('api.ads.track-click'), ['product_id' => $product->id]);
    $res2->assertJson(['billed' => false, 'cost' => 0]);
    expect($wallet->fresh()->balance)->toEqual(49500.0);
    expect($ad->fresh()->clicks_count)->toBe(2);
});
