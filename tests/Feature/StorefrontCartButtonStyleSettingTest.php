<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('storefront cart button style defaults to button when not set in database', function () {
    $response = $this->get('/');
    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->where('settings.storefront_cart_button_style', 'button')
    );
});

test('storefront cart button style returns correct database setting value', function () {
    Setting::create([
        'key' => 'storefront_cart_button_style',
        'value' => 'icon',
    ]);

    $response = $this->get('/');
    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->where('settings.storefront_cart_button_style', 'icon')
    );

    // Update to 'none' and check again
    Setting::where('key', 'storefront_cart_button_style')->update(['value' => 'none']);

    $response = $this->get('/');
    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->where('settings.storefront_cart_button_style', 'none')
    );
});

test('admin can save storefront cart button style setting', function () {
    $admin = User::factory()->create();

    $response = $this->actingAs($admin)
        ->post(route('admin.settings.update'), [
            'storefront_cart_button_style' => 'icon',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Pengaturan berhasil disimpan.');

    $this->assertDatabaseHas('settings', [
        'key' => 'storefront_cart_button_style',
        'value' => 'icon',
    ]);
});
