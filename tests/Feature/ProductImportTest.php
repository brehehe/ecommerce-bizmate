<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can download product import template', function () {
    $admin = User::factory()->create();

    $response = $this->actingAs($admin)->get(route('admin.products.import.template'));

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    $response->assertHeader('Content-Disposition', 'attachment; filename="template_import_produk.csv"');
});

test('admin can import simple product successfully', function () {
    $admin = User::factory()->create();

    $payload = [
        'products' => [
            [
                'name' => 'Imported Shoe',
                'sku' => 'SHOE-IMP-001',
                'price' => 125000,
                'cost' => 80000,
                'stock' => 50,
                'min_stock' => 5,
                'min_purchase' => 2,
                'weight' => 500,
                'length' => 30,
                'width' => 15,
                'height' => 10,
                'summary' => 'Fine imported footwear',
                'description' => 'A very fine imported shoe.',
                'tax_enabled' => true,
                'is_digital' => false,
                'is_unlimited' => false,
                'specifications' => [
                    ['name' => 'Bahan', 'value' => 'Kulit'],
                ],
                'category_names' => 'Footwear, Sport',
                'brand_name' => 'ImportBrand',
            ],
        ],
    ];

    $response = $this->actingAs($admin)->post(route('admin.products.import'), $payload);

    $response->assertStatus(302); // redirects back

    // Assert product creation in DB
    $product = Product::where('sku', 'SHOE-IMP-001')->first();
    expect($product)->not->toBeNull();
    expect($product->name)->toBe('Imported Shoe');
    expect($product->summary)->toBe('Fine imported footwear');
    expect($product->specifications)->toBe([['name' => 'Bahan', 'value' => 'Kulit']]);
    expect($product->weight)->toBe(500);
    expect($product->tax_enabled)->toBeTrue();

    // Assert main price & stock relations
    expect($product->productPrice->price)->toEqual(125000);
    expect($product->productPrice->cost)->toEqual(80000);
    expect($product->productStock->stock)->toBe(50);
    expect($product->productStock->min_stock)->toBe(5);
    expect($product->productStock->min_purchase)->toBe(2);

    // Assert categories and brands created and synced
    $brand = Brand::where('name', 'ImportBrand')->first();
    expect($brand)->not->toBeNull();
    expect($product->brand_id)->toBe($brand->id);

    $categorySport = Category::where('name', 'Sport')->first();
    expect($categorySport)->not->toBeNull();
    expect($product->categories->pluck('id'))->toContain($categorySport->id);
});

test('admin can update existing product by SKU during import', function () {
    $admin = User::factory()->create();

    // Pre-create the product
    $product = Product::create([
        'name' => 'Original Shoe',
        'sku' => 'SHOE-IMP-001',
        'slug' => 'original-shoe',
        'description' => 'Original description',
        'active' => true,
    ]);
    $product->productPrice()->create(['price' => 100000]);
    $product->productStock()->create(['stock' => 10]);

    $payload = [
        'products' => [
            [
                'name' => 'Updated Shoe Name',
                'sku' => 'SHOE-IMP-001',
                'price' => 150000,
                'cost' => 90000,
                'stock' => 100,
                'weight' => 600,
                'length' => 32,
                'width' => 16,
                'height' => 11,
                'description' => 'Updated description.',
                'tax_enabled' => false,
                'is_digital' => false,
                'is_unlimited' => true,
                'category_names' => 'Footwear',
                'brand_name' => 'UpdateBrand',
            ],
        ],
    ];

    $response = $this->actingAs($admin)->post(route('admin.products.import'), $payload);

    $response->assertStatus(302);

    $product->refresh();
    expect($product->name)->toBe('Updated Shoe Name');
    expect($product->description)->toBe('Updated description.');
    expect($product->weight)->toBe(600);
    expect($product->tax_enabled)->toBeFalse();

    expect($product->productPrice->price)->toEqual(150000);
    expect($product->productStock->stock)->toBe(100);
    expect($product->productStock->is_unlimited)->toBeTrue();
});

test('admin can import product with variations and variants combo', function () {
    $admin = User::factory()->create();

    $payload = [
        'products' => [
            [
                'name' => 'Shirt with Variants',
                'sku' => 'SHIRT-VAR-001',
                'price' => 150000,
                'cost' => 100000,
                'stock' => 40,
                'weight' => 200,
                'length' => 25,
                'width' => 20,
                'height' => 2,
                'description' => 'Cool shirt with size variants.',
                'tax_enabled' => true,
                'is_digital' => false,
                'is_unlimited' => false,
                'category_names' => 'Clothes',
                'brand_name' => 'ShirtBrand',
                'variations' => [
                    [
                        'name' => 'Ukuran',
                        'options' => [
                            ['id' => 'S', 'name' => 'S'],
                            ['id' => 'M', 'name' => 'M'],
                        ],
                    ],
                ],
                'variants' => [
                    [
                        'id' => 'S',
                        'sku' => 'SHIRT-VAR-001-S',
                        'price' => 140000,
                        'cost' => 100000,
                        'stock' => 15,
                        'is_custom' => true,
                        'custom_price' => true,
                        'custom_stock' => true,
                    ],
                    [
                        'id' => 'M',
                        'sku' => 'SHIRT-VAR-001-M',
                        'price' => 150000,
                        'cost' => 100000,
                        'stock' => 25,
                        'is_custom' => true,
                        'custom_price' => true,
                        'custom_stock' => true,
                    ],
                ],
            ],
        ],
    ];

    $response = $this->actingAs($admin)->post(route('admin.products.import'), $payload);

    $response->assertStatus(302);

    $product = Product::where('sku', 'SHIRT-VAR-001')->first();
    expect($product)->not->toBeNull();

    // Check variations & options
    expect($product->variations)->toHaveCount(1);
    $variation = $product->variations->first();
    expect($variation->name)->toBe('Ukuran');
    expect($variation->options)->toHaveCount(2);

    // Check variants combinations
    expect($product->variants)->toHaveCount(2);

    $variantS = $product->variants()->where('sku', 'SHIRT-VAR-001-S')->first();
    expect($variantS)->not->toBeNull();
    expect($variantS->productPrice->price)->toEqual(140000);
    expect($variantS->productStock->stock)->toBe(15);
    expect($variantS->options->first()->name)->toBe('S');
});

test('imported product automatically receives sequential order value', function () {
    $admin = User::factory()->create();

    // Create an existing product with order = 5
    $p1 = Product::create([
        'name' => 'Existing Product',
        'sku' => 'EXIST-001',
        'slug' => 'existing-product',
        'description' => 'Desc',
        'active' => true,
        'order' => 5,
    ]);

    $payload = [
        'products' => [
            [
                'name' => 'New Imported Product',
                'sku' => 'SHOE-IMP-999',
                'price' => 125000,
                'cost' => 80000,
                'stock' => 50,
                'description' => 'A new imported shoe.',
                'tax_enabled' => true,
                'is_digital' => false,
                'is_unlimited' => false,
            ],
        ],
    ];

    $response = $this->actingAs($admin)->post(route('admin.products.import'), $payload);
    $response->assertRedirect();

    $product = Product::where('sku', 'SHOE-IMP-999')->first();
    expect($product)->not->toBeNull();
    // Since the max order was 5, the new product should have order = 6
    expect($product->order)->toBe(6);
});
