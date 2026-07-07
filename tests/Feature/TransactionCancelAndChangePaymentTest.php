<?php

use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeTransaction(User $user, string $status = 'belum_bayar', ?string $paymentMethodId = null): Transaction
{
    return Transaction::create([
        'transaction_number' => Transaction::generateNumber(),
        'user_id' => $user->id,
        'payment_method_id' => $paymentMethodId,
        'status' => $status,
        'subtotal' => 100000,
        'discount_amount' => 0,
        'shipping_fee' => 10000,
        'shipping_discount' => 0,
        'admin_fee' => 0,
        'application_fee' => 0,
        'grand_total' => 110000,
    ]);
}

// ─────────────────────────────────────────
// cancelTransaction
// ─────────────────────────────────────────

test('customer can cancel a belum_bayar transaction with a reason', function () {
    $user = User::factory()->create();
    $transaction = makeTransaction($user, 'belum_bayar');

    $this->actingAs($user)
        ->post("/transactions/{$transaction->id}/cancel", [
            'cancel_reason' => 'Salah pilih produk',
        ])
        ->assertRedirect("/transactions/{$transaction->id}");

    $transaction->refresh();

    expect($transaction->status)->toBe('batal')
        ->and($transaction->cancel_reason)->toBe('Salah pilih produk')
        ->and($transaction->cancelled_at)->not->toBeNull();
});

test('customer can cancel a menunggu transaction', function () {
    $user = User::factory()->create();
    $transaction = makeTransaction($user, 'menunggu');

    $this->actingAs($user)
        ->post("/transactions/{$transaction->id}/cancel", [
            'cancel_reason' => 'Sudah tidak jadi beli',
        ])
        ->assertRedirect();

    expect($transaction->fresh()->status)->toBe('batal');
});

test('cancel requires a reason', function () {
    $user = User::factory()->create();
    $transaction = makeTransaction($user, 'belum_bayar');

    $this->actingAs($user)
        ->post("/transactions/{$transaction->id}/cancel", [
            'cancel_reason' => '',
        ])
        ->assertSessionHasErrors('cancel_reason');

    expect($transaction->fresh()->status)->toBe('belum_bayar');
});

test('customer cannot cancel a diproses transaction', function () {
    $user = User::factory()->create();
    $transaction = makeTransaction($user, 'diproses');

    $this->actingAs($user)
        ->post("/transactions/{$transaction->id}/cancel", [
            'cancel_reason' => 'Mau batal',
        ])
        ->assertRedirect();

    expect($transaction->fresh()->status)->toBe('diproses');
});

test('other user cannot cancel someone elses transaction', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $transaction = makeTransaction($owner, 'belum_bayar');

    $this->actingAs($other)
        ->post("/transactions/{$transaction->id}/cancel", [
            'cancel_reason' => 'Testing',
        ])
        ->assertForbidden();
});

// ─────────────────────────────────────────
// changePaymentMethod
// ─────────────────────────────────────────

test('customer can change payment method on belum_bayar transaction', function () {
    $user = User::factory()->create();
    $oldMethod = PaymentMethod::create([
        'name' => 'Old Method',
        'type' => 'manual',
        'is_active' => true,
    ]);
    $newMethod = PaymentMethod::create([
        'name' => 'New Method',
        'type' => 'manual',
        'is_active' => true,
    ]);

    $transaction = makeTransaction($user, 'belum_bayar', $oldMethod->id);

    $this->actingAs($user)
        ->post("/transactions/{$transaction->id}/change-payment", [
            'payment_method_id' => $newMethod->id,
        ])
        ->assertRedirect("/transactions/{$transaction->id}");

    expect($transaction->fresh()->payment_method_id)->toBe($newMethod->id);
});

test('customer cannot change payment method when not belum_bayar', function () {
    $user = User::factory()->create();
    $method = PaymentMethod::create([
        'name' => 'Test',
        'type' => 'manual',
        'is_active' => true,
    ]);
    $transaction = makeTransaction($user, 'menunggu', $method->id);

    $this->actingAs($user)
        ->post("/transactions/{$transaction->id}/change-payment", [
            'payment_method_id' => $method->id,
        ])
        ->assertRedirect();

    // Status should not change, redirect means error flash
    expect($transaction->fresh()->status)->toBe('menunggu');
});

test('other user cannot change payment method of someone elses transaction', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $method = PaymentMethod::create([
        'name' => 'Test',
        'type' => 'manual',
        'is_active' => true,
    ]);
    $transaction = makeTransaction($owner, 'belum_bayar', $method->id);

    $this->actingAs($other)
        ->post("/transactions/{$transaction->id}/change-payment", [
            'payment_method_id' => $method->id,
        ])
        ->assertForbidden();
});
