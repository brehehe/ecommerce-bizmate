<?php

use App\Actions\Checkout\ProcessCheckoutAction;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\CustomerAddress;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use App\Models\User;
use App\Services\WarisQrisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'name' => 'Budi Santoso',
        'email' => 'budi@example.com',
        'phone_number' => '081234567890',
    ]);

    $this->category = Category::create([
        'name' => 'Elektronik',
        'slug' => 'elektronik',
        'icon' => 'ti-cpu',
    ]);

    $this->product = Product::create([
        'name' => 'Earphone TWS Pro',
        'slug' => 'earphone-tws-pro',
        'sku' => 'TWS-01',
        'category_id' => $this->category->id,
        'summary' => 'Summary TWS',
        'description' => 'Description TWS',
        'active' => true,
    ]);

    $this->product->productPrice()->create(['price' => 75000, 'cost' => 50000]);
    $this->product->productStock()->create(['stock' => 50, 'is_unlimited' => false]);

    $this->address = CustomerAddress::create([
        'user_id' => $this->user->id,
        'receiver_name' => 'Budi Santoso',
        'phone_number' => '081234567890',
        'label' => 'Kantor',
        'full_address' => 'Jl. Sudirman Kav. 50',
        'province_name' => 'DKI Jakarta',
        'regency_name' => 'Jakarta Selatan',
        'district_name' => 'Setiabudi',
        'village_name' => 'Karet',
        'postal_code' => '12920',
        'is_primary' => true,
    ]);

    $this->paymentMethod = PaymentMethod::create([
        'name' => 'QRIS (Semua Pembayaran / E-Wallet)',
        'type' => 'gateway',
        'api_key' => 'wrs_live_test_api_key_123',
        'api_secret' => 'test-webhook-secret-xyz',
        'is_active' => true,
        'admin_fee' => 0,
        'settings' => [
            'gateway' => 'waris',
            'url' => 'https://analisis.giradia.id/api/v1',
            'qris_code' => 'random',
        ],
    ]);

    Config::set('services.waris.enabled', true);
    Config::set('services.waris.url', 'https://analisis.giradia.id/api/v1');
    Config::set('services.waris.api_key', 'wrs_live_test_api_key_123');
    Config::set('services.waris.webhook_secret', 'test-webhook-secret-xyz');
    Config::set('services.waris.default_qris_code', 'random');
});

test('it identifies waris qris payment method correctly', function () {
    expect(WarisQrisService::isWarisPaymentMethod($this->paymentMethod))->toBeTrue();

    $manualMethod = PaymentMethod::create([
        'name' => 'Transfer Manual BCA',
        'type' => 'manual',
    ]);
    expect(WarisQrisService::isWarisPaymentMethod($manualMethod))->toBeFalse();

    $midtransMethod = PaymentMethod::create([
        'name' => 'Midtrans Snap',
        'type' => 'gateway',
    ]);
    expect(WarisQrisService::isWarisPaymentMethod($midtransMethod))->toBeFalse();
});

test('it generates dynamic qris successfully via waris service', function () {
    $transaction = Transaction::create([
        'transaction_number' => 'TRX-WARIS-TEST-001',
        'user_id' => $this->user->id,
        'customer_address_id' => $this->address->id,
        'payment_method_id' => $this->paymentMethod->id,
        'status' => 'belum_bayar',
        'subtotal' => 75000,
        'grand_total' => 75000,
        'shipping_courier' => 'jne',
        'shipping_service' => 'REG',
    ]);

    Http::fake([
        'https://analisis.giradia.id/api/v1/qris/generate' => Http::response([
            'success' => true,
            'message' => 'Dynamic QRIS successfully generated',
            'data' => [
                'reference_id' => 'TRX-WARIS-TEST-001',
                'amount' => 75000,
                'final_amount' => 75000,
                'fee_charged' => 400,
                'qr_string' => '00020101021226500016ID.CO.QRIS.WWW01189360000201123456785204581253033605802ID5914Toko Bizmate6007JAKARTA61051292062070703A016304A1B2',
                'qr_image_url' => 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIi8+PC9zdmc+',
                'expires_at' => now()->addMinutes(30)->toIso8601String(),
                'qris_source' => [
                    'qris_code' => 'QRS-BCA-01',
                    'label' => 'QRIS Kasir BCA Toko Utama',
                    'nmid' => 'ID102003891023',
                ],
            ],
        ], 200),
    ]);

    $result = WarisQrisService::generateDynamicQris($transaction);

    expect($result['success'])->toBeTrue()
        ->and($result['reference_id'])->toBe('TRX-WARIS-TEST-001')
        ->and($result['amount'])->toBe(75000)
        ->and($result['qr_string'])->toContain('00020101021226')
        ->and($result['qr_image_url'])->toContain('data:image/svg+xml;base64')
        ->and($result['qris_source']['nmid'])->toBe('ID102003891023');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://analisis.giradia.id/api/v1/qris/generate'
            && $request->hasHeader('X-API-KEY', 'wrs_live_test_api_key_123')
            && $request['amount'] === 75000
            && $request['reference_id'] === 'TRX-WARIS-TEST-001';
    });
});

