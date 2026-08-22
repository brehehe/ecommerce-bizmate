<?php

use App\Models\PageView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Admin']);
    Role::firstOrCreate(['name' => 'Customer']);
});

test('it tracks page views on storefront home visit', function () {
    $response = $this->get('/');

    $response->assertOk();

    expect(PageView::where('path', '/')->count())->toBeGreaterThanOrEqual(1);
});

test('it tracks seller store visit and associates seller_id', function () {
    $seller = User::factory()->create([
        'is_seller' => true,
        'store_slug' => 'toko-testing-keren',
        'store_name' => 'Toko Testing Keren',
    ]);
    $seller->assignRole('Customer');

    $response = $this->get('/toko-testing-keren');

    $response->assertOk();

    $pageView = PageView::where('seller_id', $seller->id)->first();
    expect($pageView)->not->toBeNull();
    expect($pageView->path)->toBe('/toko-testing-keren');
});

test('admin dashboard loads visitor stats and online counters', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    PageView::create([
        'session_id' => 'sess_test_1',
        'ip_address' => '127.0.0.1',
        'url' => 'http://localhost:8000/',
        'path' => '/',
        'route_name' => 'home',
        'device' => 'desktop',
        'created_at' => now(),
    ]);

    PageView::create([
        'session_id' => 'sess_test_2',
        'ip_address' => '127.0.0.1',
        'url' => 'http://localhost:8000/search',
        'path' => '/search',
        'route_name' => 'search',
        'device' => 'mobile',
        'created_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get('/admin/dashboard');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Dashboard')
        ->has('visitorStats')
        ->has('topVisitedPages')
        ->where('visitorStats.onlineVisitors', 2)
        ->where('visitorStats.pageviewsCount', 2)
    );
});

test('seller dashboard hides visitorIpLogs and ipTrafficAnalytics from Seller', function () {
    config(['app.is_seller' => true]);

    $seller = User::factory()->create(['is_seller' => true]);
    $seller->assignRole('Customer');

    PageView::create([
        'session_id' => 'sess_test_seller',
        'seller_id' => $seller->id,
        'ip_address' => '192.168.1.10',
        'url' => 'http://localhost:8000/my-store',
        'path' => '/my-store',
        'route_name' => 'storefront.store',
        'device' => 'mobile',
        'created_at' => now(),
    ]);

    $response = $this->actingAs($seller)->get('/admin/dashboard');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Dashboard')
        ->where('isSeller', true)
        ->where('visitorIpLogs', null)
        ->where('ipTrafficAnalytics', null)
    );
});
