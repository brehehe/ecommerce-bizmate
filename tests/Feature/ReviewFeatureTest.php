<?php

use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Customer', 'guard_name' => 'web']);

    $this->user = User::factory()->create();
    $this->user->assignRole('Customer');

    $this->product = Product::create([
        'name' => 'Test Produk',
        'slug' => 'test-produk',
        'sku' => 'TEST-001',
        'summary' => 'Summary',
        'description' => 'Description',
        'active' => true,
    ]);

    $this->transaction = Transaction::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'selesai',
    ]);
});

test('product review stores is_anonymous flag', function () {
    $this->actingAs($this->user);

    $response = $this->post("/transactions/{$this->transaction->id}/review", [
        'product_id' => $this->product->id,
        'rating' => 5,
        'comment' => 'Produk bagus',
        'is_anonymous' => true,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('product_reviews', [
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'rating' => 5,
        'is_anonymous' => true,
    ]);
});

test('product review stores is_anonymous false by default', function () {
    $this->actingAs($this->user);

    $this->post("/transactions/{$this->transaction->id}/review", [
        'product_id' => $this->product->id,
        'rating' => 4,
        'comment' => 'Produk lumayan',
    ]);

    $this->assertDatabaseHas('product_reviews', [
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'is_anonymous' => false,
    ]);
});

test('authenticated user can report a review', function () {
    $this->actingAs($this->user);

    /** @var ProductReview $review */
    $review = ProductReview::create([
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'transaction_id' => $this->transaction->id,
        'rating' => 1,
        'comment' => 'Barang palsu!',
        'is_anonymous' => false,
    ]);

    $response = $this->post("/reviews/{$review->id}/report", [
        'reason' => 'Informasi palsu',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('product_reviews', [
        'id' => $review->id,
        'is_reported' => true,
        'report_reason' => 'Informasi palsu',
    ]);
});

test('cannot report same review twice', function () {
    $this->actingAs($this->user);

    /** @var ProductReview $review */
    $review = ProductReview::create([
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'transaction_id' => $this->transaction->id,
        'rating' => 2,
        'is_anonymous' => false,
        'is_reported' => true,
        'report_reason' => 'Spam',
    ]);

    $response = $this->post("/reviews/{$review->id}/report", [
        'reason' => 'Laporan kedua',
    ]);

    $response->assertSessionHas('error');

    // report_reason should still be the original
    $this->assertDatabaseHas('product_reviews', [
        'id' => $review->id,
        'report_reason' => 'Spam',
    ]);
});

test('admin can access reviews report page', function () {
    Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);

    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');

    $this->actingAs($admin);

    $response = $this->get('/admin/reports/reviews');
    $response->assertStatus(200);
});
