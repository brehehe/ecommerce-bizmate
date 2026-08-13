<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('show_brands setting defaults to true when not set in database', function () {
    $response = $this->get('/');
    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->where('settings.show_brands', true)
    );
});

test('show_brands setting returns false when set to 0 in database', function () {
    Setting::create([
        'key' => 'show_brands',
        'value' => '0',
    ]);

    $response = $this->get('/');
    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->where('settings.show_brands', false)
    );
});

test('admin can save show_brands setting to false', function () {
    $admin = User::factory()->create();

    $response = $this->actingAs($admin)
        ->post(route('admin.settings.update'), [
            'show_brands' => false,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Pengaturan berhasil disimpan.');

    $this->assertDatabaseHas('settings', [
        'key' => 'show_brands',
        'value' => '0',
    ]);
});

test('storefront brand route remains accessible when show_brands setting is disabled', function () {
    Setting::create([
        'key' => 'show_brands',
        'value' => '0',
    ]);

    $response = $this->get(route('brands'));
    $response->assertOk();
});
