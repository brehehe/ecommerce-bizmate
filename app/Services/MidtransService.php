<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MidtransService
{
    /**
     * Available Midtrans Core API payment types.
     *
     * @var array<string, array{label: string, icon: string, bank?: string, type: string, setting_key: string}>
     */
    public static array $paymentTypes = [
        'bca_va' => [
            'label' => 'BCA Virtual Account',
            'icon' => 'bank_bca',
            'bank' => 'bca',
            'type' => 'bank_transfer',
            'setting_key' => 'midtrans_bca_va_enabled',
        ],
        'bni_va' => [
            'label' => 'BNI Virtual Account',
            'icon' => 'bank_bni',
            'bank' => 'bni',
            'type' => 'bank_transfer',
            'setting_key' => 'midtrans_bni_va_enabled',
        ],
        'bri_va' => [
            'label' => 'BRI Virtual Account',
            'icon' => 'bank_bri',
            'bank' => 'bri',
            'type' => 'bank_transfer',
            'setting_key' => 'midtrans_bri_va_enabled',
        ],
        'mandiri_bill' => [
            'label' => 'Mandiri Bill Payment',
            'icon' => 'bank_mandiri',
            'bank' => 'mandiri',
            'type' => 'echannel',
            'setting_key' => 'midtrans_mandiri_enabled',
        ],
        'permata_va' => [
            'label' => 'Permata Virtual Account',
            'icon' => 'bank_permata',
            'bank' => 'permata',
            'type' => 'bank_transfer',
            'setting_key' => 'midtrans_permata_va_enabled',
        ],
        'cimb_va' => [
            'label' => 'CIMB Niaga Virtual Account',
            'icon' => 'bank_cimb',
            'bank' => 'cimb',
            'type' => 'bank_transfer',
            'setting_key' => 'midtrans_cimb_va_enabled',
        ],
        'danamon_va' => [
            'label' => 'Danamon Virtual Account',
            'icon' => 'bank_danamon',
            'bank' => 'danamon',
            'type' => 'bank_transfer',
            'setting_key' => 'midtrans_danamon_va_enabled',
        ],
        'qris' => [
            'label' => 'QRIS (Semua E-Wallet)',
            'icon' => 'qris',
            'bank' => null,
            'type' => 'qris',
            'setting_key' => 'midtrans_qris_enabled',
        ],
        'gopay' => [
            'label' => 'GoPay',
            'icon' => 'gopay',
            'bank' => null,
            'type' => 'gopay',
            'setting_key' => 'midtrans_gopay_enabled',
        ],
        'shopeepay' => [
            'label' => 'ShopeePay',
            'icon' => 'shopeepay',
            'bank' => null,
            'type' => 'shopeepay',
            'setting_key' => 'midtrans_shopeepay_enabled',
        ],
        'indomaret' => [
            'label' => 'Indomaret',
            'icon' => 'indomaret',
            'bank' => null,
            'type' => 'cstore',
            'setting_key' => 'midtrans_indomaret_enabled',
        ],
        'alfamart' => [
            'label' => 'Alfamart / Alfamidi / Dan+Dan',
            'icon' => 'alfamart',
            'bank' => null,
            'type' => 'cstore',
            'setting_key' => 'midtrans_alfamart_enabled',
        ],
        'credit_card' => [
            'label' => 'Kartu Kredit / Debit (Visa, Mastercard)',
            'icon' => 'credit_card',
            'bank' => null,
            'type' => 'credit_card',
            'setting_key' => 'midtrans_credit_card_enabled',
        ],
    ];

    /**
     * Get the Midtrans server key from settings.
     */
    public static function getServerKey(): string
    {
        return KomerceService::getSetting('midtrans_server_key', 'app.midtrans.server_key') ?? '';
    }

    /**
     * Get the Midtrans Core API base URL.
     */
    public static function getCoreApiBaseUrl(): string
    {
        $snapUrl = KomerceService::getSetting('midtrans_snap_url', 'app.midtrans.snap_url')
            ?? 'https://app.sandbox.midtrans.com';

        if (str_contains($snapUrl, 'sandbox')) {
            return 'https://api.sandbox.midtrans.com';
        }

        return 'https://api.midtrans.com';
    }

    /**
     * Charge a payment via Midtrans Core API.
     *
     * @param  array{name: string, email: string, phone?: string}  $customer
     * @return array{success: bool, payment_type?: string, payment_key?: string, data?: array, raw?: array, error?: string}
     */
    public static function charge(
        string $orderId,
        int $grossAmount,
        string $paymentTypeKey,
        array $customer,
    ): array {
        $config = self::$paymentTypes[$paymentTypeKey] ?? null;

        if (! $config) {
            return ['success' => false, 'error' => "Unknown payment type: {$paymentTypeKey}"];
        }

        // For Credit Card, we request a Snap transaction redirect instead of direct Core API charge
        // to avoid PCI-DSS and security issues for the merchant.
        if ($config['type'] === 'credit_card') {
            return self::createSnapSessionForCard($orderId, $grossAmount, $customer);
        }

        $serverKey = self::getServerKey();
        $baseUrl = self::getCoreApiBaseUrl();
        $endpoint = rtrim($baseUrl, '/').'/v2/charge';

        $payload = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $grossAmount,
            ],
            'customer_details' => [
                'first_name' => $customer['name'],
                'email' => $customer['email'],
                'phone' => $customer['phone'] ?? '',
            ],
        ];

        switch ($config['type']) {
            case 'bank_transfer':
                $payload['payment_type'] = 'bank_transfer';
                $payload['bank_transfer'] = ['bank' => $config['bank']];
                break;

            case 'echannel':
                $payload['payment_type'] = 'echannel';
                $payload['echannel'] = [
                    'bill_info1' => 'Pembayaran:',
                    'bill_info2' => 'Pesanan #'.$orderId,
                ];
                break;

            case 'qris':
                $payload['payment_type'] = 'qris';
                break;

            case 'gopay':
                $payload['payment_type'] = 'gopay';
                $payload['gopay'] = [
                    'enable_callback' => true,
                    'callback_url' => url('/transactions'),
                ];
                break;

            case 'shopeepay':
                $payload['payment_type'] = 'shopeepay';
                $payload['shopeepay'] = [
                    'callback_url' => url('/transactions'),
                ];
                break;

            case 'cstore':
                $payload['payment_type'] = 'cstore';
                $payload['cstore'] = [
                    'store' => $paymentTypeKey === 'indomaret' ? 'indomaret' : 'alfamart',
                    'message' => 'Pembayaran Pesanan #'.$orderId,
                ];
                break;
        }

        try {
            $response = Http::withBasicAuth($serverKey, '')
                ->timeout(15)
                ->post($endpoint, $payload);

            $raw = $response->json() ?? [];

            if ($response->successful() && isset($raw['status_code']) && in_array($raw['status_code'], ['200', '201'])) {
                return [
                    'success' => true,
                    'payment_type' => $config['type'],
                    'payment_key' => $paymentTypeKey,
                    'data' => self::parseResponse($raw, $config, $paymentTypeKey),
                    'raw' => $raw,
                ];
            }

            $errorMsg = $raw['status_message'] ?? ($raw['error_messages'][0] ?? 'Gagal menghubungi Midtrans Core API.');
            Log::error('Midtrans Core API charge failed', [
                'order_id' => $orderId,
                'payment_type' => $paymentTypeKey,
                'status' => $response->status(),
                'body' => $raw,
            ]);

            return ['success' => false, 'error' => $errorMsg, 'raw' => $raw];
        } catch (\Exception $e) {
            Log::error('Midtrans Core API exception', [
                'order_id' => $orderId,
                'message' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Parse Midtrans response into a normalised structure for the frontend.
     *
     * @param  array<string, mixed>  $raw
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private static function parseResponse(array $raw, array $config, string $paymentTypeKey): array
    {
        $data = [
            'type' => $config['type'],
            'payment_key' => $paymentTypeKey,
            'label' => $config['label'],
        ];

        switch ($config['type']) {
            case 'bank_transfer':
                $vaNumbers = $raw['va_numbers'] ?? [];
                if (! empty($vaNumbers)) {
                    $data['va_number'] = $vaNumbers[0]['va_number'] ?? '';
                    $data['bank'] = strtoupper($vaNumbers[0]['bank'] ?? $config['bank']);
                } elseif (isset($raw['permata_va_number'])) {
                    $data['va_number'] = $raw['permata_va_number'];
                    $data['bank'] = 'PERMATA';
                }
                $data['expiry_time'] = $raw['expiry_time'] ?? null;
                break;

            case 'echannel':
                $data['bill_key'] = $raw['bill_key'] ?? '';
                $data['biller_code'] = $raw['biller_code'] ?? '';
                $data['bank'] = 'MANDIRI';
                $data['expiry_time'] = $raw['expiry_time'] ?? null;
                break;

            case 'qris':
                $data['qr_string'] = $raw['qr_string'] ?? '';
                $data['qr_image'] = ! empty($raw['actions']) ? ($raw['actions'][0]['url'] ?? '') : '';
                $data['expiry_time'] = $raw['expiry_time'] ?? null;
                break;

            case 'gopay':
            case 'shopeepay':
                $actions = $raw['actions'] ?? [];
                foreach ($actions as $action) {
                    if ($action['name'] === 'generate-qr-code') {
                        $data['qr_image'] = $action['url'] ?? '';
                    } elseif ($action['name'] === 'deeplink-redirect') {
                        $data['deeplink'] = $action['url'] ?? '';
                    }
                }
                $data['expiry_time'] = $raw['expiry_time'] ?? null;
                break;

            case 'cstore':
                $data['payment_code'] = $raw['payment_code'] ?? '';
                $data['store'] = $raw['store'] ?? ($paymentTypeKey === 'indomaret' ? 'indomaret' : 'alfamart');
                $data['expiry_time'] = $raw['expiry_time'] ?? null;
                break;
        }

        return $data;
    }

    /**
     * Create a Snap session for Credit Card integration (avoids raw card tokenization).
     *
     * @param  array{name: string, email: string, phone?: string}  $customer
     * @return array{success: bool, payment_type?: string, payment_key?: string, data?: array, raw?: array, error?: string}
     */
    private static function createSnapSessionForCard(string $orderId, int $grossAmount, array $customer): array
    {
        $serverKey = self::getServerKey();
        $baseUrl = KomerceService::getSetting('midtrans_snap_url', 'app.midtrans.snap_url')
            ?? 'https://app.sandbox.midtrans.com';
        $endpoint = rtrim($baseUrl, '/').'/snap/v1/transactions';

        $payload = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $grossAmount,
            ],
            'customer_details' => [
                'first_name' => $customer['name'],
                'email' => $customer['email'],
                'phone' => $customer['phone'] ?? '',
            ],
            'enabled_payments' => ['credit_card'],
        ];

        try {
            $response = Http::withBasicAuth($serverKey, '')
                ->timeout(15)
                ->post($endpoint, $payload);

            $raw = $response->json() ?? [];

            if ($response->successful() && isset($raw['token'])) {
                return [
                    'success' => true,
                    'payment_type' => 'credit_card',
                    'payment_key' => 'credit_card',
                    'data' => [
                        'type' => 'credit_card',
                        'payment_key' => 'credit_card',
                        'label' => 'Kartu Kredit / Debit (Visa, Mastercard)',
                        'snap_token' => $raw['token'],
                        'redirect_url' => $raw['redirect_url'],
                    ],
                    'raw' => [
                        'transaction_id' => $raw['token'],
                        'redirect_url' => $raw['redirect_url'],
                        'token' => $raw['token'],
                    ],
                ];
            }

            $errorMsg = $raw['error_messages'][0] ?? 'Gagal membuat sesi kartu kredit Midtrans.';

            return ['success' => false, 'error' => $errorMsg, 'raw' => $raw];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get a list of enabled Midtrans Core API payment methods from settings.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getEnabledMethods(): array
    {
        $enabled = [];
        $keys = array_column(array_values(self::$paymentTypes), 'setting_key');
        $settings = Setting::whereIn('key', $keys)->pluck('value', 'key')->toArray();

        foreach (self::$paymentTypes as $key => $config) {
            if (($settings[$config['setting_key']] ?? '0') === '1') {
                $enabled[] = [
                    'key' => $key,
                    'label' => $config['label'],
                    'icon' => $config['icon'],
                    'type' => $config['type'],
                    'bank' => $config['bank'] ?? null,
                ];
            }
        }

        return $enabled;
    }

    /**
     * Get all setting keys used by Midtrans.
     *
     * @return array<int, string>
     */
    public static function getAllSettingKeys(): array
    {
        return array_merge(
            ['midtrans_api_enabled', 'midtrans_server_key', 'midtrans_client_key', 'midtrans_snap_url', 'midtrans_admin_fee'],
            array_column(array_values(self::$paymentTypes), 'setting_key')
        );
    }
}
