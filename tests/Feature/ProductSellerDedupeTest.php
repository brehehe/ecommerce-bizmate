<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->category = Category::create([
        'name' => 'Olahraga',
        'slug' => 'olahraga',
    ]);
});

function makeSellerProduct(Category $category, array $attributes = []): Product
{
    $product = Product::create(array_merge([
        'name' => 'Produk Test',
        'slug' => 'produk-test-'.Str::random(6),
        'sku' => 'SKU-TEST',
        'category_id' => $category->id,
        'description' => 'Deskripsi test',
    ], $attributes));

    $product->productPrice()->create(['price' => 100000]);
    $product->productStock()->create(['stock' => 10]);

    return $product;
}

test('edit page sellers dropdown dedupes accounts with the same phone number, preferring the seller', function () {
    $seller = User::factory()->create([
        'name' => 'Kokoh Yanuar',
        'phone_number' => '081332090011',
        'is_seller' => true,
        'store_name' => 'Kokoh Yanuar',
    ]);
    $duplicate = User::factory()->create([
        'name' => 'Kokoh Yanuar',
        'phone_number' => '081332090011',
        'is_seller' => false,
    ]);

    $product = makeSellerProduct($this->category, ['user_id' => $seller->id]);

    $response = $this->actingAs($this->admin)->get(route('admin.products.edit', $product));

    $response->assertOk();
    $props = $response->original->getData()['page']['props'];
    $kokohAccounts = collect($props['sellers'])->filter(fn ($s) => $s['name'] === 'Kokoh Yanuar');

    expect($kokohAccounts->pluck('id')->all())->toBe([$seller->id]);
});

test('edit page sellers dropdown keeps the current product owner even when it is the weaker candidate', function () {
    $seller = User::factory()->create([
        'name' => 'Kokoh Yanuar',
        'phone_number' => '081332090011',
        'is_seller' => true,
    ]);
    $customerWithProducts = User::factory()->create([
        'name' => 'Kokoh Yanuar',
        'phone_number' => '081332090011',
        'is_seller' => false,
    ]);

    $product = makeSellerProduct($this->category, ['user_id' => $customerWithProducts->id]);

    $response = $this->actingAs($this->admin)->get(route('admin.products.edit', $product));

    $response->assertOk();
    $props = $response->original->getData()['page']['props'];
    $sellerIds = collect($props['sellers'])->pluck('id')->all();

    expect($sellerIds)->toContain($customerWithProducts->id);
});

test('create page sellers dropdown dedupes by phone number', function () {
    User::factory()->create([
        'name' => 'Dedi',
        'phone_number' => '081234567890',
        'is_seller' => true,
    ]);
    User::factory()->create([
        'name' => 'Dedi',
        'phone_number' => '6281234567890',
        'is_seller' => true,
    ]);

    $response = $this->actingAs($this->admin)->get(route('admin.products.create'));

    $response->assertOk();
    $props = $response->original->getData()['page']['props'];
    $dediAccounts = collect($props['sellers'])->filter(fn ($s) => $s['name'] === 'Dedi');

    expect($dediAccounts)->toHaveCount(1);
});

test('updating a product with a new seller reuses an existing account with a differently formatted phone', function () {
    $existing = User::factory()->create([
        'name' => 'Budi',
        'phone_number' => '6281234567890',
        'is_seller' => true,
    ]);

    $product = makeSellerProduct($this->category);

    $this->actingAs($this->admin)->put(route('admin.products.update', $product), [
        'name' => $product->name,
        'price' => 100000,
        'description' => $product->description,
        'new_seller' => [
            'name' => 'Budi',
            'phone_number' => '081234567890',
        ],
    ])->assertRedirect();

    expect(User::whereIn('phone_number', ['081234567890', '6281234567890'])->count())->toBe(1);

    $product->refresh();
    expect($product->user_id)->toBe($existing->id);
});

test('updating a product with a new seller reuses an existing account by email', function () {
    $existing = User::factory()->create([
        'name' => 'Budi',
        'email' => 'buditoko@example.com',
        'is_seller' => false,
    ]);

    $product = makeSellerProduct($this->category);

    $this->actingAs($this->admin)->put(route('admin.products.update', $product), [
        'name' => $product->name,
        'price' => 100000,
        'description' => $product->description,
        'new_seller' => [
            'name' => 'Budi',
            'email' => 'buditoko@example.com',
        ],
    ])->assertRedirect();

    expect(User::where('email', 'buditoko@example.com')->count())->toBe(1);

    $product->refresh();
    expect($product->user_id)->toBe($existing->id);
    expect($existing->refresh()->is_seller)->toBeTrue();
});

test('storing a product with a new seller creates a new seller account when none matches', function () {
    $this->actingAs($this->admin)->post(route('admin.products.store'), [
        'name' => 'Produk Baru',
        'price' => 50000,
        'description' => 'Deskripsi produk baru',
        'new_seller' => [
            'name' => 'Cahyo',
            'phone_number' => '085656629097',
        ],
    ])->assertRedirect();

    $product = Product::where('name', 'Produk Baru')->firstOrFail();
    $seller = User::findOrFail($product->user_id);

    expect($seller->name)->toBe('Cahyo');
    expect($seller->phone_number)->toBe('085656629097');
    expect($seller->is_seller)->toBeTrue();
    expect(User::where('phone_number', '085656629097')->count())->toBe(1);
});