test('it handles qris generation error gracefully', function () {
    $transaction = Transaction::create([
        'transaction_number' => 'TRX-WARIS-FAIL-001',
        'user_id' => $this->user->id,
        'customer_address_id' => $this->address->id,
        'payment_method_id' => $this->paymentMethod->id,
        'status' => 'belum_bayar',
        'subtotal' => 100000,
        'grand_total' => 100000,
        'shipping_courier' => 'jne',
        'shipping_service' => 'REG',
    ]);

    Http::fake([
        'https://analisis.giradia.id/api/v1/qris/generate' => Http::response([
            'success' => false,
            'error_code' => 'INSUFFICIENT_BALANCE',
            'message' => 'Saldo akun Anda tidak mencukupi untuk generate QRIS.',
        ], 402),
    ]);

    $result = WarisQrisService::generateDynamicQris($transaction);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('Saldo');
});

test('it verifies webhook signature with sha256 prefix', function () {
    $payload = json_encode(['event' => 'payment.received', 'data' => ['reference_id' => 'TRX-001']]);
    $secret = 'test-webhook-secret-xyz';
    $validSignature = 'sha256='.hash_hmac('sha256', $payload, $secret);

    expect(WarisQrisService::verifyWebhookSignature($payload, $validSignature))->toBeTrue()
        ->and(WarisQrisService::verifyWebhookSignature($payload, 'sha256=invalidhash12345'))->toBeFalse();
});

test('it successfully processes waris payment webhook and marks transaction paid', function () {
    $transaction = Transaction::create([
        'transaction_number' => 'TRX-WARIS-SUCCESS-001',
        'user_id' => $this->user->id,
        'customer_address_id' => $this->address->id,
        'payment_method_id' => $this->paymentMethod->id,
        'status' => 'belum_bayar',
        'payment_status' => 'belum_lunas',
        'subtotal' => 75000,
        'grand_total' => 75000,
        'shipping_courier' => 'jne',
        'shipping_service' => 'REG',
    ]);

    $payment = TransactionPayment::create([
        'transaction_id' => $transaction->id,
        'payment_method_id' => $this->paymentMethod->id,
        'amount' => 75000,
        'status' => 'pending',
        'gateway_transaction_id' => 'TRX-WARIS-SUCCESS-001',
        'gateway_status' => 'PENDING',
        'gateway_response' => json_encode(['qris_string' => 'mock_qris_string']),
    ]);

    $payload = [
        'event' => 'payment.received',
        'data' => [
            'reference_id' => 'TRX-WARIS-SUCCESS-001',
            'amount' => 75000,
            'paid_at' => now()->toIso8601String(),
            'payment_method' => 'BCA_QRIS',
            'bank_sender' => 'BCA Mobile',
            'status' => 'paid',
        ],
    ];

    $rawContent = json_encode($payload);
    $signature = 'sha256='.hash_hmac('sha256', $rawContent, 'test-webhook-secret-xyz');

    $response = $this->call(
        'POST',
        '/api/webhooks/waris',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WARIS_SIGNATURE' => $signature,
        ],
        $rawContent
    );

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'acknowledged',
            'message' => 'Payment processed successfully.',
        ]);

    $transaction->refresh();
    $payment->refresh();

    expect($transaction->status)->toBe('diproses')
        ->and($transaction->payment_status)->toBe('lunas')
        ->and($payment->status)->toBe('confirmed')
        ->and($payment->gateway_status)->toBe('PAID')
        ->and($payment->confirmed_at)->not->toBeNull();

    expect($transaction->statusHistories()->where('status', 'diproses')->exists())->toBeTrue();
});

