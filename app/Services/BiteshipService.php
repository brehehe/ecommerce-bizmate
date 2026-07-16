<?php

namespace App\Services;

use App\Models\CustomerAddress;
use App\Models\Setting;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BiteshipService
{
    /**
     * Resolve configuration setting or config fallback.
     */
    private static function getSetting(string $key, ?string $configFallback = null): ?string
    {
        $settingVal = Setting::where('key', $key)->value('value');
        if (! empty($settingVal)) {
            return $settingVal;
        }

        return $configFallback ? config($configFallback) : null;
    }

    /**
     * Get Biteship URL.
     */
    public static function getBiteshipUrl(): string
    {
        $url = self::getSetting('biteship_url', 'services.biteship.url');
        $url = ! empty($url) ? rtrim($url, '/') : 'https://api.biteship.com/v1';
        if (! str_contains($url, '/v1')) {
            $url .= '/v1';
        }

        return $url;
    }

    /**
     * Get Biteship API key / Secret Key.
     */
    public static function getBiteshipKey(): ?string
    {
        return self::getSetting('biteship_secret_key', 'services.biteship.secret_key');
    }

    /**
     * Check if Biteship integration is enabled.
     */
    public static function isEnabled(): bool
    {
        if (! config('app.logistic_enabled', true)) {
            return false;
        }

        $enabled = self::getSetting('biteship_enabled');

        return $enabled === '1' || $enabled === 'true' || $enabled === true;
    }

    /**
     * Resolve area name to Biteship specific Area ID.
     */
    public static function resolveBiteshipAreaId(string $provinceName, string $regencyName, string $districtName, ?string $zipCode = null): ?string
    {
        if (empty($districtName) && empty($regencyName) && empty($zipCode)) {
            return null;
        }

        $provinceClean = strtoupper(trim(str_ireplace(['provinsi', 'prov'], '', $provinceName)));
        $regencyClean = strtoupper(trim(str_ireplace(['kabupaten', 'kab', 'kota'], '', $regencyName)));
        $districtClean = strtoupper(trim(str_ireplace(['kecamatan', 'kec'], '', $districtName)));
        $zipClean = $zipCode ? trim($zipCode) : '';

        $cacheKey = 'biteship_area_id_'.md5("{$provinceClean}_{$regencyClean}_{$districtClean}_{$zipClean}");

        return Cache::remember($cacheKey, 86400, function () use ($regencyClean, $districtClean, $zipClean) {
            $apiKey = self::getBiteshipKey();
            if (empty($apiKey)) {
                return null;
            }

            // Build search input query: prefer district + regency
            $queryInput = trim("{$districtClean} {$regencyClean}");
            if (empty($queryInput)) {
                $queryInput = $zipClean;
            }

            try {
                $response = Http::withHeaders([
                    'authorization' => $apiKey,
                    'Accept' => 'application/json',
                ])
                    ->timeout(10)
                    ->get(self::getBiteshipUrl().'/maps/areas', [
                        'countries' => 'ID',
                        'input' => $queryInput,
                        'type' => 'single',
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $areas = $data['areas'] ?? [];

                    if (empty($areas)) {
                        // Retry with just district if combined search had no results
                        $response = Http::withHeaders([
                            'authorization' => $apiKey,
                            'Accept' => 'application/json',
                        ])
                            ->timeout(10)
                            ->get(self::getBiteshipUrl().'/maps/areas', [
                                'countries' => 'ID',
                                'input' => $districtClean ?: $regencyClean,
                                'type' => 'single',
                            ]);
                        $data = $response->json();
                        $areas = $data['areas'] ?? [];
                    }

                    if (! empty($areas)) {
                        // Find the best match
                        foreach ($areas as $area) {
                            $areaName = strtoupper($area['name'] ?? '');
                            if (str_contains($areaName, $districtClean) && str_contains($areaName, $regencyClean)) {
                                return $area['id'];
                            }
                        }

                        // Fallback to first result
                        return $areas[0]['id'];
                    }
                } else {
                    Log::warning('Biteship Maps Areas API failed: '.$response->body());
                }
            } catch (\Exception $e) {
                Log::error('Biteship Maps Areas API error: '.$e->getMessage());
            }

            return null;
        });
    }

    /**
     * Calculate Domestic Shipping Cost using Biteship.
     */
    public static function getDomesticCost(string $originRegencyId, string $destinationRegencyId, int $weight, string $courier, ?string $addressId = null, $cartItems = null): array
    {
        $apiKey = self::getBiteshipKey();
        if (empty($apiKey)) {
            return ['error' => 'API Key Biteship belum diatur.'];
        }

        // 1. Resolve store/origin area ID from store settings
        $originProvince = self::getSetting('province_name') ?? 'JAWA TIMUR';
        $originRegency = self::getSetting('regency_name') ?? 'KOTA SURABAYA';
        $originDistrict = self::getSetting('district_name') ?? 'WONOKROMO';
        $originPostal = self::getSetting('postal_code') ?? '60245';

        $originAreaId = self::resolveBiteshipAreaId($originProvince, $originRegency, $originDistrict, $originPostal);

        if (empty($originAreaId)) {
            return ['error' => 'Gagal menentukan area asal pengiriman Biteship.'];
        }

        // 2. Resolve destination area ID
        $address = null;
        if ($addressId) {
            $address = CustomerAddress::find($addressId);
        }
        if (! $address && auth()->check()) {
            $address = CustomerAddress::where('user_id', auth()->id())
                ->where('regency_id', $destinationRegencyId)
                ->orderByDesc('is_primary')
                ->first();
        }

        $destLat = null;
        $destLng = null;

        if ($address) {
            // Use pre-stored area ID if available (avoids extra Maps API call)
            if (! empty($address->biteship_area_id)) {
                $destAreaId = $address->biteship_area_id;
            } else {
                $destAreaId = self::resolveBiteshipAreaId(
                    $address->province_name ?? '',
                    $address->regency_name ?? '',
                    $address->district_name ?? '',
                    $address->postal_code
                );
            }
            $destLat = $address->latitude;
            $destLng = $address->longitude;
        } else {
            // Fallback: try resolving via destination regency name or ID if address record is not found
            $destAreaId = self::resolveBiteshipAreaId('', $destinationRegencyId, '');
        }

        if (empty($destAreaId)) {
            return ['error' => 'Gagal menentukan area tujuan pengiriman Biteship.'];
        }

        // Standardize courier code for Biteship (e.g. gojek/gosend, si_cepat/sicepat)
        $courierMap = [
            'gojek' => 'gojek',
            'gosend' => 'gojek',
            'grab' => 'grab',
            'grabexpress' => 'grab',
            'jne' => 'jne',
            'sicepat' => 'sicepat',
            'si_cepat' => 'sicepat',
            'jnt' => 'jnt',
            'j&t' => 'jnt',
            'ide' => 'idexpress',
            'idx' => 'idexpress',
            'idexpress' => 'idexpress',
            'ninja' => 'ninja',
            'lion' => 'lion',
            'sap' => 'sap',
            'anteraja' => 'anteraja',
        ];

        $courierLower = strtolower($courier);
        $biteshipCourier = $courierMap[$courierLower] ?? $courierLower;

        try {
            $itemsPayload = [];
            if (! empty($cartItems)) {
                foreach ($cartItems as $item) {
                    if ($item->product?->is_digital) {
                        continue;
                    }

                    $product = $item->product;
                    $variant = $item->productVariant ?? $item->product_variant;

                    $itemName = $product->name ?? 'Product';
                    if ($variant) {
                        $variantName = $variant->options ? $variant->options->pluck('name')->join(' / ') : '';
                        if (! empty($variantName)) {
                            $itemName .= ' - '.$variantName;
                        }
                    }

                    $itemPrice = $variant
                        ? ($variant->is_promo ? $variant->promo_price : ($variant->productPrice?->price ?? 0))
                        : ($product->is_promo ? $product->promo_price : ($product->productPrice?->price ?? 0));

                    $itemWeight = $variant->weight ?? $product->weight ?? 1000;
                    $itemLength = $variant->length ?? $product->length ?? 10;
                    $itemWidth = $variant->width ?? $product->width ?? 10;
                    $itemHeight = $variant->height ?? $product->height ?? 10;

                    // Strip HTML tags and normalize spacing
                    $cleanName = trim(strip_tags($itemName));
                    $cleanDescription = strip_tags($product->description ?? $itemName);
                    $cleanDescription = preg_replace('/\s+/', ' ', $cleanDescription);
                    $cleanDescription = trim($cleanDescription);

                    $itemsPayload[] = [
                        'name' => substr($cleanName, 0, 50),
                        'description' => substr($cleanDescription ?: $cleanName, 0, 100),
                        'value' => (int) $itemPrice,
                        'length' => (int) max(1, $itemLength),
                        'width' => (int) max(1, $itemWidth),
                        'height' => (int) max(1, $itemHeight),
                        'weight' => (int) max(1, $itemWeight),
                        'quantity' => (int) max(1, $item->quantity),
                    ];
                }
            }

            if (empty($itemsPayload)) {
                $itemsPayload[] = [
                    'weight' => max(1, $weight),
                ];
            }

            $payload = [
                'origin_area_id' => $originAreaId,
                'destination_area_id' => $destAreaId,
                'couriers' => $biteshipCourier,
                'items' => $itemsPayload,
            ];

            $originLat = self::getSetting('latitude');
            $originLng = self::getSetting('longitude');

            if (! empty($originLat) && ! empty($originLng)) {
                $payload['origin_latitude'] = (float) $originLat;
                $payload['origin_longitude'] = (float) $originLng;
            }

            if (! empty($destLat) && ! empty($destLng)) {
                $payload['destination_latitude'] = (float) $destLat;
                $payload['destination_longitude'] = (float) $destLng;
            }

            Log::info('Biteship rates request:', [
                'url' => self::getBiteshipUrl().'/rates/couriers',
                'payload' => $payload,
            ]);

            $response = Http::withHeaders([
                'authorization' => $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
                ->timeout(15)
                ->post(self::getBiteshipUrl().'/rates/couriers', $payload);

            Log::info('Biteship rates response status: '.$response->status());

            if ($response->successful()) {
                $data = $response->json();
                $results = [];

                if (isset($data['pricing']) && is_array($data['pricing'])) {
                    $grouped = [];
                    foreach ($data['pricing'] as $item) {
                        $companyCode = $item['courier_code'] ?? $item['company'] ?? '';
                        if (empty($companyCode)) {
                            continue;
                        }
                        $companyName = $item['courier_name'] ?? strtoupper($companyCode);

                        if (! isset($grouped[$companyCode])) {
                            $grouped[$companyCode] = [
                                'code' => $companyCode,
                                'name' => $companyName,
                                'costs' => [],
                            ];
                        }

                        $serviceCode = $item['courier_service_code'] ?? $item['type'] ?? $item['service_type'] ?? 'reg';
                        $serviceName = $item['courier_service_name'] ?? $item['service_type'] ?? 'Regular';
                        $grouped[$companyCode]['costs'][] = [
                            'service' => "[{$serviceCode}] {$serviceName}",
                            'description' => $item['description'] ?? ($item['courier_service_name'] ?? 'Regular'),
                            'cost' => [
                                [
                                    'value' => (float) ($item['price'] ?? $item['shipping_fee'] ?? 0),
                                    'etd' => $item['duration'] ?? '',
                                    'note' => '',
                                ],
                            ],
                        ];
                    }
                    $results = array_values($grouped);
                } else {
                    // Parse couriers array (might be "couriers" or "courier")
                    $couriersList = $data['couriers'] ?? $data['courier'] ?? [];

                    foreach ($couriersList as $courierItem) {
                        $companyCode = $courierItem['company_code'] ?? $courierItem['code'] ?? '';
                        $companyName = $courierItem['company_name'] ?? $courierItem['name'] ?? strtoupper($companyCode);
                        $services = $courierItem['available_services'] ?? [];

                        $costs = [];
                        foreach ($services as $service) {
                            $serviceCode = $service['service_code'] ?? $service['service'] ?? $service['service_code'] ?? 'reg';
                            $serviceName = $service['service_name'] ?? $service['service'] ?? 'Regular';
                            $costs[] = [
                                'service' => "[{$serviceCode}] {$serviceName}",
                                'description' => $service['description'] ?? ($service['service_name'] ?? 'Regular'),
                                'cost' => [
                                    [
                                        'value' => (float) ($service['price'] ?? 0),
                                        'etd' => $service['etd'] ?? '',
                                        'note' => '',
                                    ],
                                ],
                            ];
                        }

                        if (! empty($costs)) {
                            $results[] = [
                                'code' => $companyCode,
                                'name' => $companyName,
                                'costs' => $costs,
                            ];
                        }
                    }
                }

                return ['results' => $results];
            }

            Log::warning('Biteship Rates API failed: '.$response->body());

            $errorMsg = $response->json('error') ?? $response->json('message') ?? 'Gagal mengambil data ongkir dari Biteship.';
            if (str_contains(strtolower($errorMsg), 'no courier available') || str_contains(strtolower($errorMsg), 'out of coverage') || str_contains(strtolower($errorMsg), 'not active')) {
                $errorMsg = 'Layanan tidak tersedia untuk kurir ini di wilayah Anda (Area Tidak Terjangkau / Jarak Terlalu Jauh).';
            }

            return ['error' => $errorMsg];
        } catch (\Exception $e) {
            Log::error('BiteshipService getDomesticCost error: '.$e->getMessage());

            return ['error' => 'Gagal terhubung ke layanan Biteship.'];
        }
    }

    /**
     * Search destination in Biteship Maps Areas API.
     */
    public static function searchDestination(string $keyword): array
    {
        $apiKey = self::getBiteshipKey();
        if (empty($apiKey)) {
            return ['error' => 'API Key Biteship belum diatur.'];
        }

        try {
            $response = Http::withHeaders([
                'authorization' => $apiKey,
                'Accept' => 'application/json',
            ])
                ->timeout(10)
                ->get(self::getBiteshipUrl().'/maps/areas', [
                    'countries' => 'ID',
                    'input' => $keyword,
                    'type' => 'single',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $areas = $data['areas'] ?? [];

                // Map to match Komerce response format
                $mappedData = [];
                foreach ($areas as $area) {
                    $mappedData[] = [
                        'id' => $area['id'] ?? '',
                        'city_name' => $area['administrative_division_level_2_name'] ?? '',
                        'district_name' => $area['administrative_division_level_3_name'] ?? '',
                        'zip_code' => (string) ($area['postal_code'] ?? ''),
                        'postal_code' => (string) ($area['postal_code'] ?? ''),
                        'label' => $area['name'] ?? '',
                    ];
                }

                return ['success' => true, 'data' => $mappedData];
            }

            Log::warning('Biteship Maps Areas search failed: '.$response->body());

            return ['error' => $response->json('message') ?? 'Gagal mencari destinasi dari Biteship.'];
        } catch (\Exception $e) {
            Log::error('BiteshipService Search Destination Error: '.$e->getMessage());

            return ['error' => 'Gagal terhubung ke Biteship.'];
        }
    }

    /**
     * Book/Store Shipment in Biteship.
     */
    public static function storeShipment(Transaction $transaction): array
    {
        $apiKey = self::getBiteshipKey();
        if (empty($apiKey)) {
            return ['error' => 'API Key Biteship belum diatur.'];
        }

        $address = $transaction->customerAddress;
        if (! $address) {
            return ['error' => 'Alamat pengiriman transaksi tidak ditemukan.'];
        }

        // Fetch store settings for sender/origin info
        $storeName = self::getSetting('store_name') ?? config('app.name');
        $storePhone = self::getSetting('store_phone') ?? '081234567890';
        $storeAddress = self::getSetting('address') ?? self::getSetting('store_address') ?? 'Alamat Toko';
        $storeEmail = self::getSetting('store_email') ?? 'store@example.com';
        $storeLat = self::getSetting('latitude') ?? '-7.274631';
        $storeLng = self::getSetting('longitude') ?? '109.207174';

        $originProvince = self::getSetting('province_name') ?? 'JAWA TIMUR';
        $originRegency = self::getSetting('regency_name') ?? 'KOTA SURABAYA';
        $originDistrict = self::getSetting('district_name') ?? 'WONOKROMO';
        $originPostal = self::getSetting('postal_code') ?? '60245';

        $originAreaId = self::resolveBiteshipAreaId($originProvince, $originRegency, $originDistrict, $originPostal);
        if (empty($originAreaId)) {
            return ['error' => 'Gagal menentukan area asal pengiriman Biteship.'];
        }

        // Resolve destination area ID
        if (! empty($address->biteship_area_id)) {
            $destAreaId = $address->biteship_area_id;
        } else {
            $destAreaId = self::resolveBiteshipAreaId(
                $address->province_name ?? '',
                $address->regency_name ?? '',
                $address->district_name ?? '',
                $address->postal_code
            );
        }

        if (empty($destAreaId)) {
            return ['error' => 'Gagal menentukan area tujuan pengiriman Biteship.'];
        }

        // Normalize Courier
        $courierMap = [
            'gojek' => 'gojek',
            'gosend' => 'gojek',
            'grab' => 'grab',
            'grabexpress' => 'grab',
            'jne' => 'jne',
            'sicepat' => 'sicepat',
            'jnt' => 'jnt',
            'j&t' => 'jnt',
            'idexpress' => 'idexpress',
            'idx' => 'idexpress',
            'ninja' => 'ninja',
            'lion' => 'lion',
            'sap' => 'sap',
            'anteraja' => 'anteraja',
        ];
        $courierCompany = $courierMap[strtolower($transaction->shipping_courier)] ?? strtolower($transaction->shipping_courier);

        // Normalize Service Type
        $serviceLower = strtolower($transaction->shipping_service ?? '');
        $courierType = 'reg';
        if (preg_match('/\[(.*?)\]/', $transaction->shipping_service, $matches)) {
            $courierType = $matches[1];
        } elseif (str_contains($serviceLower, 'instant')) {
            $courierType = 'instant';
        } elseif (str_contains($serviceLower, 'same day') || str_contains($serviceLower, 'sameday')) {
            $courierType = 'same_day';
        } elseif (str_contains($serviceLower, 'cargo') || str_contains($serviceLower, 'gokil')) {
            $courierType = 'cargo';
        } elseif (str_contains($serviceLower, 'yes') || str_contains($serviceLower, 'best') || str_contains($serviceLower, 'fast')) {
            $courierType = 'next_day';
        } elseif (! empty($serviceLower)) {
            if (! str_contains($serviceLower, ' ') && preg_match('/^[a-z0-9_\-]+$/', $serviceLower)) {
                $courierType = $serviceLower;
            }
        }

        // Build items list
        $itemsPayload = [];
        $transaction->loadMissing(['items.product', 'items.productVariant']);
        foreach ($transaction->items as $item) {
            if ($item->product?->is_digital) {
                continue;
            }

            $product = $item->product;
            $variant = $item->productVariant;
            $itemName = $product->name ?? 'Product';
            if ($variant) {
                $variantName = $variant->options ? $variant->options->pluck('name')->join(' / ') : '';
                if (! empty($variantName)) {
                    $itemName .= ' - '.$variantName;
                }
            }

            $itemPrice = $item->harga_akhir ?? $item->harga_jual ?? 10000;
            $itemWeight = $variant->weight ?? $product->weight ?? 1000;
            $itemLength = $variant->length ?? $product->length ?? 10;
            $itemWidth = $variant->width ?? $product->width ?? 10;
            $itemHeight = $variant->height ?? $product->height ?? 10;

            $itemsPayload[] = [
                'name' => substr(trim(strip_tags($itemName)), 0, 50),
                'description' => substr(trim(strip_tags($product->description ?? $itemName)), 0, 100) ?: 'Goods',
                'value' => (int) $itemPrice,
                'length' => (int) max(1, $itemLength),
                'width' => (int) max(1, $itemWidth),
                'height' => (int) max(1, $itemHeight),
                'weight' => (int) max(1, $itemWeight),
                'quantity' => (int) max(1, $item->quantity),
            ];
        }

        if (empty($itemsPayload)) {
            $itemsPayload[] = [
                'name' => 'Paket Belanja',
                'description' => 'Paket Belanja',
                'value' => (int) $transaction->subtotal,
                'weight' => (int) max(1, $transaction->total_weight_grams ?? 1000),
                'quantity' => 1,
            ];
        }

        $payload = [
            'origin_contact_name' => substr($storeName, 0, 50),
            'origin_contact_phone' => $storePhone,
            'origin_address' => $storeAddress,
            'origin_postal_code' => (int) $originPostal,
            'origin_coordinate' => [
                'latitude' => (float) $storeLat,
                'longitude' => (float) $storeLng,
            ],
            'destination_contact_name' => substr($address->receiver_name, 0, 50),
            'destination_contact_phone' => $address->phone_number,
            'destination_contact_email' => $transaction->user->email ?? $storeEmail,
            'destination_address' => $address->full_address,
            'destination_postal_code' => (int) $address->postal_code,
            'courier_company' => $courierCompany,
            'courier_type' => $courierType,
            'delivery_type' => 'now',
            'reference_id' => $transaction->transaction_number.'-'.time(),
            'items' => $itemsPayload,
        ];

        if ($address->latitude && $address->longitude) {
            $payload['destination_coordinate'] = [
                'latitude' => (float) $address->latitude,
                'longitude' => (float) $address->longitude,
            ];
        }

        try {
            Log::info('Biteship create order request:', [
                'payload' => $payload,
            ]);

            $response = Http::withHeaders([
                'authorization' => $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
                ->timeout(15)
                ->post(self::getBiteshipUrl().'/orders', $payload);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('Biteship create order success:', [
                    'response' => $data,
                ]);

                return [
                    'success' => true,
                    'data' => [
                        'booking_code' => $data['id'] ?? null,
                        'airway_bill' => $data['courier']['waybill_id'] ?? null,
                    ],
                ];
            }

            Log::warning('Biteship create order failed: '.$response->body());

            return ['error' => $response->json('error') ?? $response->json('message') ?? 'Gagal membuat pesanan pengiriman di Biteship.'];
        } catch (\Exception $e) {
            Log::error('BiteshipService storeShipment error: '.$e->getMessage());

            return ['error' => 'Gagal terhubung ke Biteship.'];
        }
    }

    /**
     * Cancel shipment order in Biteship.
     */
    public static function cancelShipment(string $bookingCode): array
    {
        $apiKey = self::getBiteshipKey();
        if (empty($apiKey)) {
            return ['error' => 'API Key Biteship belum diatur.'];
        }

        try {
            $response = Http::withHeaders([
                'authorization' => $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
                ->timeout(15)
                ->post(self::getBiteshipUrl()."/orders/{$bookingCode}/cancel", [
                    'cancellation_reason' => 'Dibatalkan oleh penjual.',
                ]);

            if ($response->successful()) {
                return ['success' => true];
            }

            Log::warning('Biteship cancel order failed: '.$response->body());

            return ['error' => $response->json('error') ?? $response->json('message') ?? 'Gagal membatalkan pesanan pengiriman di Biteship.'];
        } catch (\Exception $e) {
            Log::error('BiteshipService cancelShipment error: '.$e->getMessage());

            return ['error' => 'Gagal terhubung ke Biteship.'];
        }
    }

    /**
     * Get shipment status tracking history.
     */
    public static function getShipmentHistory(string $airwayBill, ?string $courier = null): array
    {
        $apiKey = self::getBiteshipKey();

        if (! empty($apiKey) && $airwayBill !== '000000000000' && ! str_starts_with(strtoupper($airwayBill), 'KOMERKOM')) {
            $courierCode = $courier ? strtolower($courier) : 'jne';
            // Map courier name
            $courierMap = [
                'gojek' => 'gojek',
                'gosend' => 'gojek',
                'grab' => 'grab',
                'grabexpress' => 'grab',
                'jne' => 'jne',
                'sicepat' => 'sicepat',
                'jnt' => 'jnt',
                'j&t' => 'jnt',
                'idexpress' => 'idexpress',
                'idx' => 'idexpress',
                'ninja' => 'ninja',
                'lion' => 'lion',
                'sap' => 'sap',
                'anteraja' => 'anteraja',
            ];
            $courierCode = $courierMap[$courierCode] ?? $courierCode;

            try {
                $response = Http::withHeaders([
                    'authorization' => $apiKey,
                    'Accept' => 'application/json',
                ])
                    ->timeout(15)
                    ->get(self::getBiteshipUrl()."/trackings/{$airwayBill}/couriers/{$courierCode}");

                if ($response->successful()) {
                    $data = $response->json();
                    $rawHistory = $data['history'] ?? [];

                    if (! empty($rawHistory)) {
                        $history = [];
                        foreach ($rawHistory as $event) {
                            $history[] = [
                                'date' => Carbon::parse($event['updated_at'])->toDateTimeString(),
                                'desc' => $event['note'] ?? $event['status'] ?? '',
                            ];
                        }

                        return [
                            'success' => true,
                            'history' => $history,
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Biteship real-time tracking failed or timed out: '.$e->getMessage());
            }
        }

        // Fallback simulated tracking for testing/staging environments
        $transaction = Transaction::where('tracking_number', $airwayBill)->first();
        $status = $transaction ? $transaction->status : 'diproses';

        $history = [
            ['date' => now()->subHours(5)->toDateTimeString(), 'desc' => 'Paket telah diserahkan ke kurir (Staging/Biteship)'],
            ['date' => now()->subHours(2)->toDateTimeString(), 'desc' => 'Paket sedang transit di hub logistik'],
        ];

        if ($status === 'dikirim' || $status === 'selesai') {
            $history[] = ['date' => now()->subHours(1)->toDateTimeString(), 'desc' => 'Paket sedang dalam perjalanan ke alamat penerima'];
            $history[] = ['date' => now()->toDateTimeString(), 'desc' => 'Paket telah diterima oleh Ybs (Simulasi Biteship)'];
        } else {
            $history[] = ['date' => now()->toDateTimeString(), 'desc' => 'Paket sedang dalam perjalanan ke alamat penerima'];
        }

        return [
            'success' => true,
            'simulated' => true,
            'history' => $history,
        ];
    }

    /**
     * Get detailed order info from Biteship.
     */
    public static function getOrderDetail(string $bookingCode): array
    {
        $apiKey = self::getBiteshipKey();
        if (empty($apiKey)) {
            return ['error' => 'API Key Biteship belum diatur.'];
        }

        try {
            $response = Http::withHeaders([
                'authorization' => $apiKey,
                'Accept' => 'application/json',
            ])
                ->timeout(15)
                ->get(self::getBiteshipUrl()."/orders/{$bookingCode}");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return ['error' => $response->json('error') ?? $response->json('message') ?? 'Gagal mengambil detail order dari Biteship.'];
        } catch (\Exception $e) {
            Log::error('BiteshipService getOrderDetail error: '.$e->getMessage());

            return ['error' => 'Gagal terhubung ke Biteship.'];
        }
    }
}
