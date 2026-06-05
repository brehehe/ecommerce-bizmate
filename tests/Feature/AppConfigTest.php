<?php

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('superadmin can access app config show page', function () {
    $response = $this->get('/zozzuehmqewbobfo');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/AppConfig')
        ->has('currentAppName')
        ->has('currentStoreName')
        ->has('currentStoreAppName')
    );
});

test('superadmin can update config with correct secret key', function () {
    $response = $this->post('/zozzuehmqewbobfo', [
        'app_name' => 'NewAppName',
        'store_name' => 'NewStoreName',
        'store_app_name' => 'NewStoreAppName',
        'secret_key' => 'zozzuehmqewbobfo',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    // Verify settings updated in DB
    expect(Setting::where('key', 'store_name')->value('value'))->toBe('NewStoreName');
    expect(Setting::where('key', 'store_app_name')->value('value'))->toBe('NewStoreAppName');
});

test('superadmin cannot update config with incorrect secret key', function () {
    $response = $this->post('/zozzuehmqewbobfo', [
        'app_name' => 'NewAppName',
        'store_name' => 'NewStoreName',
        'store_app_name' => 'NewStoreAppName',
        'secret_key' => 'wrong_key',
    ]);

    $response->assertSessionHasErrors(['secret_key']);
});
