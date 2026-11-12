<?php

test('google login configuration can be toggled via config', function () {
    // 1. When disabled
    config(['services.google.enabled' => false]);

    $response = $this->get('/login');
    $response->assertInertia(fn ($page) => $page
        ->where('app_config.google_login_enabled', false)
        ->where('googleLoginEnabled', false)
    );

    // Direct visit to /auth/google should be blocked
    $redirectResponse = $this->get('/auth/google');
    $redirectResponse->assertRedirect('/login')
        ->assertSessionHasErrors(['email' => 'Fitur Masuk dengan Google sedang dinonaktifkan.']);

    // 2. When enabled
    config(['services.google.enabled' => true]);

    $response = $this->get('/login');
    $response->assertInertia(fn ($page) => $page
        ->where('app_config.google_login_enabled', true)
        ->where('googleLoginEnabled', true)
    );
});
