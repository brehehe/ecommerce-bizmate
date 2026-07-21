<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole('Admin');
});

test('admin can search web images', function () {
    // Mock Bing Images search response
    Http::fake([
        'https://www.bing.com/images/search*' => Http::response(
            '<html><body><a class="iusc" m="{&quot;murl&quot;:&quot;https://shopee.co.id/image1.jpg&quot;,&quot;turl&quot;:&quot;https://shopee.co.id/thumb1.jpg&quot;,&quot;desc&quot;:&quot;Nike Shoes&quot;}"></a></body></html>',
            200
        ),
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.products.search-web-images', ['q' => 'sepatu nike']));

    $response->assertOk();
    $response->assertJsonStructure([
        'images' => [
            '*' => ['url', 'thumbnail', 'title'],
        ],
    ]);

    $data = $response->json();
    expect($data['images'])->toHaveCount(1);
    expect($data['images'][0]['url'])->toBe('https://shopee.co.id/image1.jpg');
});

test('admin can download proxy image as base64', function () {
    // Mock the external image download
    // Create a 1x1 dummy PNG image
    $dummyPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

    Http::fake([
        'https://shopee.co.id/image1.jpg' => Http::response($dummyPng, 200, [
            'Content-Type' => 'image/png',
        ]),
    ]);

    $response = $this->actingAs($this->admin)
        ->post(route('admin.products.proxy-web-image'), [
            'url' => 'https://shopee.co.id/image1.jpg',
        ]);

    $response->assertOk();
    $response->assertJsonStructure(['image']);

    $data = $response->json();
    expect($data['image'])->toContain('data:image/png;base64,');
});

test('guest cannot search web images', function () {
    $response = $this->get(route('admin.products.search-web-images', ['q' => 'sepatu nike']));
    $response->assertRedirect('/login');
});

test('admin can search web images using google custom search api', function () {
    // Set custom env variables for test
    config(['services.google_search.key' => 'test-key']);
    config(['services.google_search.cx' => 'test-cx']);

    Http::fake([
        'https://www.bing.com/images/search*' => Http::response('<html><body></body></html>', 200),
        'https://customsearch.googleapis.com/*' => Http::response([
            'items' => [
                [
                    'title' => 'Google Nike Image',
                    'link' => 'https://shopee.co.id/google-image1.jpg',
                    'image' => [
                        'thumbnailLink' => 'https://shopee.co.id/google-thumb1.jpg',
                    ],
                ],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.products.search-web-images', ['q' => 'sepatu nike']));

    $response->assertOk();
    $data = $response->json();
    expect($data['images'])->toHaveCount(1);
    expect($data['images'][0]['url'])->toBe('https://shopee.co.id/google-image1.jpg');
    expect($data['images'][0]['title'])->toBe('Google Nike Image');
});
