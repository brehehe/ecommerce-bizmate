<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('user can access category page by slug', function () {
    $category = Category::create([
        'name' => 'Elektronik',
        'slug' => 'elektronik',
        'icon' => 'ti-cpu',
    ]);

    $product = Product::create([
        'name' => 'Laptop ASUS',
        'slug' => 'laptop-asus',
        'sku' => 'LAP-ASUS',
        'category_id' => $category->id,
        'summary' => 'Summary',
        'description' => 'Description',
        'active' => true,
    ]);

    $response = $this->get("/category/{$category->slug}");

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Storefront/Category')
        ->where('category.name', 'Elektronik')
        ->has('products.data', 1)
        ->where('products.data.0.name', 'Laptop ASUS')
    );
});

test('user can access category page by id', function () {
    $category = Category::create([
        'name' => 'Pakaian',
        'slug' => 'pakaian',
        'icon' => 'ti-shirt',
    ]);

    $product = Product::create([
        'name' => 'Kemeja Batik',
        'slug' => 'kemeja-batik',
        'sku' => 'BATIK-KEM',
        'category_id' => $category->id,
        'summary' => 'Summary',
        'description' => 'Description',
        'active' => true,
    ]);

    $response = $this->get("/category/{$category->id}");

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Storefront/Category')
        ->where('category.name', 'Pakaian')
        ->has('products.data', 1)
        ->where('products.data.0.name', 'Kemeja Batik')
    );
});

test('category page filters products by search query and prices', function () {
    $category = Category::create([
        'name' => 'Makanan',
        'slug' => 'makanan',
        'icon' => 'ti-tools',
    ]);

    $product1 = Product::create([
        'name' => 'Bakso Sapi',
        'slug' => 'bakso-sapi',
        'sku' => 'BAKSO-1',
        'category_id' => $category->id,
        'summary' => 'Summary',
        'description' => 'Description',
        'active' => true,
    ]);

    $product2 = Product::create([
        'name' => 'Nasi Goreng',
        'slug' => 'nasi-goreng',
        'sku' => 'NASGOR-2',
        'category_id' => $category->id,
        'summary' => 'Summary',
        'description' => 'Description',
        'active' => true,
    ]);

    $product1->productPrice()->create(['price' => 15000]);
    $product2->productPrice()->create(['price' => 25000]);

    // Test keyword search
    $responseSearch = $this->get("/category/{$category->slug}?q=Bakso");
    $responseSearch->assertOk();
    $responseSearch->assertInertia(fn (Assert $page) => $page
        ->component('Storefront/Category')
        ->has('products.data', 1)
        ->where('products.data.0.name', 'Bakso Sapi')
    );

    // Test price range filter
    $responsePrice = $this->get("/category/{$category->slug}?min_price=20000");
    $responsePrice->assertOk();
    $responsePrice->assertInertia(fn (Assert $page) => $page
        ->component('Storefront/Category')
        ->has('products.data', 1)
        ->where('products.data.0.name', 'Nasi Goreng')
    );
});

test('admin cannot create category with media_type image without uploading an image', function () {
    $admin = User::factory()->create();

    $response = $this->actingAs($admin)->post(route('admin.categories.store'), [
        'name' => 'Kategori Baru',
        'slug' => 'kategori-baru',
        'media_type' => 'image',
    ]);

    $response->assertSessionHasErrors(['image']);
});

test('admin cannot create category with media_type image when uploading a landscape image', function () {
    $admin = User::factory()->create();

    $landscapeImage = UploadedFile::fake()->image('landscape.jpg', 300, 200);

    $response = $this->actingAs($admin)->post(route('admin.categories.store'), [
        'name' => 'Kategori Baru',
        'slug' => 'kategori-baru',
        'media_type' => 'image',
        'image' => $landscapeImage,
    ]);

    $response->assertSessionHasErrors(['image']);
});

