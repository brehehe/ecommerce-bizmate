<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('admin can access brands index page', function () {
    $response = $this->actingAs($this->user)->get(route('admin.master-data.brands'));
    $response->assertOk();
});

test('admin can create a brand', function () {
    $response = $this->actingAs($this->user)->post(route('admin.master-data.brands.store'), [
        'name' => 'IKEA',
        'is_active' => true,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('brands', [
        'name' => 'IKEA',
        'slug' => 'ikea',
        'is_active' => true,
    ]);
});

test('admin can update a brand', function () {
    $brand = Brand::create([
        'name' => 'ASUS',
        'slug' => 'asus',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)->put(route('admin.master-data.brands.update', $brand), [
        'name' => 'ROG ASUS',
        'is_active' => false,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('brands', [
        'id' => $brand->id,
        'name' => 'ROG ASUS',
        'slug' => 'rog-asus',
        'is_active' => false,
    ]);
});

test('admin can toggle brand active status', function () {
    $brand = Brand::create([
        'name' => 'Xiaomi',
        'slug' => 'xiaomi',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)->post(route('admin.master-data.brands.toggle-active', $brand));

    $response->assertRedirect();
    $this->assertDatabaseHas('brands', [
        'id' => $brand->id,
        'is_active' => false,
    ]);
});

test('admin can delete a brand', function () {
    $brand = Brand::create([
        'name' => 'Uniqlo',
        'slug' => 'uniqlo',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)->delete(route('admin.master-data.brands.destroy', $brand));

    $response->assertRedirect();
    $this->assertSoftDeleted('brands', [
        'id' => $brand->id,
    ]);
});

test('admin can store a product with brand_id and specifications', function () {
    $category = Category::create([
        'name' => 'Furniture',
        'slug' => 'furniture',
    ]);

    $brand = Brand::create([
        'name' => 'Informa',
        'slug' => 'informa',
    ]);

    $response = $this->actingAs($this->user)->post(route('admin.products.store'), [
        'name' => 'Kursi Kerja Ergonomis',
        'sku' => 'CH-ERGO-01',
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'price' => 1500000,
        'description' => 'Deskripsi kursi kerja.',
        'specifications' => [
            'Bahan' => 'Mesh & Kayu',
            'Garansi' => '2 Tahun',
        ],
    ]);

    $response->assertRedirect(route('admin.products.index'));

    $product = Product::where('sku', 'CH-ERGO-01')->first();
    expect($product)->not->toBeNull();
    expect($product->brand_id)->toBe($brand->id);
    expect($product->brand)->toBe('Informa'); // backward-compatible check
    expect($product->specifications)->toBeArray()->toHaveKey('Bahan', 'Mesh & Kayu');
});

test('admin can update a product with brand_id and specifications', function () {
    $category = Category::create([
        'name' => 'Furniture',
        'slug' => 'furniture',
    ]);

    $brand1 = Brand::create(['name' => 'IKEA', 'slug' => 'ikea']);
    $brand2 = Brand::create(['name' => 'Informa', 'slug' => 'informa']);

    $product = Product::create([
        'name' => 'Sofa Mewah',
        'slug' => 'sofa-mewah',
        'sku' => 'SF-MWH-01',
        'category_id' => $category->id,
        'brand_id' => $brand1->id,
        'brand' => 'IKEA',
        'description' => 'Deskripsi sofa.',
        'specifications' => [
            'Bahan' => 'Kulit Asli',
        ],
    ]);
    $product->productPrice()->create(['price' => 5000000]);
    $product->productStock()->create(['stock' => 10]);

    $response = $this->actingAs($this->user)->put(route('admin.products.update', $product), [
        'name' => 'Sofa Mewah Update',
        'sku' => 'SF-MWH-01',
        'category_id' => $category->id,
        'brand_id' => $brand2->id,
        'price' => 5500000,
        'description' => 'Deskripsi sofa update.',
        'specifications' => [
            'Bahan' => 'Beludru Premium',
            'Dimensi' => '200x90 cm',
        ],
    ]);

    $response->assertRedirect(route('admin.products.index'));

    $product->refresh();
    expect($product->brand_id)->toBe($brand2->id);
    expect($product->brand)->toBe('Informa');
    expect($product->specifications)->toBeArray()
        ->toHaveKey('Bahan', 'Beludru Premium')
        ->toHaveKey('Dimensi', '200x90 cm');
});

test('admin can store a product with multiple categories and brands', function () {
    $category1 = Category::create(['name' => 'Category One', 'slug' => 'cat-one']);
    $category2 = Category::create(['name' => 'Category Two', 'slug' => 'cat-two']);

    $brand1 = Brand::create(['name' => 'Brand One', 'slug' => 'brand-one']);
    $brand2 = Brand::create(['name' => 'Brand Two', 'slug' => 'brand-two']);

    $response = $this->actingAs($this->user)->post(route('admin.products.store'), [
        'name' => 'Multitask Product',
        'sku' => 'MULTI-01',
        'category_ids' => [$category1->id, $category2->id],
        'brand_ids' => [$brand1->id, $brand2->id],
        'price' => 200000,
        'description' => 'Multi desc',
    ]);

    $response->assertRedirect(route('admin.products.index'));

    $product = Product::where('sku', 'MULTI-01')->first();
    expect($product)->not->toBeNull();
    // Compatibility fields check (should be the first items)
    expect($product->category_id)->toBe($category1->id);
    expect($product->brand_id)->toBe($brand1->id);
    expect($product->brand)->toBe('Brand One');

    // Pivot tables check
    expect($product->categories)->toHaveCount(2);
    expect($product->brands)->toHaveCount(2);
    expect($product->categories->pluck('id')->toArray())->toContain($category1->id, $category2->id);
    expect($product->brands->pluck('id')->toArray())->toContain($brand1->id, $brand2->id);
});

test('admin can filter products by multiple categories and brands on index', function () {
    $category1 = Category::create(['name' => 'Category One', 'slug' => 'cat-one']);
    $category2 = Category::create(['name' => 'Category Two', 'slug' => 'cat-two']);

    $brand1 = Brand::create(['name' => 'Brand One', 'slug' => 'brand-one']);
    $brand2 = Brand::create(['name' => 'Brand Two', 'slug' => 'brand-two']);

    // Product with cat1 and brand1
    $product1 = Product::create([
        'name' => 'Product One',
        'slug' => 'prod-one',
        'sku' => 'P-ONE',
        'category_id' => $category1->id,
        'brand_id' => $brand1->id,
        'description' => 'Desc one',
    ]);
    $product1->categories()->sync([$category1->id]);
    $product1->brands()->sync([$brand1->id]);

    // Product with cat2 and brand2
    $product2 = Product::create([
        'name' => 'Product Two',
        'slug' => 'prod-two',
        'sku' => 'P-TWO',
        'category_id' => $category2->id,
        'brand_id' => $brand2->id,
        'description' => 'Desc two',
    ]);
    $product2->categories()->sync([$category2->id]);
    $product2->brands()->sync([$brand2->id]);

    // Filter by cat1 only
    $response = $this->actingAs($this->user)->get(route('admin.products.index', [
        'category' => [$category1->id],
    ]));
    $response->assertOk();
    $pageProps = $response->original->getData()['page']['props'];
    $dataProducts = $pageProps['products']['data'];
    $ids = collect($dataProducts)->pluck('id')->toArray();
    expect($ids)->toContain($product1->id);
    expect($ids)->not->toContain($product2->id);

    // Filter by brand2 only
    $response = $this->actingAs($this->user)->get(route('admin.products.index', [
        'brand' => [$brand2->id],
    ]));
    $response->assertOk();
    $pageProps = $response->original->getData()['page']['props'];
    $dataProducts = $pageProps['products']['data'];
    $ids = collect($dataProducts)->pluck('id')->toArray();
    expect($ids)->toContain($product2->id);
    expect($ids)->not->toContain($product1->id);
});
