<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('invalid email on registration shows a translated message, not the raw key', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'bukan-email',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertSessionHasErrors('email');

    $error = session('errors')->first('email');

    expect($error)
        ->toBeString()
        ->not->toContain('validation.')
        ->toBe('Kolom email harus berupa alamat email yang valid.');
});

test('duplicate email on registration shows a translated unique message', function () {
    User::factory()->create([
        'email' => 'sudah@ada.test',
    ]);

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'sudah@ada.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertSessionHasErrors(['email' => 'email sudah digunakan.']);
    expect(session('errors')->first('email'))->not->toContain('validation.');
});