test('admin can create category with media_type image when uploading a square or portrait image', function () {
    Storage::fake('public');
    $admin = User::factory()->create();

    $squareImage = UploadedFile::fake()->image('square.jpg', 200, 200);

    $response = $this->actingAs($admin)->post(route('admin.categories.store'), [
        'name' => 'Kategori Square',
        'slug' => 'kategori-square',
        'media_type' => 'image',
        'image' => $squareImage,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('categories', [
        'name' => 'Kategori Square',
        'slug' => 'kategori-square',
    ]);

    $portraitImage = UploadedFile::fake()->image('portrait.jpg', 200, 300);

    $response = $this->actingAs($admin)->post(route('admin.categories.store'), [
        'name' => 'Kategori Portrait',
        'slug' => 'kategori-portrait',
        'media_type' => 'image',
        'image' => $portraitImage,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('categories', [
        'name' => 'Kategori Portrait',
        'slug' => 'kategori-portrait',
    ]);
});

test('admin cannot update category to media_type image without an image in DB or upload', function () {
    $admin = User::factory()->create();
    $category = Category::create([
        'name' => 'Kategori Awal',
        'slug' => 'kategori-awal',
        'icon' => 'ti-folder',
    ]);

    $response = $this->actingAs($admin)->put(route('admin.categories.update', $category), [
        'name' => 'Kategori Edit',
        'slug' => 'kategori-edit',
        'media_type' => 'image',
    ]);

    $response->assertSessionHasErrors(['image']);
});

test('admin cannot update category with a landscape image', function () {
    $admin = User::factory()->create();
    $category = Category::create([
        'name' => 'Kategori Awal',
        'slug' => 'kategori-awal',
        'icon' => 'ti-folder',
    ]);

    $landscapeImage = UploadedFile::fake()->image('landscape.jpg', 300, 200);

    $response = $this->actingAs($admin)->put(route('admin.categories.update', $category), [
        'name' => 'Kategori Edit',
        'slug' => 'kategori-edit',
        'media_type' => 'image',
        'image' => $landscapeImage,
    ]);

    $response->assertSessionHasErrors(['image']);
});

test('admin can update category with a square/portrait image', function () {
    Storage::fake('public');
    $admin = User::factory()->create();
    $category = Category::create([
        'name' => 'Kategori Awal',
        'slug' => 'kategori-awal',
        'icon' => 'ti-folder',
    ]);

    $portraitImage = UploadedFile::fake()->image('portrait.jpg', 200, 350);

    $response = $this->actingAs($admin)->put(route('admin.categories.update', $category), [
        'name' => 'Kategori Edit',
        'slug' => 'kategori-edit',
        'media_type' => 'image',
        'image' => $portraitImage,
    ]);

    $response->assertRedirect();
    $category->refresh();
    expect($category->image)->not->toBeNull();
    expect($category->icon)->toBeNull();
});

test('admin can delete category and its subcategories are cascadingly soft-deleted', function () {
    $admin = User::factory()->create();

    $parent = Category::create([
        'name' => 'Main Category',
        'slug' => 'main-category',
        'icon' => 'ti-folder',
    ]);

    $child = Category::create([
        'name' => 'Sub Category',
        'slug' => 'sub-category',
        'icon' => 'ti-folder',
        'parent_id' => $parent->id,
    ]);

    $response = $this->actingAs($admin)->delete(route('admin.categories.destroy', $parent));

    $response->assertRedirect();
    $this->assertSoftDeleted('categories', ['id' => $parent->id]);
    $this->assertSoftDeleted('categories', ['id' => $child->id]);
});

test('admin can bulk delete categories', function () {
    $admin = User::factory()->create();

    $cat1 = Category::create([
        'name' => 'Category 1',
        'slug' => 'category-1',
        'icon' => 'ti-folder',
    ]);

    $cat2 = Category::create([
        'name' => 'Category 2',
        'slug' => 'category-2',
        'icon' => 'ti-folder',
    ]);

    $response = $this->actingAs($admin)->post(route('admin.categories.bulk-delete'), [
        'ids' => [$cat1->id, $cat2->id],
    ]);

    $response->assertRedirect();
    $this->assertSoftDeleted('categories', ['id' => $cat1->id]);
    $this->assertSoftDeleted('categories', ['id' => $cat2->id]);
});
