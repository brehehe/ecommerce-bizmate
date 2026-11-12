<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;

test('home page renders opengraph and description meta tags for social link sharing', function () {
    Setting::updateOrCreate(['key' => 'store_name'], ['value' => 'Bizmate Store']);
    Setting::updateOrCreate(['key' => 'store_description'], ['value' => 'Toko online terpercaya serba lengkap']);
    Setting::updateOrCreate(['key' => 'store_logo'], ['value' => '/logos/default-logo.png']);

    $response = $this->get('/');

    $response->assertOk();
    $content = $response->getContent();

    expect($content)->toContain('<meta property="og:title" content="Bizmate Store - Toko Online">')
        ->and($content)->toContain('<meta property="og:description" content="Toko online terpercaya serba lengkap">')
        ->and($content)->toContain('<meta name="description" content="Toko online terpercaya serba lengkap">')
        ->and($content)->toContain('<meta property="og:type" content="website">')
        ->and($content)->toContain('logos/default-logo.png');
});

test('product page renders product specific opengraph tags', function () {
    Setting::updateOrCreate(['key' => 'store_name'], ['value' => 'Bizmate Store']);

    $category = Category::create([
        'name' => 'Olahraga',
        'slug' => 'olahraga',
    ]);
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'SEP-001',
        'name' => 'Sepatu Olahraga Running Pro',
        'slug' => 'sepatu-olahraga-running-pro',
        'summary' => 'Sepatu lari ringan dan nyaman untuk maraton',
        'image' => 'https://example.com/sepatu.jpg',
        'active' => true,
    ]);

    $response = $this->get(route('products.show', $product->slug));

    $response->assertOk();
    $content = $response->getContent();

    expect($content)->toContain('<meta property="og:type" content="product">')
        ->and($content)->toContain('<meta property="og:title" content="Sepatu Olahraga Running Pro - Bizmate Store">')
        ->and($content)->toContain('<meta property="og:description" content="Sepatu lari ringan dan nyaman untuk maraton">')
        ->and($content)->toContain('https://example.com/sepatu.jpg');
});
