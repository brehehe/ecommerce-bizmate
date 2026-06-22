<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('admin can bulk delete products', function () {
    $category = Category::create([
        'name' => 'Furniture',
        'slug' => 'furniture',
    ]);

    $product1 = Product::create([
        'name' => 'Sofa Mewah',
        'slug' => 'sofa-mewah',
        'sku' => 'SF-MWH-01',
        'category_id' => $category->id,
        'description' => 'Deskripsi sofa.',
    ]);
    $product1->productPrice()->create(['price' => 5000000]);
    $product1->productStock()->create(['stock' => 10]);

    $product2 = Product::create([
        'name' => 'Meja Makan',
        'slug' => 'meja-makan',
        'sku' => 'MJ-MKN-01',
        'category_id' => $category->id,
        'description' => 'Deskripsi meja makan.',
    ]);
    $product2->productPrice()->create(['price' => 2000000]);
    $product2->productStock()->create(['stock' => 5]);

    $product3 = Product::create([
        'name' => 'Kursi Kayu',
        'slug' => 'kursi-kayu',
        'sku' => 'KS-KYU-01',
        'category_id' => $category->id,
        'description' => 'Deskripsi kursi kayu.',
    ]);
    $product3->productPrice()->create(['price' => 500000]);
    $product3->productStock()->create(['stock' => 15]);

    $response = $this->actingAs($this->user)->post(route('admin.products.bulk-delete'), [
        'ids' => [$product1->id, $product2->id],
    ]);

    $response->assertRedirect();

    // product1 and product2 should be deleted from DB
    $this->assertDatabaseMissing('products', ['id' => $product1->id]);
    $this->assertDatabaseMissing('products', ['id' => $product2->id]);

    // product3 should still exist
    $this->assertDatabaseHas('products', ['id' => $product3->id]);
});
