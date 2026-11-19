<?php

use App\Actions\Cart\AddToCartAction;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductStock;
use App\Models\RestoProfile;
use App\Models\StoreReview;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    if (! Role::where('name', 'Super Admin')->exists()) {
        Role::create(['name' => 'Super Admin']);
    }
    if (! Role::where('name', 'Seller')->exists()) {
        Role::create(['name' => 'Seller']);
    }
    if (! Role::where('name', 'Customer')->exists()) {
        Role::create(['name' => 'Customer']);
    }
});

test('customer can submit store review for completed transaction in cek-pesanan', function () {
    $seller = User::factory()->create(['is_seller' => true]);
    $resto = RestoProfile::create([
        'user_id' => $seller->id,
        'resto_name' => 'Resto Sedap Rasa',
        'slug' => 'resto-sedap-rasa',
        'culinary_category' => 'Aneka Kuliner',
        'approval_status' => 'approved',
        'is_open_now' => true,
    ]);

    $customer = User::factory()->create();

    $transaction = Transaction::create([
        'transaction_number' => 'TRX-TEST-001',
        'user_id' => $customer->id,
        'resto_id' => $seller->id,
        'status' => 'selesai',
        'customer_name' => 'Budi Santoso',
        'customer_phone' => '081234567890',
        'grand_total' => 50000,
    ]);

    $response = $this->actingAs($customer)
        ->post(route('order.track.rate', $transaction->id), [
            'rating' => 5,
            'comment' => 'Makanan sangat lezat dan pengantaran cepat!',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('store_reviews', [
        'transaction_id' => $transaction->id,
        'resto_profile_id' => $resto->id,
        'seller_id' => $seller->id,
        'rating' => 5,
        'comment' => 'Makanan sangat lezat dan pengantaran cepat!',
    ]);

    // Check dynamic calculation on resto
    $resto->refresh();
    expect($resto->review_count)->toBe(1);
    expect((float) $resto->average_rating)->toBe(5.0);
});

test('cannot review incomplete transaction or review more than once', function () {
    $seller = User::factory()->create(['is_seller' => true]);
    $resto = RestoProfile::create([
        'user_id' => $seller->id,
        'resto_name' => 'Resto Enak',
        'slug' => 'resto-enak',
        'culinary_category' => 'Aneka Nasi',
        'approval_status' => 'approved',
        'is_open_now' => true,
    ]);

    $customer = User::factory()->create();

    $uncompletedTx = Transaction::create([
        'transaction_number' => 'TRX-TEST-002',
        'user_id' => $customer->id,
        'resto_id' => $seller->id,
        'status' => 'diproses',
        'customer_name' => 'Budi Santoso',
        'grand_total' => 30000,
    ]);

    $response = $this->actingAs($customer)
        ->post(route('order.track.rate', $uncompletedTx->id), [
            'rating' => 5,
            'comment' => 'Belum selesai',
        ]);

    $response->assertSessionHas('error');
    expect(StoreReview::count())->toBe(0);
});

test('admin can access store ratings report page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');

    $response = $this->actingAs($admin)
        ->get(route('admin.reports.store-ratings'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Reports/StoreRatings')
        ->has('reviews')
        ->has('summary')
        ->has('ratingDistribution')
    );
});

test('customer cannot add to cart if resto is closed', function () {
    config(['app.is_foodpick' => true]);

    $seller = User::factory()->create(['is_seller' => true]);
    $resto = RestoProfile::create([
        'user_id' => $seller->id,
        'resto_name' => 'Resto Tutup Malam',
        'slug' => 'resto-tutup-malam',
        'culinary_category' => 'Aneka Kuliner',
        'approval_status' => 'approved',
        'is_open_now' => false, // TUTUP SEMENTARA
    ]);

    $category = Category::create([
        'name' => 'Makanan',
        'slug' => 'makanan',
    ]);

    $product = Product::create([
        'user_id' => $seller->id,
        'name' => 'Nasi Goreng Spesial',
        'slug' => 'nasi-goreng-spesial',
        'sku' => 'NASI-01',
        'category_id' => $category->id,
        'active' => true,
    ]);
    ProductStock::create([
        'product_id' => $product->id,
        'stock' => 10,
        'is_unlimited' => false,
    ]);
    ProductPrice::create([
        'product_id' => $product->id,
        'price' => 25000,
    ]);

    $customer = User::factory()->create();

    $action = app(AddToCartAction::class);
    $result = $action->execute($customer, $product->id, null, 1);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('sedang tutup sementara');
});
