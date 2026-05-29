<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * Setup test data for CMS testing.
 */
function setupCmsTestData(): User
{
    $admin = User::factory()->create();

    return $admin;
}

test('admin can view banners CMS page', function () {
    $admin = setupCmsTestData();

    $response = $this->actingAs($admin)->get(route('admin.cms.banners'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Cms/Banners')
        ->has('heroBanners')
        ->has('sideBanners')
        ->has('middleWideBanner')
        ->has('storefrontUrl')
    );
});

test('admin can update banners and upload files', function () {
    Storage::fake('public');
    $admin = setupCmsTestData();

    $heroFile = UploadedFile::fake()->image('hero.jpg');
    $sideFile = UploadedFile::fake()->image('side.jpg');
    $middleWideFile = UploadedFile::fake()->image('middle_wide.jpg');

    $response = $this->actingAs($admin)->post(route('admin.cms.banners.update'), [
        'hero_banners' => [
            ['alt' => 'New Hero Alt', 'link' => '/promo'],
        ],
        'side_banners' => [
            ['alt' => 'New Side Alt', 'link' => '/sale'],
        ],
        'middle_wide_banner' => [
            'alt' => 'New Middle Wide Alt', 'link' => '/wide-promo', 'image' => '/banners/flash-sale.png',
        ],
        'hero_files' => [
            0 => $heroFile,
        ],
        'side_files' => [
            0 => $sideFile,
        ],
        'middle_wide_file' => $middleWideFile,
    ]);

    $response->assertRedirect();

    // Check setting table values
    $heroBanners = json_decode(Setting::where('key', 'hero_banners')->value('value'), true);
    $sideBanners = json_decode(Setting::where('key', 'side_banners')->value('value'), true);
    $middleWideBanner = json_decode(Setting::where('key', 'middle_wide_banner')->value('value'), true);

    expect($heroBanners)->toHaveCount(1);
    expect($heroBanners[0]['alt'])->toBe('New Hero Alt');
    expect($heroBanners[0]['link'])->toBe('/promo');
    expect($heroBanners[0]['image'])->toStartWith('/storage/banners/');

    expect($sideBanners)->toHaveCount(1);
    expect($sideBanners[0]['alt'])->toBe('New Side Alt');
    expect($sideBanners[0]['link'])->toBe('/sale');
    expect($sideBanners[0]['image'])->toStartWith('/storage/banners/');

    expect($middleWideBanner)->toBeArray();
    expect($middleWideBanner['alt'])->toBe('New Middle Wide Alt');
    expect($middleWideBanner['link'])->toBe('/wide-promo');
    expect($middleWideBanner['image'])->toStartWith('/storage/banners/');

    // Assert files are stored in public storage
    $storedHeroPath = str_replace('/storage/', '', $heroBanners[0]['image']);
    $storedSidePath = str_replace('/storage/', '', $sideBanners[0]['image']);
    $storedMiddleWidePath = str_replace('/storage/', '', $middleWideBanner['image']);

    Storage::disk('public')->assertExists($storedHeroPath);
    Storage::disk('public')->assertExists($storedSidePath);
    Storage::disk('public')->assertExists($storedMiddleWidePath);
});
