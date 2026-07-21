<?php

use App\Models\CustomerAddress;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $user = User::factory()->create();

    $address = CustomerAddress::create([
        'user_id' => $user->id,
        'receiver_name' => 'Test User',
        'phone_number' => '08123456789',
        'label' => 'Rumah',
        'full_address' => 'Jl. Test No. 1',
        'province_name' => 'Jawa Timur',
        'regency_name' => 'Surabaya',
        'district_name' => 'Wonokromo',
        'village_name' => 'Ngagelrejo',
        'postal_code' => '60245',
        'is_primary' => true,
    ]);

    $paymentMethod = PaymentMethod::create([
        'name' => 'Transfer BCA',
        'type' => 'manual',
        'is_active' => true,
        'admin_fee' => 0,
    ]);

    $this->transaction = Transaction::create([
        'transaction_number' => Transaction::generateNumber(),
        'user_id' => $user->id,
        'customer_address_id' => $address->id,
        'payment_method_id' => $paymentMethod->id,
        'status' => 'diproses',
        'subtotal' => 100000,
        'shipping_fee' => 15000,
        'grand_total' => 115000,
        'booking_code' => 'IDEM-BOOK-001',
        'tracking_number' => 'IDEM-AWB-001',
    ]);
});

// ============================================================
// BITESHIP IDEMPOTENCY
// ============================================================

test('biteship webhook skips duplicate shipping event and does not re-update status', function () {
    // First call — should be processed and status updated to dikirim
    $first = $this->postJson(route('api.biteship.webhook'), [
        'order_id' => 'IDEM-BOOK-001',
        'status' => 'picked_up',
    ]);

    $first->assertOk();
    $this->transaction->refresh();
    expect($this->transaction->status)->toBe('dikirim');

    // Manually set status back to diproses to prove second call won't re-update it
    $this->transaction->update(['status' => 'diproses']);

    // Second call with identical payload — should be a duplicate and skipped
    $second = $this->postJson(route('api.biteship.webhook'), [
        'order_id' => 'IDEM-BOOK-001',
        'status' => 'picked_up',
    ]);

    $second->assertOk();
    $second->assertJsonFragment(['message' => 'Webhook already processed (duplicate skipped)']);

    // Status must NOT be changed again
    $this->transaction->refresh();
    expect($this->transaction->status)->toBe('diproses');

    // Only one WebhookEvent row should exist for this key
    expect(WebhookEvent::where('idempotency_key', 'biteship:IDEM-BOOK-001:picked_up')->count())->toBe(1);
});

test('biteship webhook records event to webhook_events table after processing', function () {
    $this->postJson(route('api.biteship.webhook'), [
        'order_id' => 'IDEM-BOOK-001',
        'status' => 'delivered',
    ])->assertOk();

    expect(WebhookEvent::where('idempotency_key', 'biteship:IDEM-BOOK-001:delivered')->exists())->toBeTrue();
    expect(WebhookEvent::where('source', 'biteship')->count())->toBe(1);
});

test('biteship webhook allows different statuses for the same order to be processed separately', function () {
    $this->postJson(route('api.biteship.webhook'), [
        'order_id' => 'IDEM-BOOK-001',
        'status' => 'picked_up',
    ])->assertOk();

    $this->postJson(route('api.biteship.webhook'), [
        'order_id' => 'IDEM-BOOK-001',
        'status' => 'delivered',
    ])->assertOk();

    // Two different events should each be recorded
    expect(WebhookEvent::where('source', 'biteship')->count())->toBe(2);
});

// ============================================================
// KOMERCE IDEMPOTENCY
// ============================================================

test('komerce webhook skips duplicate shipping event and does not re-update status', function () {
    // First call — shipping → dikirim
    $first = $this->postJson(route('api.komerce.webhook'), [
        'booking_code' => 'IDEM-BOOK-001',
        'status' => 'shipping',
    ]);

    $first->assertOk();
    $this->transaction->refresh();
    expect($this->transaction->status)->toBe('dikirim');

    // Reset status to verify second call won't change it
    $this->transaction->update(['status' => 'diproses']);

    // Second call — duplicate, must be skipped
    $second = $this->postJson(route('api.komerce.webhook'), [
        'booking_code' => 'IDEM-BOOK-001',
        'status' => 'shipping',
    ]);

    $second->assertOk();
    $second->assertJsonFragment(['message' => 'Webhook already processed (duplicate skipped)']);

    $this->transaction->refresh();
    expect($this->transaction->status)->toBe('diproses');

    expect(WebhookEvent::where('idempotency_key', 'komerce:shipping:IDEM-BOOK-001:shipping')->count())->toBe(1);
});

test('komerce webhook records event to webhook_events table after shipping processing', function () {
    $this->postJson(route('api.komerce.webhook'), [
        'booking_code' => 'IDEM-BOOK-001',
        'status' => 'delivered',
    ])->assertOk();

    expect(WebhookEvent::where('source', 'komerce')->where('idempotency_key', 'ilike', 'komerce:shipping:%')->exists())->toBeTrue();
});

test('komerce webhook allows different statuses for the same booking code', function () {
    $this->postJson(route('api.komerce.webhook'), [
        'booking_code' => 'IDEM-BOOK-001',
        'status' => 'shipping',
    ])->assertOk();

    $this->postJson(route('api.komerce.webhook'), [
        'booking_code' => 'IDEM-BOOK-001',
        'status' => 'delivered',
    ])->assertOk();

    expect(WebhookEvent::where('source', 'komerce')->count())->toBe(2);
});

// ============================================================
// MODEL HELPERS
// ============================================================

test('WebhookEvent::alreadyProcessed returns false for new key and true for existing key', function () {
    expect(WebhookEvent::alreadyProcessed('test:key:new'))->toBeFalse();

    WebhookEvent::record('test', 'test:key:new', [], 200);

    expect(WebhookEvent::alreadyProcessed('test:key:new'))->toBeTrue();
});

test('WebhookEvent::record is idempotent and does not create duplicates on repeated calls', function () {
    WebhookEvent::record('biteship', 'biteship:dup-key:status', ['foo' => 'bar'], 200);
    WebhookEvent::record('biteship', 'biteship:dup-key:status', ['foo' => 'bar'], 200);
    WebhookEvent::record('biteship', 'biteship:dup-key:status', ['foo' => 'bar'], 200);

    expect(WebhookEvent::where('idempotency_key', 'biteship:dup-key:status')->count())->toBe(1);
});
