<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->category = Category::create([
        'name' => 'Furniture',
        'slug' => 'furniture',
        'icon' => 'ti-home',
    ]);
    $this->brand = Brand::create([
        'name' => 'IKEA',
        'slug' => 'ikea',
        'is_active' => true,
    ]);
});

test('product store endpoint correctly saves photo, variation, and option sort orders', function () {
    $response = $this->actingAs($this->user)->post(route('admin.products.store'), [
        'name' => 'Wooden Table',
        'sku' => 'TAB-WOOD-01',
        'category_ids' => [$this->category->id],
        'brand_ids' => [$this->brand->id],
        'price' => 250000,
        'cost' => 150000,
        'stock' => 10,
        'description' => 'Fine wooden table description.',
        'photos' => [
            'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
        ],
        'variations' => [
            [
                'name' => 'Color',
                'options' => [
                    ['id' => 'temp-1', 'name' => 'Brown', 'description' => 'Natural wood brown'],
                    ['id' => 'temp-2', 'name' => 'Black', 'description' => 'Matte black style'],
                ],
            ],
            [
                'name' => 'Size',
                'options' => [
                    ['id' => 'temp-3', 'name' => 'M', 'description' => 'Medium size'],
                    ['id' => 'temp-4', 'name' => 'L', 'description' => 'Large size'],
                ],
            ],
        ],
        'variants' => [],
    ]);

    $response->assertRedirect(route('admin.products.index'));

    $product = Product::with(['images', 'variations.options'])->where('sku', 'TAB-WOOD-01')->first();
    expect($product)->not->toBeNull();

    // Verify images order
    expect($product->images)->toHaveCount(2);
    expect($product->images[0]->sort_order)->toBe(0);
    expect($product->images[1]->sort_order)->toBe(1);

    // Verify variations order
    expect($product->variations)->toHaveCount(2);
    expect($product->variations[0]->name)->toBe('Color');
    expect($product->variations[0]->sort_order)->toBe(0);
    expect($product->variations[1]->name)->toBe('Size');
    expect($product->variations[1]->sort_order)->toBe(1);

    // Verify options order within variations
    $colorVar = $product->variations[0];
    expect($colorVar->options)->toHaveCount(2);
    expect($colorVar->options[0]->name)->toBe('Brown');
    expect($colorVar->options[0]->sort_order)->toBe(0);
    expect($colorVar->options[1]->name)->toBe('Black');
    expect($colorVar->options[1]->sort_order)->toBe(1);

    $sizeVar = $product->variations[1];
    expect($sizeVar->options)->toHaveCount(2);
    expect($sizeVar->options[0]->name)->toBe('M');
    expect($sizeVar->options[0]->sort_order)->toBe(0);
    expect($sizeVar->options[1]->name)->toBe('L');
    expect($sizeVar->options[1]->sort_order)->toBe(1);
});

test('product update endpoint correctly updates photo, variation, and option sort orders', function () {
    // 1. First, create a product using store
    $product = Product::create([
        'name' => 'Wooden Chair',
        'sku' => 'CHR-WOOD-01',
        'category_id' => $this->category->id,
        'brand_id' => $this->brand->id,
        'slug' => 'wooden-chair-slug',
        'description' => 'Fine wooden chair description.',
    ]);

    $product->categories()->sync([$this->category->id]);
    $product->brands()->sync([$this->brand->id]);

    $img1 = $product->images()->create(['path' => 'storage/products/image1.png', 'is_main' => true, 'sort_order' => 0]);
    $img2 = $product->images()->create(['path' => 'storage/products/image2.png', 'is_main' => false, 'sort_order' => 1]);

    $colorVar = $product->variations()->create(['name' => 'Color', 'sort_order' => 0]);
    $brownOpt = $colorVar->options()->create(['name' => 'Brown', 'sort_order' => 0]);
    $blackOpt = $colorVar->options()->create(['name' => 'Black', 'sort_order' => 1]);

    // 2. Perform updates to reorder them: swap photos order and swap options order
    $response = $this->actingAs($this->user)->put(route('admin.products.update', $product), [
        'name' => 'Wooden Chair Updated',
        'sku' => 'CHR-WOOD-01',
        'category_ids' => [$this->category->id],
        'brand_ids' => [$this->brand->id],
        'price' => 300000,
        'cost' => 180000,
        'stock' => 15,
        'description' => 'Updated chair description.',
        'photos' => [
            'storage/products/image2.png', // swapped to index 0
            'storage/products/image1.png', // swapped to index 1
        ],
        'variations' => [
            [
                'id' => $colorVar->id,
                'name' => 'Color',
                'options' => [
                    ['id' => $blackOpt->id, 'name' => 'Black', 'image' => ''], // swapped to index 0
                    ['id' => $brownOpt->id, 'name' => 'Brown', 'image' => ''], // swapped to index 1
                ],
            ],
        ],
        'variants' => [],
    ]);

    $response->assertRedirect(route('admin.products.index'));

    // Reload with ordered relations
    $product->load(['images', 'variations.options']);

    // Check swapped images order
    expect($product->images)->toHaveCount(2);
    expect($product->images[0]->path)->toBe('storage/products/image2.png');
    expect($product->images[0]->sort_order)->toBe(0);
    expect($product->images[0]->is_main)->toBeTrue();

    expect($product->images[1]->path)->toBe('storage/products/image1.png');
    expect($product->images[1]->sort_order)->toBe(1);
    expect($product->images[1]->is_main)->toBeFalse();

    // Check swapped options order
    expect($product->variations[0]->options)->toHaveCount(2);
    expect($product->variations[0]->options[0]->name)->toBe('Black');
    expect($product->variations[0]->options[0]->sort_order)->toBe(0);
    expect($product->variations[0]->options[1]->name)->toBe('Brown');
    expect($product->variations[0]->options[1]->sort_order)->toBe(1);
});
