<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class UATController extends Controller
{
    /**
     * Show the UAT steps interface.
     */
    public function index()
    {
        return Inertia::render('Admin/UAT');
    }

    /**
     * Run a live UAT test step.
     */
    public function runStep(Request $request, string $step): JsonResponse
    {
        $apiKey = Setting::where('key', 'shipping_delivery_key')->value('value')
            ?? config('app.rajaongkir.shipping_delivery_key');

        $deliveryUrl = Setting::where('key', 'komerce_delivery_url')->value('value')
            ?? config('app.rajaongkir.delivery_url')
            ?? 'https://api-sandbox.collaborator.komerce.id/api/v1/';

        $parsed = parse_url($deliveryUrl);
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? 'api-sandbox.collaborator.komerce.id';
        $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';
        $rootUrl = "{$scheme}://{$host}{$port}";

        $headers = [
            'x-api-key' => $apiKey,
            'key' => $apiKey,
            'Authorization' => 'Bearer '.$apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        switch ($step) {
            case 'destination':
                $keyword = $request->input('keyword', 'cibungbulang');
                $url = "{$rootUrl}/tariff/api/v1/destination/search";
                $params = ['keyword' => $keyword];
                $fullUrl = $url.'?'.http_build_query($params);

                $curl = $this->buildCurl('GET', $fullUrl, $headers);

                try {
                    $response = Http::withHeaders($headers)->get($url, $params);
                    $result = $response->json();
                } catch (\Exception $e) {
                    $result = ['error' => $e->getMessage()];
                }
                break;

            case 'calculate':
                $url = "{$rootUrl}/tariff/api/v1/calculate";
                $params = [
                    'shipper_destination_id' => (int) $request->input('shipper_destination_id', 3578), // Surabaya / Wonokromo
                    'receiver_destination_id' => (int) $request->input('receiver_destination_id', 69363), // Wonokromo
                    'weight' => (float) $request->input('weight', 1.0),
                    'item_value' => (int) $request->input('item_value', 100000),
                    'cod' => $request->input('cod', 'no'),
                ];
                $fullUrl = $url.'?'.http_build_query($params);

                $curl = $this->buildCurl('GET', $fullUrl, $headers);

                try {
                    $response = Http::withHeaders($headers)->get($url, $params);
                    $result = $response->json();
                } catch (\Exception $e) {
                    $result = ['error' => $e->getMessage()];
                }
                break;

            case 'order':
                $transaction = Transaction::with(['customerAddress', 'items', 'paymentMethod'])->latest()->first();

                if (! $transaction) {
                    return response()->json([
                        'curl' => '-',
                        'result' => [
                            'error' => 'Tidak ada data transaksi di database. Silakan lakukan checkout terlebih dahulu.',
                        ],
                    ]);
                }

                $url = "{$rootUrl}/order/api/v1/orders/store";
                $payload = $this->getMockStorePayload($transaction);

                $curl = $this->buildCurl('POST', $url, $headers, $payload);

                try {
                    $response = Http::withHeaders($headers)->post($url, $payload);
                    $result = $response->json();
                } catch (\Exception $e) {
                    $result = ['error' => $e->getMessage()];
                }
                break;

            case 'pickup':
                $transaction = Transaction::whereNotNull('booking_code')->latest()->first()
                    ?? Transaction::latest()->first();

                if (! $transaction) {
                    return response()->json([
                        'curl' => '-',
                        'result' => [
                            'error' => 'Tidak ada data transaksi untuk diuji pickup.',
                        ],
                    ]);
                }

                $url = "{$rootUrl}/order/api/v1/pickup/request";
                $payload = [
                    'pickup_date' => now()->addDay()->format('Y-m-d'),
                    'pickup_time' => '14:00',
                    'pickup_vehicle' => 'Motor',
                    'orders' => [
                        [
                            'order_no' => $transaction->booking_code ?? 'BOOK-MOCK12345',
                        ],
                    ],
                ];

                $curl = $this->buildCurl('POST', $url, $headers, $payload);

                try {
                    $response = Http::withHeaders($headers)->post($url, $payload);
                    $result = $response->json();
                } catch (\Exception $e) {
                    $result = ['error' => $e->getMessage()];
                }
                break;

            case 'print_label':
                $transaction = Transaction::whereNotNull('booking_code')->latest()->first()
                    ?? Transaction::latest()->first();

                if (! $transaction) {
                    return response()->json([
                        'curl' => '-',
                        'result' => [
                            'error' => 'Tidak ada data transaksi untuk diuji cetak label.',
                        ],
                    ]);
                }

                $bookingCode = $transaction->booking_code ?? 'BOOK-MOCK12345';
                $url = "{$rootUrl}/order/api/v1/orders/print-label";
                $fullUrl = $url.'?'.http_build_query([
                    'order_no' => $bookingCode,
                    'page' => 'page_5',
                ]);

                $curl = $this->buildCurl('POST', $fullUrl, $headers);

                try {
                    $response = Http::withHeaders($headers)->post($fullUrl);
                    $result = $response->json();
                } catch (\Exception $e) {
                    $result = ['error' => $e->getMessage()];
                }
                break;

            case 'history_awb':
                $transaction = Transaction::whereNotNull('tracking_number')->latest()->first()
                    ?? Transaction::latest()->first();

                if (! $transaction) {
                    return response()->json([
                        'curl' => '-',
                        'result' => [
                            'error' => 'Tidak ada data transaksi untuk diuji pelacakan resi.',
                        ],
                    ]);
                }

                $url = "{$rootUrl}/order/api/v1/orders/history-airway-bill";
                $params = [
                    'airway_bill' => $transaction->tracking_number ?? 'RES-MOCK12345',
                    'shipping' => strtolower($transaction->shipping_courier ?? 'jne'),
                ];
                $fullUrl = $url.'?'.http_build_query($params);

                $curl = $this->buildCurl('GET', $fullUrl, $headers);

                try {
                    $response = Http::withHeaders($headers)->get($url, $params);
                    $result = $response->json();
                } catch (\Exception $e) {
                    $result = ['error' => $e->getMessage()];
                }
                break;

            default:
                return response()->json(['error' => 'Langkah UAT tidak valid.'], 400);
        }

        return response()->json([
            'curl' => $curl,
            'result' => $result,
        ]);
    }

    /**
     * Construct copy-pasteable curl command string.
     */
    private function buildCurl(string $method, string $url, array $headers, ?array $payload = null): string
    {
        $curl = "curl -X {$method} \"{$url}\"";
        foreach ($headers as $key => $val) {
            $curl .= " -H \"{$key}: {$val}\"";
        }
        if ($payload && $method !== 'GET') {
            $jsonPayload = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $curl .= " -d '".str_replace("'", "'\\''", $jsonPayload)."'";
        }

        return $curl;
    }

    /**
     * Map a transaction model to Komerce shipping store payload representation.
     */
    private function getMockStorePayload(Transaction $transaction): array
    {
        $address = $transaction->customerAddress;
        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storePhone = Setting::where('key', 'store_phone')->value('value') ?? '081234567890';
        $storeAddress = Setting::where('key', 'address')->value('value') ?? 'Alamat Toko';
        $storeEmail = Setting::where('key', 'store_email')->value('value') ?? 'store@example.com';

        $orderDetails = $transaction->items->map(function ($item) {
            return [
                'product_name' => $item->product_name,
                'qty' => $item->quantity,
                'product_price' => (int) $item->harga_jual,
                'subtotal' => (int) $item->subtotal,
            ];
        })->toArray();

        return [
            'order_date' => now()->format('Y-m-d'),
            'brand_name' => $storeName,
            'shipper_name' => $storeName,
            'shipper_phone' => $storePhone,
            'shipper_destination_id' => 3578,
            'shipper_address' => $storeAddress,
            'shipper_email' => $storeEmail,
            'origin_pin_point' => '-7.274631, 109.207174',
            'receiver_name' => $address->receiver_name ?? 'Customer Test',
            'receiver_phone' => $address->phone_number ?? '08123456789',
            'receiver_destination_id' => 69363,
            'receiver_address' => $address ? ($address->full_address ?? 'Jl. Mawar No 10') : 'Jl. Mawar No 10',
            'destination_pin_point' => '-7.274631, 109.207174',
            'shipping' => strtoupper($transaction->shipping_courier ?? 'JNE'),
            'shipping_type' => $transaction->shipping_service ?? 'REG',
            'payment_method' => 'BANK TRANSFER',
            'shipping_cost' => (int) $transaction->shipping_fee,
            'shipping_cashback' => 0,
            'service_fee' => 0,
            'additional_cost' => 0,
            'discount' => 0,
            'grand_total' => (int) $transaction->grand_total,
            'cod_value' => 0,
            'is_insurance' => 0,
            'insurance_value' => 0,
            'order_details' => $orderDetails,
        ];
    }
}