test('it rejects waris webhook with invalid signature', function () {
    $payload = [
        'event' => 'payment.received',
        'data' => ['reference_id' => 'TRX-001', 'amount' => 10000],
    ];
    $rawContent = json_encode($payload);

    $response = $this->call(
        'POST',
        '/api/webhooks/waris',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WARIS_SIGNATURE' => 'sha256=forged_signature_here',
        ],
        $rawContent
    );

    $response->assertStatus(401)
        ->assertJson(['status' => 'error']);
});

test('it returns 404 when transaction reference is not found in webhook', function () {
    $payload = [
        'event' => 'payment.received',
        'data' => [
            'reference_id' => 'TRX-UNKNOWN-99999',
            'amount' => 50000,
            'status' => 'paid',
        ],
    ];

    $rawContent = json_encode($payload);
    $signature = 'sha256='.hash_hmac('sha256', $rawContent, 'test-webhook-secret-xyz');

    $response = $this->call(
        'POST',
        '/api/webhooks/waris',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WARIS_SIGNATURE' => $signature,
        ],
        $rawContent
    );

    $response->assertStatus(404)
        ->assertJson(['status' => 'error', 'message' => 'Transaction not found.']);
});

test('it can check account balance and list qris via service', function () {
    Http::fake([
        'https://analisis.giradia.id/api/v1/account/balance' => Http::response([
            'success' => true,
            'balance' => 250000,
            'fee_per_generate' => 400,
            'estimated_remaining_generations' => 625,
        ], 200),
        'https://analisis.giradia.id/api/v1/qris/list' => Http::response([
            'success' => true,
            'data' => [
                ['qris_code' => 'QRS-BCA-01', 'label' => 'QRIS Kasir BCA'],
                ['qris_code' => 'QRS-DANA-02', 'label' => 'QRIS Dana Bisnis'],
            ],
        ], 200),
    ]);

    $balanceRes = WarisQrisService::checkBalance();
    expect($balanceRes['success'])->toBeTrue()
        ->and($balanceRes['data']['balance'])->toBe(250000);

    $listRes = WarisQrisService::listQris();
    expect($listRes['success'])->toBeTrue()
        ->and(count($listRes['data']))->toBe(2)
        ->and($listRes['data'][0]['qris_code'])->toBe('QRS-BCA-01');
});

test('it automatically generates waris dynamic qris during checkout', function () {
    $cartItem = CartItem::create([
        'user_id' => $this->user->id,
        'product_id' => $this->product->id,
        'quantity' => 2,
        'is_checked' => true,
    ]);
    $cartItem->setRelation('product', $this->product);

    Http::fake([
        'https://analisis.giradia.id/api/v1/qris/generate' => Http::response([
            'success' => true,
            'message' => 'Dynamic QRIS successfully generated',
            'data' => [
                'reference_id' => 'TRX-CHECKOUT-001',
                'amount' => 150000,
                'final_amount' => 150000,
                'fee_charged' => 400,
                'qr_string' => '00020101021226500016ID.CO.QRIS.WWW...',
                'qr_image_url' => 'data:image/svg+xml;base64,CHECKOUT_QR_CODE',
                'expires_at' => now()->addMinutes(30)->toIso8601String(),
                'qris_source' => [
                    'qris_code' => 'QRS-BCA-01',
                    'label' => 'QRIS Kasir BCA',
                    'nmid' => 'ID102003891023',
                ],
            ],
        ], 200),
    ]);

    $action = app(ProcessCheckoutAction::class);
    $transaction = $action->execute(
        $this->user,
        [
            'customer_address_id' => $this->address->id,
            'payment_method_id' => $this->paymentMethod->id,
            'shipping_courier' => 'jne',
            'shipping_service' => 'REG',
            'shipping_fee' => 10000,
        ],
        collect([$cartItem])
    );

    $payment = $transaction->payments()->latest()->first();
    expect($payment)->not->toBeNull()
        ->and($payment->gateway_status)->toBe('PENDING');

    $gatewayResp = is_array($payment->gateway_response)
        ? $payment->gateway_response
        : json_decode($payment->gateway_response, true);

    expect($gatewayResp['qr_image_url'])->toBe('data:image/svg+xml;base64,CHECKOUT_QR_CODE')
        ->and($gatewayResp['qr_string'])->toBe('00020101021226500016ID.CO.QRIS.WWW...')
        ->and($gatewayResp['qris_source']['nmid'])->toBe('ID102003891023');
});
