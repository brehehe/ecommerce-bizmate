<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('inertia shared settings has pwa_install_enabled', function () {
    config(['app.pwa_install_enabled' => true]);

    $response = $this->get(route('home'));

    $response->assertStatus(200);
    $inertiaProps = $response->original->getData()['page']['props'];
    expect($inertiaProps['settings']['pwa_install_enabled'])->toBeTrue();
});

test('inertia shared settings honors pwa_install_enabled false', function () {
    config(['app.pwa_install_enabled' => false]);

    $response = $this->get(route('home'));

    $response->assertStatus(200);
    $inertiaProps = $response->original->getData()['page']['props'];
    expect($inertiaProps['settings']['pwa_install_enabled'])->toBeFalse();
});
