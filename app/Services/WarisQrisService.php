<?php

namespace App\Services;

use App\Models\PaymentMethod;
use App\Models\Setting;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WarisQrisService
{
    /**
     * Check if WARIS QRIS integration is enabled.
     */
    public static function isEnabled(): bool
    {
        $settingValue = Setting::where('key', 'waris_api_enabled')->value('value');
        if ($settingValue !== null) {
            return $settingValue === '1' || $settingValue === 'true';
        }

        return (bool) config('services.waris.enabled', true);
    }

    /**
     * Get the API base URL.
     */
    public static function getBaseUrl(): string
    {
        $settingUrl = Setting::where('key', 'waris_api_url')->value('value');
        if (! empty($settingUrl)) {
            return rtrim((string) $settingUrl, '/');
        }

        return rtrim((string) config('services.waris.url', 'https://analisis.giradia.id/api/v1'), '/');
    }

    /**
     * Get the WARIS API Key.
     */
    public static function getApiKey(): ?string
    {
        $settingKey = Setting::where('key', 'waris_api_key')->value('value');
        if (! empty($settingKey)) {
            return (string) $settingKey;
        }

        $configKey = config('services.waris.api_key');

        return ! empty($configKey) ? (string) $configKey : null;
    }

    /**
     * Get the Webhook HMAC Secret.
     */
    public static function getWebhookSecret(): ?string
    {
        $settingSecret = Setting::where('key', 'waris_webhook_secret')->value('value');
        if (! empty($settingSecret)) {
            return (string) $settingSecret;
        }

        $configSecret = config('services.waris.webhook_secret');

        return ! empty($configSecret) ? (string) $configSecret : null;
    }

    /**
     * Get the default QRIS code identifier.
     */
    public static function getDefaultQrisCode(): string
    {
        $settingCode = Setting::where('key', 'waris_qris_code')->value('value');
        if (! empty($settingCode)) {
            return (string) $settingCode;
        }

        $configCode = config('services.waris.default_qris_code');

        return ! empty($configCode) ? (string) $configCode : 'random';
    }

    /**
     * Check whether a given payment method is powered by WARIS QRIS.
     */
    public static function isWarisPaymentMethod(?PaymentMethod $method): bool
    {
        if (! $method) {
            return false;
        }

        $name = strtolower((string) $method->name);
        $settingsGateway = strtolower((string) ($method->settings['gateway'] ?? ''));

        if ($method->type === 'waris_qris' || $settingsGateway === 'waris' || str_contains($name, 'waris')) {
            return true;
        }

        // Standard gateway QRIS without third-party prefix
        if (
            $method->type === 'gateway' &&
            str_contains($name, 'qris') &&
            ! str_contains($name, 'komerce') &&
            ! str_contains($name, 'midtrans')
        ) {
            return true;
        }

        return false;
    }

    /**
     * Generate Dynamic QRIS for a Transaction.
     *
     * @return array{
     *     success: bool,
     *     reference_id?: string,
     *     amount?: int,
     *     final_amount?: int,
     *     fee_charged?: int,
     *     qr_string?: string,
     *     qr_image_url?: string,
     *     expires_at?: ?string,
     *     qris_source?: ?array<string, mixed>,
     *     error?: string,
     *     raw?: array<string, mixed>
     * }
     */
    public static function generateDynamicQris(
        Transaction $transaction,
        ?string $qrisCode = null,
        string $format = 'data_uri'
    ): array {
        $apiKey = self::getApiKey();

        if (empty($apiKey)) {
            Log::warning('WarisQrisService: API key is not configured.');

            return [
                'success' => false,
                'error' => 'API Key QRIS belum dikonfigurasi pada sistem.',
            ];
        }

        $url = self::getBaseUrl().'/qris/generate';
        $amount = (int) round((float) $transaction->grand_total);
        $referenceId = (string) $transaction->transaction_number;
        $qrisCode = $qrisCode ?: self::getDefaultQrisCode();

        $payload = [
            'amount' => $amount,
            'reference_id' => $referenceId,
            'qris_code' => $qrisCode,
            'format' => $format,
        ];

        try {
            $callbackUrl = url('/api/webhooks/waris');
            if (! empty($callbackUrl) && ! str_contains($callbackUrl, 'localhost')) {
                $payload['callback_url'] = $callbackUrl;
            }
        } catch (\Throwable) {
            // Keep default payload
        }

        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->timeout(15)->post($url, $payload);

            $data = $response->json();

            if ($response->successful() && ($data['success'] ?? false) === true) {
                $qrisData = $data['data'] ?? [];

                $qrString = (string) ($qrisData['qr_string'] ?? '');
                $qrImage = (string) (
                    $qrisData['qr_image_data_uri']
                    ?? $qrisData['qr_image_url']
                    ?? $qrisData['image']
                    ?? $qrisData['qr_image']
                    ?? ''
                );

                if (empty($qrImage) && ! empty($qrString)) {
                    $qrImage = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data='.urlencode($qrString);
                }

                $qrisSource = $qrisData['qris_source'] ?? [
                    'qris_code' => $qrisData['qris_code_used'] ?? null,
                    'label' => $qrisData['merchant_name'] ?? null,
                    'nmid' => $qrisData['nmid'] ?? null,
                ];

                return [
                    'success' => true,
                    'reference_id' => $qrisData['reference_id'] ?? $referenceId,
                    'amount' => (int) ($qrisData['amount'] ?? $amount),
                    'final_amount' => (int) ($qrisData['final_amount'] ?? $amount),
                    'fee_charged' => (int) ($qrisData['fee_charged'] ?? 0),
                    'qr_string' => $qrString,
                    'qr_image_url' => $qrImage,
                    'expires_at' => $qrisData['expires_at'] ?? null,
                    'qris_source' => $qrisSource,
                    'raw' => is_array($data) ? $data : [],
                ];
            }

            $errorMessage = $data['message'] ?? 'Gagal membuat QRIS dinamis.';
            if (($data['error_code'] ?? null) === 'INSUFFICIENT_BALANCE') {
                $errorMessage = 'Saldo akun gateway QRIS tidak mencukupi untuk membuat dynamic QRIS.';
            }

            Log::error('WarisQrisService: Failed to generate QRIS', [
                'status' => $response->status(),
                'response' => $data,
                'reference_id' => $referenceId,
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
                'raw' => is_array($data) ? $data : [],
            ];
        } catch (\Throwable $e) {
            Log::error('WarisQrisService: Exception during QRIS generation', [
                'error' => $e->getMessage(),
                'reference_id' => $referenceId,
            ]);

            return [
                'success' => false,
                'error' => 'Koneksi ke gateway QRIS mengalami kendala: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Check real-time payment status of an invoice / reference_id from the gateway API.
     * Endpoint: GET /qris/status/{reference_id}
     *
     * @return array{
     *     success: bool,
     *     is_paid: bool,
     *     status?: string,
     *     paid_at?: ?string,
     *     amount?: int,
     *     error?: string,
     *     data?: array<string, mixed>
     * }
     */
    public static function checkTransactionStatus(string $referenceId): array
    {
        $apiKey = self::getApiKey();
        $url = self::getBaseUrl().'/qris/status/'.urlencode($referenceId);

        try {
            $request = Http::timeout(10)->withHeaders([
                'Accept' => 'application/json',
            ]);

            if (! empty($apiKey)) {
                $request = $request->withHeaders(['X-API-KEY' => $apiKey]);
            }

            $response = $request->get($url);
            $data = $response->json();

            if ($response->successful() && ($data['success'] ?? false) === true) {
                $statusData = $data['data'] ?? [];
                $isPaid = (bool) ($statusData['is_paid'] ?? false);
                $statusStr = strtoupper((string) ($statusData['status'] ?? 'PENDING'));

                return [
                    'success' => true,
                    'is_paid' => $isPaid || $statusStr === 'PAID',
                    'status' => $statusStr,
                    'paid_at' => $statusData['paid_at'] ?? null,
                    'amount' => (int) ($statusData['amount'] ?? 0),
                    'data' => $statusData,
                ];
            }

            return [
                'success' => false,
                'is_paid' => false,
                'error' => $data['message'] ?? 'Status transaksi belum tersedia di gateway.',
                'data' => is_array($data) ? $data : [],
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'is_paid' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check account balance and remaining generations.
     *
     * @return array{success: bool, data?: array<string, mixed>, error?: string}
     */
    public static function checkBalance(): array
    {
        $apiKey = self::getApiKey();

        if (empty($apiKey)) {
            return [
                'success' => false,
                'error' => 'API Key belum dikonfigurasi.',
            ];
        }

        $url = self::getBaseUrl().'/account/balance';

        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $apiKey,
                'Accept' => 'application/json',
            ])->timeout(10)->get($url);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'error' => $response->json('message') ?? 'Gagal mengambil informasi saldo.',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * List registered static QRIS codes.
     *
     * @return array{success: bool, data?: array<int, mixed>, error?: string}
     */
    public static function listQris(): array
    {
        $apiKey = self::getApiKey();

        if (empty($apiKey)) {
            return [
                'success' => false,
                'error' => 'API Key belum dikonfigurasi.',
            ];
        }

        $url = self::getBaseUrl().'/qris/list';

        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $apiKey,
                'Accept' => 'application/json',
            ])->timeout(10)->get($url);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json('data') ?? $response->json() ?? [],
                ];
            }

            return [
                'success' => false,
                'error' => $response->json('message') ?? 'Gagal mengambil daftar QRIS.',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify incoming webhook HMAC-SHA256 signature.
     */
    public static function verifyWebhookSignature(string $payload, ?string $signatureHeader): bool
    {
        $secret = self::getWebhookSecret();

        // If secret is not set in config/db, reject for security
        if (empty($secret) || empty($signatureHeader)) {
            return false;
        }

        $cleanHeader = trim($signatureHeader);
        $expectedHmac = hash_hmac('sha256', $payload, $secret);
        $expectedWithPrefix = 'sha256='.$expectedHmac;

        if (str_starts_with($cleanHeader, 'sha256=')) {
            return hash_equals($expectedWithPrefix, $cleanHeader);
        }

        return hash_equals($expectedHmac, $cleanHeader);
    }

    /**
     * Ensure the WARIS QRIS payment method is synced in the database.
     */
    public static function syncPaymentMethod(): ?PaymentMethod
    {
        try {
            $enabled = self::isEnabled();
            $adminFee = (float) (Setting::where('key', 'waris_admin_fee')->value('value') ?? 0);

            if ($enabled) {
                return PaymentMethod::updateOrCreate(
                    ['name' => 'QRIS (Semua Pembayaran / E-Wallet)'],
                    [
                        'type' => 'gateway',
                        'api_key' => self::getApiKey() ?? '',
                        'api_secret' => self::getWebhookSecret() ?? '',
                        'admin_fee' => $adminFee,
                        'is_active' => true,
                        'settings' => [
                            'gateway' => 'waris',
                            'url' => self::getBaseUrl(),
                            'qris_code' => self::getDefaultQrisCode(),
                        ],
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::error('WarisQrisService: syncPaymentMethod error: '.$e->getMessage());
        }

        return null;
    }
}
