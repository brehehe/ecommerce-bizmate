<?php

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->paymentMethod = PaymentMethod::create([
        'name' => 'Transfer BCA',
        'type' => 'manual',
        'is_active' => true,
        'admin_fee' => 0,
    ]);
});

test('checkout store is blocked when CHECKOUT_LOCKED is true', function () {
    config(['app.checkout_locked' => true]);

    $response = $this->actingAs($this->user)->post(route('checkout.store'), [
        'payment_method_id' => $this->paymentMethod->id,
        'shipping_courier' => 'digital',
        'shipping_service' => 'email',
        'shipping_fee' => 0,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect(session('error'))->toContain('Checkout sedang dinonaktifkan sementara');
});

test('checkout store uses custom CHECKOUT_LOCKED_MESSAGE when set', function () {
    config([
        'app.checkout_locked' => true,
        'app.checkout_locked_message' => 'Kami sedang stok opname, mohon tunggu.',
    ]);

    $response = $this->actingAs($this->user)->post(route('checkout.store'), [
        'payment_method_id' => $this->paymentMethod->id,
        'shipping_courier' => 'digital',
        'shipping_service' => 'email',
        'shipping_fee' => 0,
    ]);

    $response->assertSessionHas('error', 'Kami sedang stok opname, mohon tunggu.');
});

test('checkout store proceeds normally when CHECKOUT_LOCKED is false', function () {
    config(['app.checkout_locked' => false]);

    // No cart items set up — expect a redirect back due to empty cart,
    // not a lock error. This confirms the lock guard was NOT triggered.
    $response = $this->actingAs($this->user)->post(route('checkout.store'), [
        'payment_method_id' => $this->paymentMethod->id,
        'shipping_courier' => 'digital',
        'shipping_service' => 'email',
        'shipping_fee' => 0,
    ]);

    $response->assertRedirect();
    // Session error should be about empty cart, NOT about checkout being locked
    expect(session('error'))->not->toContain('dinonaktifkan');
});
