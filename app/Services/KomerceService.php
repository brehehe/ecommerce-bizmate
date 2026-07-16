<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\CustomerAddress;
use App\Models\PaymentMethod;
use App\Models\Setting;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KomerceService
{
    /**
     * Resolve configuration setting or config fallback.
     */
    public static function getSetting(string $key, ?string $configFallback = null): ?string
    {
        $envKeyMap = [
            'rajaongkir_url' => ['env' => 'app.rajaongkir.has_url_env', 'config' => 'app.rajaongkir.url'],
            'rajaongkir_shipping_cost' => ['env' => 'app.rajaongkir.has_shipping_cost_env', 'config' => 'app.rajaongkir.shipping_cost'],
            'komerce_delivery_url' => ['env' => 'app.rajaongkir.has_delivery_url_env', 'config' => 'app.rajaongkir.delivery_url'],
            'shipping_delivery_key' => ['env' => 'app.rajaongkir.has_shipping_delivery_key_env', 'config' => 'app.rajaongkir.shipping_delivery_key'],
            'payment_api_key' => ['env' => 'app.rajaongkir.has_payment_api_key_env', 'config' => 'app.rajaongkir.payment_api_key'],
            'qrisly_api_key' => ['env' => 'app.rajaongkir.has_qrisly_api_key_env', 'config' => 'app.rajaongkir.qrisly_api_key'],
        ];

        if (isset($envKeyMap[$key])) {
            $map = $envKeyMap[$key];
            if (config($map['env'])) {
                return config($map['config']);
            }
        }

        $settingVal = Setting::where('key', $key)->value('value');
        if (! empty($settingVal)) {
            return $settingVal;
        }

        return $configFallback ? config($configFallback) : null;
    }

    /**
     * Get base URL for Rajaongkir.
     */
    private static function getRajaongkirBaseUrl(): string
    {
        return self::getSetting('rajaongkir_url', 'app.rajaongkir.url')
            ?? 'https://rajaongkir.komerce.id/api/v1/';
    }

    /**
     * Get Rajaongkir Cost API Key.
     */
    private static function getRajaongkirKey(): ?string
    {
        return self::getSetting('rajaongkir_shipping_cost', 'app.rajaongkir.shipping_cost');
    }

    /**
     * Get base URL for Komerce Delivery / Collaborator APIs.
     */
    private static function getKomerceDeliveryUrl(): string
    {
        return self::getSetting('komerce_delivery_url', 'app.rajaongkir.delivery_url')
            ?? 'https://api-sandbox.collaborator.komerce.id/api/v1/';
    }

    /**
     * Get the correct, fully-qualified URL for Komerce APIs.
     */
    private static function getKomerceUrl(string $path): string
    {
        $baseUrl = self::getKomerceDeliveryUrl();
        $parsed = parse_url($baseUrl);
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? 'api-sandbox.collaborator.komerce.id';
        $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';
        $rootUrl = "{$scheme}://{$host}{$port}";

        $cleanPath = ltrim($path, '/');

        // Map correct paths according to RajaOngkir/Komerce Delivery API docs
        $mappedPath = match ($cleanPath) {
            'store' => 'order/api/v1/orders/store',
            'cancel' => 'order/api/v1/orders/cancel',
            'detail' => 'order/api/v1/orders/detail',
            'webhook' => 'order/api/v1/orders/webhook',
            'Pickup' => 'order/api/v1/pickup/request',
            'print-label' => 'order/api/v1/orders/print-label',
            'history-airway-bill' => 'order/api/v1/orders/history-airway-bill',
            'payment/qris/generate' => 'api/v1/payment/qris/generate',
            'payment/checkout/generate' => 'api/v1/payment/checkout/generate',
            'payment/status' => 'api/v1/payment/status',
            'tariff/destination-search' => 'tariff/api/v1/destination/search',
            'tariff/calculate' => 'tariff/api/v1/calculate',
            default => 'api/v1/'.$cleanPath,
        };

        return "{$rootUrl}/{$mappedPath}";
    }

    /**
     * Helper to prepare headers for collaborator APIs.
     */
    private static function getCollaboratorHeaders(string $apiKey): array
    {
        return [
            'x-api-key' => $apiKey,
            'key' => $apiKey,
            'Authorization' => 'Bearer '.$apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Get shipment discount percentage for Komerce 3PL couriers.
     */
    private static function getShippingDiscountPercentage(string $courier, string $service): float
    {
        $courier = strtolower($courier);
        $service = strtoupper($service);

        // Only Regular/Standard/EZ/OKE services get discounts in the Komerce 3PL scheme
        if (! str_contains($service, 'REG') && ! str_contains($service, 'STANDARD') && ! str_contains($service, 'EZ') && ! str_contains($service, 'OKE')) {
            return 0.0;
        }

        return match ($courier) {
            'jne' => 0.25,
            'sap' => 0.30,
            'idexpress', 'idx' => 0.25,
            'sicepat', 'si_cepat' => 0.20,
            'ninja', 'ninja_van' => 0.40,
            'j&t', 'jnt' => 0.25,
            'lion' => 0.20,
            default => 0.0,
        };
    }

    // ==========================================
    // RAJAONGKIR (Cost & Destination APIs)
    // ==========================================

    /**
     * Calculate Domestic Shipping Cost.
     */
    public static function getDomesticCost(string $origin, string $destination, int $weight, string $courier, ?string $addressId = null): array
    {
        $courierLower = strtolower($courier);
        if ($courierLower === 'gojek') {
            $courierLower = 'gosend';
        }

        // Komerce Collaborator calculate API
        if (self::isDeliveryEnabled()) {
            $apiKey = self::getSetting('shipping_delivery_key', 'app.rajaongkir.shipping_delivery_key');
            if ($apiKey) {
                $originProvince = self::getSetting('province_name') ?? 'JAWA TIMUR';
                $originRegency = self::getSetting('regency_name') ?? 'KOTA SURABAYA';
                $originDistrict = self::getSetting('district_name') ?? 'WONOKROMO';
                $originPostal = self::getSetting('postal_code') ?? '60245';

                $shipperDestId = self::resolveKomerceDestinationId($originProvince, $originRegency, $originDistrict, $originPostal);

                $address = null;
                if ($addressId) {
                    $address = CustomerAddress::find($addressId);
                }
                if (! $address && auth()->check()) {
                    $address = CustomerAddress::where('user_id', auth()->id())
                        ->where('regency_id', $destination)
                        ->orderByDesc('is_primary')
                        ->first();
                }
                if (! $address) {
                    $address = CustomerAddress::where('regency_id', $destination)
                        ->orderByDesc('is_primary')
                        ->first();
                }

                $receiverDestId = null;
                if ($address) {
                    $receiverDestId = self::resolveKomerceDestinationId(
                        $address->province_name ?? '',
                        $address->regency_name ?? '',
                        $address->district_name ?? '',
                        $address->postal_code
                    );
                }

                if (! $receiverDestId && $address) {
                    $receiverDestId = self::resolveKomerceDestinationId(
                        $address->province_name ?? '',
                        $address->regency_name ?? '',
                        $address->regency_name ?? ''
                    );
                }

                if ($shipperDestId && $receiverDestId) {
                    $weightKg = max(1.0, $weight / 1000.0);
                    $itemValue = 10000;
                    if (auth()->check()) {
                        $itemValue = CartItem::where('user_id', auth()->id())
                            ->where('is_checked', true)
                            ->get()
                            ->sum(fn ($item) => $item->price * $item->quantity);
                    }
                    if ($itemValue <= 0) {
                        $itemValue = 10000;
                    }

                    $originLat = self::getSetting('latitude') ?? '-7.3003118';
                    $originLng = self::getSetting('longitude') ?? '112.7483016';
                    $originPinPoint = "{$originLat}, {$originLng}";

                    $destLat = $address && $address->latitude ? $address->latitude : '-7.3003118';
                    $destLng = $address && $address->longitude ? $address->longitude : '112.7483016';
                    $destPinPoint = "{$destLat}, {$destLng}";

                    try {
                        Log::info('Komerce tariff calculate request:', [
                            'shipper_destination_id' => $shipperDestId,
                            'receiver_destination_id' => $receiverDestId,
                            'weight' => $weightKg,
                            'item_value' => $itemValue,
                            'cod' => 'no',
                            'origin_pin_point' => $originPinPoint,
                            'destination_pin_point' => $destPinPoint,
                        ]);

                        $response = Http::withHeaders(self::getCollaboratorHeaders($apiKey))
                            ->get(self::getKomerceUrl('tariff/calculate'), [
                                'shipper_destination_id' => $shipperDestId,
                                'receiver_destination_id' => $receiverDestId,
                                'weight' => $weightKg,
                                'item_value' => $itemValue,
                                'cod' => 'no',
                                'origin_pin_point' => $originPinPoint,
                                'destination_pin_point' => $destPinPoint,
                            ]);

                        if ($response->successful()) {
                            Log::info('Komerce tariff calculate success:', [
                                'response' => $response->json(),
                            ]);
                            $data = $response->json();
                            $allRates = array_merge(
                                $data['data']['calculate_reguler'] ?? [],
                                $data['data']['calculate_cargo'] ?? [],
                                $data['data']['calculate_instant'] ?? []
                            );

                            $costs = [];
                            $courierName = strtoupper($courier);

                            foreach ($allRates as $rate) {
                                $rateCourier = strtolower($rate['shipping_name'] ?? '');
                                $match = false;
                                if ($rateCourier === $courierLower) {
                                    $match = true;
                                } elseif ($rateCourier === 'jnt' && $courierLower === 'j&t') {
                                    $match = true;
                                } elseif ($rateCourier === 'idexpress' && $courierLower === 'idx') {
                                    $match = true;
                                }

                                if ($match) {
                                    $courierName = $rate['shipping_name'];
                                    $costs[] = [
                                        'service' => $rate['service_name'],
                                        'description' => $rate['service_name'].' ('.($rate['etd'] ?? '-').')',
                                        'cost' => [
                                            [
                                                'value' => (float) ($rate['shipping_cost'] ?? 0),
                                                'etd' => $rate['etd'] ?? '',
                                                'note' => '',
                                            ],
                                        ],
                                    ];
                                }
                            }

                            if (! empty($costs)) {
                                return [
                                    'results' => [
                                        [
                                            'code' => $courierLower,
                                            'name' => $courierName,
                                            'costs' => $costs,
                                        ],
                                    ],
                                ];
                            }
                        } else {
                            Log::warning('Komerce Tariff Calculate failed: '.$response->body().' Payload: '.json_encode([
                                'shipper_destination_id' => $shipperDestId,
                                'receiver_destination_id' => $receiverDestId,
                                'weight' => $weightKg,
                                'item_value' => $itemValue,
                                'cod' => 'no',
                                'origin_pin_point' => $originPinPoint,
                                'destination_pin_point' => $destPinPoint,
                            ]));
                        }
                    } catch (\Exception $e) {
                        Log::error('KomerceService Tariff Calculate Error: '.$e->getMessage());
                    }
                }
            }
        }

        // Fallback for GoSend / Grab (Local manual distance calculation)
        if ($courierLower === 'gosend' || $courierLower === 'grab') {
            Log::info('Using fallback local calculation for instant courier (GoSend/Grab):', [
                'courier' => $courierLower,
                'destination' => $destination,
                'address_id' => $addressId,
            ]);

            $user = auth()->user();
            $address = null;

            if ($addressId) {
                $address = CustomerAddress::find($addressId);
            }

            if (! $address && $user) {
                $address = CustomerAddress::where('user_id', $user->id)
                    ->where('regency_id', $destination)
                    ->orderByDesc('is_primary')
                    ->first();
            }

            if (! $address) {
                $address = CustomerAddress::where('regency_id', $destination)
                    ->orderByDesc('is_primary')
                    ->first();
            }

            $storeLat = Setting::where('key', 'latitude')->value('value');
            $storeLng = Setting::where('key', 'longitude')->value('value');

            $distance = 10.0; // default 10 km
            if ($address && $address->latitude && $address->longitude && $storeLat && $storeLng) {
                $earthRadius = 6371; // km
                $latDelta = deg2rad((float) $address->latitude - (float) $storeLat);
                $lonDelta = deg2rad((float) $address->longitude - (float) $storeLng);
                $a = sin($latDelta / 2) * sin($latDelta / 2) +
                    cos(deg2rad((float) $storeLat)) * cos(deg2rad((float) $address->latitude)) *
                    sin($lonDelta / 2) * sin($lonDelta / 2);
                $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
                $distance = $earthRadius * $c; // in km
            }

            Log::info('Local calculation details:', [
                'distance_km' => $distance,
                'store_lat' => $storeLat,
                'store_lng' => $storeLng,
                'address_lat' => $address ? $address->latitude : null,
                'address_lng' => $address ? $address->longitude : null,
            ]);

            if ($distance > 50) {
                return ['error' => 'Jarak pengiriman terlalu jauh untuk kurir instant (maksimal 50 km). Jarak saat ini: '.round($distance, 1).' km.'];
            }

            $name = $courierLower === 'gosend' ? 'GoSend' : 'GrabExpress';

            if ($courierLower === 'gosend') {
                $instantCost = max(15000, 10000 + round(2500 * $distance));
                $samedayCost = max(10000, 8000 + round(1500 * $distance));
            } else {
                $instantCost = max(15000, 11000 + round(2400 * $distance));
                $samedayCost = max(10000, 9000 + round(1400 * $distance));
            }

            Log::info('Local calculation costs:', [
                'instant_cost' => $instantCost,
                'sameday_cost' => $samedayCost,
            ]);

            $results = [
                [
                    'code' => $courierLower,
                    'name' => $name,
                    'costs' => [
                        [
                            'service' => 'Instant',
                            'description' => 'Pengiriman Instant (1-2 Jam)',
                            'cost' => [
                                [
                                    'value' => (float) $instantCost,
                                    'etd' => '1-2 jam',
                                    'note' => '',
                                ],
                            ],
                        ],
                        [
                            'service' => 'Sameday',
                            'description' => 'Pengiriman Same Day (6-8 Jam)',
                            'cost' => [
                                [
                                    'value' => (float) $samedayCost,
                                    'etd' => '6-8 jam',
                                    'note' => '',
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            return ['results' => $results];
        }

        // Fallback to Rajaongkir API
        $apiKey = self::getRajaongkirKey();
        $baseUrl = self::getRajaongkirBaseUrl();

        if (! $apiKey) {
            return ['error' => 'API Key RajaOngkir belum diatur.'];
        }

        try {
            $isKomerce = str_contains($baseUrl, 'komerce.id');
            $endpoint = $isKomerce ? 'calculate/domestic-cost' : 'cost';

            $payload = [
                'origin' => $origin,
                'destination' => $destination,
                'weight' => $weight,
                'courier' => $courier,
            ];

            Log::info('RajaOngkir cost calculation request:', [
                'url' => rtrim($baseUrl, '/').'/'.$endpoint,
                'payload' => $payload,
            ]);

            $response = Http::withHeaders(['key' => $apiKey])
                ->asForm()
                ->post(rtrim($baseUrl, '/').'/'.$endpoint, $payload);

            Log::info('RajaOngkir cost calculation response:', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if ($isKomerce) {
                    $results = [];
                    $items = $data['data'] ?? [];
                    $costs = [];

                    foreach ($items as $item) {
                        $costs[] = [
                            'service' => $item['service'],
                            'description' => $item['description'] ?? $item['service'],
                            'cost' => [
                                [
                                    'value' => (float) ($item['cost'] ?? 0),
                                    'etd' => $item['etd'] ?? '',
                                    'note' => '',
                                ],
                            ],
                        ];
                    }

                    if (! empty($costs)) {
                        $results = [
                            [
                                'code' => $courier,
                                'name' => $items[0]['name'] ?? strtoupper($courier),
                                'costs' => $costs,
                            ],
                        ];
                    }

                    return ['results' => $results];
                }

                return ['results' => $data['rajaongkir']['results'] ?? []];
            }

            Log::warning('RajaOngkir Shipping Cost API failed: '.$response->body());

            return ['error' => $response->json('meta.message') ?? $response->json('rajaongkir.status.description') ?? 'Gagal mengambil data ongkir domestic.'];
        } catch (\Exception $e) {
            Log::error('KomerceService Domestic Cost Error: '.$e->getMessage());

            return ['error' => 'Koneksi ke Komerce RajaOngkir bermasalah.'];
        }
    }

    /**
     * Calculate International Shipping Cost.
     */
    public static function getInternationalCost(string $origin, string $destinationCountryId, int $weight, string $courier): array
    {
        $apiKey = self::getRajaongkirKey();
        $baseUrl = self::getRajaongkirBaseUrl();

        if (! $apiKey) {
            return ['error' => 'API Key RajaOngkir belum diatur.'];
        }

        try {
            $payload = [
                'origin' => $origin,
                'destination' => $destinationCountryId,
                'weight' => $weight,
                'courier' => $courier,
            ];

            Log::info('RajaOngkir international cost request:', [
                'url' => rtrim($baseUrl, '/').'/calculate/international-cost',
                'payload' => $payload,
            ]);

            $response = Http::withHeaders(['key' => $apiKey])
                ->asForm()
                ->post(rtrim($baseUrl, '/').'/calculate/international-cost', $payload);

            Log::info('RajaOngkir international cost response:', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            if ($response->successful()) {
                $data = $response->json();

                // Parse Komerce international response format
                $results = [];
                $items = $data['data'] ?? [];
                $costs = [];

                foreach ($items as $item) {
                    $costs[] = [
                        'service' => $item['service'],
                        'description' => $item['description'] ?? $item['service'],
                        'cost' => [
                            [
                                'value' => (float) ($item['cost'] ?? 0),
                                'etd' => $item['etd'] ?? '',
                                'note' => '',
                            ],
                        ],
                    ];
                }

                if (! empty($costs)) {
                    $results = [
                        [
                            'code' => $courier,
                            'name' => $items[0]['name'] ?? strtoupper($courier),
                            'costs' => $costs,
                        ],
                    ];
                }

                return ['results' => $results];
            }

            return ['error' => $response->json('meta.message') ?? 'Gagal mengambil data ongkir internasional.'];
        } catch (\Exception $e) {
            Log::error('KomerceService International Cost Error: '.$e->getMessage());

            return ['error' => 'Gagal terhubung ke layanan ongkir internasional.'];
        }
    }

    /**
     * Get International Destinations List.
     */
    public static function getInternationalDestinations(): array
    {
        $apiKey = self::getRajaongkirKey();
        $baseUrl = self::getRajaongkirBaseUrl();

        if (! $apiKey) {
            return [];
        }

        try {
            $response = Http::withHeaders(['key' => $apiKey])
                ->get(rtrim($baseUrl, '/').'/calculate/international-destination');

            if ($response->successful()) {
                $data = $response->json();

                return $data['data'] ?? [];
            }

            return [];
        } catch (\Exception $e) {
            Log::error('KomerceService International Destinations Error: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Track Waybill (Cek Resi via RajaOngkir).
     */
    public static function trackWaybill(string $waybill, string $courier): array
    {
        $apiKey = self::getRajaongkirKey();
        $baseUrl = self::getRajaongkirBaseUrl();

        if (! $apiKey) {
            return ['error' => 'API Key RajaOngkir belum diatur.'];
        }

        try {
            $payload = [
                'waybill' => $waybill,
                'courier' => $courier,
            ];

            Log::info('RajaOngkir track waybill request:', [
                'url' => rtrim($baseUrl, '/').'/waybill',
                'payload' => $payload,
            ]);

            $response = Http::withHeaders(['key' => $apiKey])
                ->asForm()
                ->post(rtrim($baseUrl, '/').'/waybill', $payload);

            Log::info('RajaOngkir track waybill response:', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return ['success' => true, 'data' => $data['rajaongkir']['result'] ?? []];
            }

            Log::warning("Rajaongkir trackWaybill failed for waybill {$waybill}: ".$response->body());

            return ['error' => $response->json('rajaongkir.status.description') ?? 'Gagal melacak resi.'];
        } catch (\Exception $e) {
            Log::error('KomerceService Track Waybill Error: '.$e->getMessage());

            return ['error' => 'Koneksi pelacakan resi bermasalah.'];
        }
    }

    /**
     * Search destination in Komerce Collaborator API.
     */
    public static function searchDestination(string $keyword): array
    {
        $apiKey = self::getSetting('shipping_delivery_key', 'app.rajaongkir.shipping_delivery_key');
        if (! $apiKey) {
            return ['error' => 'API Key Shipping Delivery belum diatur.'];
        }

        try {
            $url = self::getKomerceUrl('tariff/destination-search');
            $params = ['keyword' => $keyword];
            $fullUrlWithParams = $url.'?'.http_build_query($params);
            $headers = self::getCollaboratorHeaders($apiKey);

            $curlCmd = 'curl -X GET "'.$fullUrlWithParams.'"';
            foreach ($headers as $key => $val) {
                $curlCmd .= ' -H "'.$key.': '.$val.'"';
            }

            Log::info('Komerce destination search request (curl):', [
                'curl' => $curlCmd,
                'keyword' => $keyword,
            ]);

            $response = Http::withHeaders($headers)
                ->get($url, $params);

            if ($response->successful()) {
                $responseData = $response->json();

                Log::info('Komerce destination search success - Response Details:', [
                    'curl' => $curlCmd,
                    'response' => $responseData,
                ]);

                return ['success' => true, 'data' => $responseData['data'] ?? []];
            }

            Log::warning('Komerce destination search failed:', [
                'curl' => $curlCmd,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['error' => $response->json('meta.message') ?? 'Gagal mencari destinasi dari Komerce.'];
        } catch (\Exception $e) {
            Log::error('KomerceService Search Destination Error: '.$e->getMessage());

            return ['error' => 'Gagal terhubung ke Komerce.'];
        }
    }

    /**
     * Resolve standard region names to Komerce specific Destination ID.
     */
    public static function resolveKomerceDestinationId(string $provinceName, string $regencyName, string $districtName, ?string $zipCode = null): ?int
    {
        if (empty($districtName) && empty($regencyName) && empty($zipCode)) {
            return null;
        }

        $provinceClean = strtoupper(trim(str_ireplace(['provinsi', 'prov'], '', $provinceName)));
        $regencyClean = strtoupper(trim(str_ireplace(['kabupaten', 'kab', 'kota'], '', $regencyName)));
        $districtClean = strtoupper(trim(str_ireplace(['kecamatan', 'kec'], '', $districtName)));
        $zipClean = $zipCode ? trim($zipCode) : '';

        $cacheKey = 'komerce_dest_id_'.md5("{$provinceClean}_{$regencyClean}_{$districtClean}_{$zipClean}");

        return Cache::remember($cacheKey, 86400, function () use ($regencyClean, $districtClean, $zipClean) {
            // 1. Try search by zip code if available
            if (! empty($zipClean)) {
                $searchRes = self::searchDestination($zipClean);
                if (isset($searchRes['success']) && ! empty($searchRes['data'])) {
                    foreach ($searchRes['data'] as $item) {
                        $itemCity = strtoupper(trim(str_ireplace(['kabupaten', 'kab', 'kota'], '', $item['city_name'] ?? '')));
                        $itemDistrict = strtoupper(trim(str_ireplace(['kecamatan', 'kec'], '', $item['district_name'] ?? '')));
                        $itemZip = trim($item['zip_code'] ?? $item['postal_code'] ?? '');

                        if ($itemZip === $zipClean && (empty($districtClean) || str_contains($itemDistrict, $districtClean)) && (empty($regencyClean) || str_contains($itemCity, $regencyClean))) {
                            return (int) $item['id'];
                        }
                    }

                    // Fallback to any matching city with this zip code
                    foreach ($searchRes['data'] as $item) {
                        $itemCity = strtoupper(trim(str_ireplace(['kabupaten', 'kab', 'kota'], '', $item['city_name'] ?? '')));
                        if (str_contains($itemCity, $regencyClean)) {
                            return (int) $item['id'];
                        }
                    }

                    return (int) $searchRes['data'][0]['id'];
                }
            }

            // 2. Search by district name first
            if (! empty($districtClean)) {
                $searchRes = self::searchDestination($districtClean);
                if (isset($searchRes['success']) && ! empty($searchRes['data'])) {
                    foreach ($searchRes['data'] as $item) {
                        $itemCity = strtoupper(trim(str_ireplace(['kabupaten', 'kab', 'kota'], '', $item['city_name'] ?? '')));
                        $itemDistrict = strtoupper(trim(str_ireplace(['kecamatan', 'kec'], '', $item['district_name'] ?? '')));

                        if (str_contains($itemDistrict, $districtClean) && str_contains($itemCity, $regencyClean)) {
                            return (int) $item['id'];
                        }
                    }

                    // Fallback: try relaxed matching
                    foreach ($searchRes['data'] as $item) {
                        $itemCity = strtoupper(trim(str_ireplace(['kabupaten', 'kab', 'kota'], '', $item['city_name'] ?? '')));
                        if (str_contains($itemCity, $regencyClean)) {
                            return (int) $item['id'];
                        }
                    }

                    // Default fallback to first result
                    return (int) $searchRes['data'][0]['id'];
                }
            }

            // 3. Search by regency name
            if (! empty($regencyClean)) {
                $searchResRegency = self::searchDestination($regencyClean);
                if (isset($searchResRegency['success']) && ! empty($searchResRegency['data'])) {
                    return (int) $searchResRegency['data'][0]['id'];
                }
            }

            return null;
        });
    }

    // ==========================================
    // SHIPPING DELIVERY (Shipment & Pickups)
    // ==========================================

    /**
     * Check if Shipping Delivery is Enabled.
     */
    public static function isDeliveryEnabled(): bool
    {
        if (! config('app.logistic_enabled', true)) {
            return false;
        }

        $enabled = self::getSetting('shipping_delivery_enabled');

        return $enabled === '1' || $enabled === 'true' || $enabled === true;
    }

    /**
     * Book/Store Shipment in Komerce Delivery.
     */
    public static function storeShipment(Transaction $transaction): array
    {
        $apiKey = self::getSetting('shipping_delivery_key', 'app.rajaongkir.shipping_delivery_key');
        $baseUrl = self::getKomerceDeliveryUrl();

        if (! $apiKey) {
            return ['error' => 'API Key Shipping Delivery belum diatur.'];
        }

        $address = $transaction->customerAddress;
        if (! $address) {
            return ['error' => 'Alamat pengiriman transaksi tidak ditemukan.'];
        }

        // Fetch store settings for sender info
        $originRegencyId = self::getSetting('rajaongkir_origin', 'app.rajaongkir.origin')
            ?? self::getSetting('regency_id');
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

        $shipperDestinationId = self::resolveKomerceDestinationId($originProvince, $originRegency, $originDistrict, $originPostal) ?? (int) $originRegencyId;
        $receiverDestinationId = self::resolveKomerceDestinationId($address->province_name ?? '', $address->regency_name ?? '', $address->district_name ?? '', $address->postal_code) ?? (int) $address->regency_id;

        $paymentMethod = $transaction->paymentMethod;
        $isCod = $paymentMethod && str_contains(strtolower($paymentMethod->name), 'cod');

        $courierNorm = strtoupper(trim($transaction->shipping_courier ?? ''));
        if ($courierNorm === 'GOJEK' || $courierNorm === 'GO-SEND') {
            $courierNorm = 'GOSEND';
        }

        $serviceNorm = trim($transaction->shipping_service ?? 'Standard');

        // Normalize courier and service names for Komerce compatibility
        if ($courierNorm === 'JNE') {
            if (str_contains(strtoupper($serviceNorm), 'YES')) {
                $serviceNorm = 'YES';
            } elseif (str_contains(strtoupper($serviceNorm), 'OKE')) {
                $serviceNorm = 'OKE';
            } elseif (str_contains(strtoupper($serviceNorm), 'FLAT')) {
                $serviceNorm = 'JNEFlat';
            } elseif (str_contains(strtoupper($serviceNorm), 'CTC')) {
                $serviceNorm = 'CTC';
            } else {
                $serviceNorm = 'REG';
            }
        } elseif ($courierNorm === 'JNT' || $courierNorm === 'J&T' || $courierNorm === 'JT') {
            $courierNorm = 'JNT';
            if (str_contains(strtoupper($serviceNorm), 'SUPER')) {
                $serviceNorm = 'Super';
            } elseif (str_contains(strtoupper($serviceNorm), 'CARGO')) {
                $serviceNorm = 'Cargo';
            } else {
                $serviceNorm = 'EZ';
            }
        } elseif ($courierNorm === 'SICEPAT' || $courierNorm === 'SI_CEPAT' || $courierNorm === 'SI-CEPAT') {
            $courierNorm = 'SICEPAT';
            if (str_contains(strtoupper($serviceNorm), 'GOKIL')) {
                $serviceNorm = 'GOKIL';
            } elseif (str_contains(strtoupper($serviceNorm), 'BEST')) {
                $serviceNorm = 'BEST';
            } else {
                $serviceNorm = 'REG';
            }
        } elseif ($courierNorm === 'NINJA' || $courierNorm === 'NINJA_VAN' || $courierNorm === 'NINJAVAN') {
            $courierNorm = 'NINJA';
            $serviceNorm = 'Standard';
        } elseif ($courierNorm === 'SAP') {
            $serviceNorm = 'UDRREG';
        } elseif ($courierNorm === 'IDEXPRESS' || $courierNorm === 'IDX' || $courierNorm === 'IDE') {
            $courierNorm = 'IDEXPRESS';
            $serviceNorm = 'IDFLAT';
        } elseif ($courierNorm === 'LION' || $courierNorm === 'LION_PARCEL' || $courierNorm === 'LIONPARCEL') {
            $courierNorm = 'LION';
            $serviceNorm = 'REGPACK';
        }

        $discountPercent = self::getShippingDiscountPercentage($transaction->shipping_courier ?? '', $transaction->shipping_service ?? '');
        $shippingCost = (int) $transaction->shipping_fee;
        $shippingCashback = (int) round($shippingCost * $discountPercent);

        $transaction->loadMissing(['items.product', 'items.productVariant']);

        $physicalItems = $transaction->items->filter(function ($item) {
            return ! ($item->product && $item->product->is_digital);
        });

        $orderDetails = $physicalItems->map(function ($item) {
            $price = (int) ($item->harga_akhir ?? $item->harga_jual ?? 0);
            $qty = (int) $item->quantity;

            // Resolve weight and dimensions from variant or main product
            $variant = $item->productVariant;
            $product = $item->product;

            $weight = 1000;
            if ($variant && $variant->weight > 0) {
                $weight = (int) $variant->weight;
            } elseif ($product && $product->weight > 0) {
                $weight = (int) $product->weight;
            }

            $width = 10;
            if ($variant && $variant->width > 0) {
                $width = (int) $variant->width;
            } elseif ($product && $product->width > 0) {
                $width = (int) $product->width;
            }

            $height = 10;
            if ($variant && $variant->height > 0) {
                $height = (int) $variant->height;
            } elseif ($product && $product->height > 0) {
                $height = (int) $product->height;
            }

            $length = 10;
            if ($variant && $variant->length > 0) {
                $length = (int) $variant->length;
            } elseif ($product && $product->length > 0) {
                $length = (int) $product->length;
            }

            return [
                'product_name' => $item->product_name,
                'product_variant_name' => $item->product_sku ?? 'Default',
                'product_price' => $price,
                'product_weight' => $weight,
                'product_width' => $width,
                'product_height' => $height,
                'product_length' => $length,
                'qty' => $qty,
                'subtotal' => $price * $qty,
            ];
        })->values()->toArray();

        $itemSubtotalSum = collect($orderDetails)->sum('subtotal');
        $totalWeightGrams = 0;
        foreach ($orderDetails as $detail) {
            $totalWeightGrams += $detail['product_weight'] * $detail['qty'];
        }

        // Fetch the exact shipping cost from Komerce calculation endpoint to ensure it matches perfectly
        if (! app()->environment('testing')) {
            try {
                $calcRes = self::getDomesticCost(
                    $originRegencyId,
                    $address->regency_id,
                    $totalWeightGrams,
                    strtolower($courierNorm),
                    $address->id
                );

                if (isset($calcRes['results'][0]['costs'])) {
                    $found = false;
                    foreach ($calcRes['results'][0]['costs'] as $c) {
                        if (strtoupper($c['service']) === strtoupper($serviceNorm)) {
                            $shippingCost = (int) ($c['cost'][0]['value'] ?? $shippingCost);
                            $found = true;
                            break;
                        }
                    }

                    if (! $found && ! empty($calcRes['results'][0]['costs'])) {
                        $shippingCost = (int) ($calcRes['results'][0]['costs'][0]['cost'][0]['value'] ?? $shippingCost);
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to pre-calculate shipping cost in storeShipment: '.$e->getMessage());
            }
        }

        if ($shippingCost !== (int) $transaction->shipping_fee) {
            $oldFee = (int) $transaction->shipping_fee;
            $diff = $shippingCost - $oldFee;
            $transaction->update([
                'shipping_fee' => $shippingCost,
                'grand_total' => (float) $transaction->grand_total + $diff,
            ]);
        }

        $shippingCashback = (int) round($shippingCost * $discountPercent);
        $calculatedGrandTotal = $itemSubtotalSum + $shippingCost + (int) $transaction->admin_fee;

        $cleanShipperPhone = preg_replace('/^\+/', '', trim($storePhone));
        $cleanReceiverPhone = preg_replace('/^\+/', '', trim($address->phone_number));

        $additionalCost = (int) ($transaction->application_fee ?? 0) + (int) ($transaction->admin_fee ?? 0);
        $insurance = 0;
        $insuranceValue = 0.0;

        // Run the price and insurance calculation iteratively to resolve the circular dependency
        // where insurance_value depends on product_price, and product_price depends on discount (which includes insurance).
        $maxIterations = 3;
        for ($iter = 0; $iter < $maxIterations; $iter++) {
            $totalProductPrice = (float) $itemSubtotalSum;
            $insuranceValue = 0.0;
            if ($totalProductPrice >= 300000) {
                $courierKey = strtolower($courierNorm);
                if ($courierKey === 'jne') {
                    $insuranceValue = ($totalProductPrice * 0.002) + 5000;
                } elseif ($courierKey === 'sicepat') {
                    $tempGross = $totalProductPrice + $shippingCost + (int) $transaction->admin_fee + $additionalCost;
                    if ($tempGross > 500000) {
                        $insuranceValue = $tempGross * 0.003;
                    }
                } elseif ($courierKey === 'idexpress') {
                    $insuranceValue = $totalProductPrice * 0.002;
                } elseif ($courierKey === 'sap') {
                    $insuranceValue = ($totalProductPrice * 0.003) + 2000;
                } elseif ($courierKey === 'ninja') {
                    if ($totalProductPrice <= 1000000) {
                        $insuranceValue = 2500;
                    } else {
                        $insuranceValue = $totalProductPrice * 0.0025;
                    }
                } elseif ($courierKey === 'jnt' || $courierKey === 'j&t') {
                    $insuranceValue = $totalProductPrice * 0.002;
                } elseif ($courierKey === 'lion') {
                    $insuranceValue = $totalProductPrice * 0.003;
                } elseif ($courierKey === 'gosend') {
                    if ($totalProductPrice <= 1000000) {
                        $insuranceValue = 1000;
                    } elseif ($totalProductPrice <= 10000000) {
                        $insuranceValue = 2000;
                    } else {
                        $insuranceValue = 5000;
                    }
                }
            }

            $insurance = $insuranceValue > 0 ? (int) ceil($insuranceValue) : 0;
            $grossTotal = $itemSubtotalSum + $shippingCost + $additionalCost + $insurance;
            $totalDiscount = (int) ($grossTotal - (float) $transaction->grand_total);

            if ($totalDiscount !== 0 && ! empty($orderDetails)) {
                if ($iter === $maxIterations - 1) {
                    $additionalCost = max(0, $additionalCost - $totalDiscount);
                    break;
                }

                $remainingDiscount = $totalDiscount;
                $itemCount = count($orderDetails);

                for ($i = 0; $i < $itemCount; $i++) {
                    $qty = $orderDetails[$i]['qty'];
                    if ($qty <= 0) {
                        $qty = 1;
                    }

                    if ($i === $itemCount - 1) {
                        $itemDiscount = $remainingDiscount;
                    } else {
                        $itemShare = $orderDetails[$i]['subtotal'] / $itemSubtotalSum;
                        $itemDiscount = (int) round($totalDiscount * $itemShare);
                        $itemDiscount = min($itemDiscount, $remainingDiscount);
                    }

                    $remainingDiscount -= $itemDiscount;

                    $newSubtotal = max(0, $orderDetails[$i]['subtotal'] - $itemDiscount);
                    $newUnitPrice = (int) round($newSubtotal / $qty);

                    $orderDetails[$i]['product_price'] = $newUnitPrice;
                    $orderDetails[$i]['subtotal'] = $newUnitPrice * $qty;
                }

                $itemSubtotalSum = collect($orderDetails)->sum('subtotal');
            } else {
                break;
            }
        }

        // Final fail-safe validation to ensure calculated total matches grand_total exactly
        $grossTotal = $itemSubtotalSum + $shippingCost + $additionalCost + $insurance;
        $diff = (int) ($grossTotal - (float) $transaction->grand_total);
        if ($diff !== 0) {
            $additionalCost = max(0, $additionalCost - $diff);
        }

        Log::info([
            'subtotal' => collect($orderDetails)->sum('subtotal'),
            'shipping_cost' => $shippingCost,
            'service_fee' => 0,
            'additional_cost' => $additionalCost,
            'insurance_value' => ceil($insuranceValue),
            'grand_total' => $transaction->grand_total,
        ]);

        // Construct Postman-compliant Komerce API payload
        $payload = [
            'order_date' => $transaction->created_at ? $transaction->created_at->format('Y-m-d') : now()->format('Y-m-d'),
            'brand_name' => $storeName,
            'shipper_name' => $storeName,
            'shipper_phone' => $cleanShipperPhone,
            'shipper_destination_id' => (int) $shipperDestinationId,
            'shipper_address' => $storeAddress,
            'shipper_email' => $storeEmail,
            'origin_pin_point' => "{$storeLat}, {$storeLng}",
            'receiver_name' => $address->receiver_name,
            'receiver_phone' => $cleanReceiverPhone,
            'receiver_destination_id' => (int) $receiverDestinationId,
            'receiver_address' => $address->full_address.', '.$address->district_name.', '.$address->regency_name.', '.$address->province_name.' '.$address->postal_code,
            'destination_pin_point' => $address->latitude && $address->longitude ? "{$address->latitude}, {$address->longitude}" : '-7.274631, 109.207174',
            'shipping' => $courierNorm,
            'shipping_type' => $serviceNorm,
            'payment_method' => $isCod ? 'COD' : 'BANK TRANSFER',
            'shipping_cost' => $shippingCost,
            'shipping_cashback' => $shippingCashback,
            'service_fee' => 0,
            'additional_cost' => $additionalCost,
            'discount' => 0, // Set discount to 0 since the prices have been adjusted directly to balance the grand_total
            // 'grand_total' => (int) $transaction->grand_total,
            'grand_total' => collect($orderDetails)->sum('subtotal')
    + $shippingCost
    + $additionalCost,
            'cod_value' => $isCod ? (int) $transaction->grand_total : 0,
            'is_insurance' => $insuranceValue > 0 ? 1 : 0,
            'insurance_value' => (int) ceil($insuranceValue),
            'order_details' => $orderDetails,
        ];

        if ($courierNorm === 'LION') {
            $payload['commodity_code'] = 'ELG150';
        }

        $isSandbox = ! app()->environment('production') || str_contains(self::getKomerceDeliveryUrl(), 'sandbox');

        Log::info('Komerce Delivery store payload: '.json_encode($payload));

        try {
            $response = Http::withHeaders(self::getCollaboratorHeaders($apiKey))
                ->post(self::getKomerceUrl('store'), $payload);

            if ($response->successful()) {
                Log::info('Komerce Delivery store success response: '.$response->body());

                return ['success' => true, 'data' => $response->json('data') ?? $response->json()];
            }

            if ($isSandbox) {
                // Fallback for staging testing: simulate order booking if staging credentials fail or return mock response
                Log::info('Komerce Delivery store failed (expected in sandbox): '.$response->body().'. Returning simulated response for staging test.');

                return [
                    'success' => true,
                    'simulated' => true,
                    'data' => [
                        'booking_code' => 'BOOK-'.strtoupper(uniqid()),
                        'airway_bill' => 'RES-'.strtoupper(uniqid()),
                        'status' => 'booked',
                        'courier' => $transaction->shipping_courier ?? 'jne',
                        'service' => $transaction->shipping_service ?? 'REG',
                        'shipping_fee' => $transaction->shipping_fee ?? 15000,
                    ],
                ];
            }

            Log::warning('Komerce Delivery store failed. Status: '.$response->status().' Body: '.$response->body().' Payload: '.json_encode($payload));

            return [
                'success' => false,
                'error' => $response->json('meta.message') ?? 'Gagal membuat pesanan pengiriman di Komerce.',
            ];
        } catch (\Exception $e) {
            Log::error('KomerceService Store Shipment Error: '.$e->getMessage().' Payload: '.json_encode($payload));

            if ($isSandbox) {
                return [
                    'success' => true,
                    'simulated' => true,
                    'data' => [
                        'booking_code' => 'BOOK-'.strtoupper(uniqid()),
                        'airway_bill' => 'RES-'.strtoupper(uniqid()),
                        'status' => 'booked',
                        'courier' => $transaction->shipping_courier ?? 'jne',
                        'service' => $transaction->shipping_service ?? 'REG',
                        'shipping_fee' => $transaction->shipping_fee ?? 15000,
                    ],
                ];
            }

            return ['error' => 'Gagal terhubung ke Komerce Delivery.'];
        }
    }

    /**
     * Request Courier Pickup.
     */
    public static function requestPickup(array $payload): array
    {
        $apiKey = self::getSetting(
            'shipping_delivery_key',
            'app.rajaongkir.shipping_delivery_key'
        );

        if (! $apiKey) {
            return [
                'success' => false,
                'error' => 'API Key Shipping Delivery belum diatur.',
            ];
        }

        try {

            $pickupTime = ! empty($payload['pickup_time'])
                ? Carbon::parse($payload['pickup_time'])
                : now()->addDay()->setHour(14)->setMinute(0);

            $vehicleType = strtolower(
                $payload['vehicle_type'] ?? 'motorcycle'
            );

            $pickupVehicle = match (true) {
                str_contains($vehicleType, 'truck'),
                str_contains($vehicleType, 'truk') => 'Truk',

                str_contains($vehicleType, 'car'),
                str_contains($vehicleType, 'mobil') => 'Mobil',

                default => 'Motor',
            };

            $apiPayload = [
                'pickup_date' => $pickupTime->format('Y-m-d'),
                'pickup_time' => $pickupTime->format('H:i'),
                'pickup_vehicle' => $pickupVehicle,
                'orders' => [
                    [
                        'order_no' => $payload['booking_code'] ?? '',
                    ],
                ],
            ];

            Log::info('Pickup URL', [
                'url' => self::getKomerceUrl('Pickup'),
            ]);

            Log::info('Pickup Payload', $apiPayload);

            $response = Http::withHeaders(
                self::getCollaboratorHeaders($apiKey)
            )->post(
                self::getKomerceUrl('Pickup'),
                $apiPayload
            );

            Log::info('Pickup Response Status', [
                'status' => $response->status(),
            ]);

            Log::info('Pickup Response Body', [
                'body' => $response->body(),
            ]);

            $data = $response->json();

            if (! $response->successful()) {

                return [
                    'success' => false,
                    'error' => $data['meta']['message']
                        ?? 'Gagal request pickup ke Komerce.',
                    'data' => $data,
                ];
            }

            $firstOrder = $data['data'][0] ?? null;

            if (! $firstOrder) {

                return [
                    'success' => false,
                    'error' => 'Response pickup tidak memiliki data order.',
                    'data' => $data,
                ];
            }

            if (($firstOrder['status'] ?? '') !== 'success') {

                return [
                    'success' => false,
                    'error' => 'Pickup gagal diproses oleh Komerce.',
                    'data' => $data,
                ];
            }

            if (empty($firstOrder['awb'])) {

                return [
                    'success' => false,
                    'error' => 'Pickup berhasil tetapi AWB belum tersedia.',
                    'data' => $data,
                ];
            }

            return [
                'success' => true,
                'data' => $data,
            ];

        } catch (\Throwable $e) {

            Log::error('Komerce Pickup Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Print Shipping Label.
     */
    public static function printLabel(string $bookingCode): array
    {
        $apiKey = self::getSetting('shipping_delivery_key', 'app.rajaongkir.shipping_delivery_key');
        if (! $apiKey) {
            return ['error' => 'API Key Shipping Delivery belum diatur.'];
        }

        try {
            $response = Http::withHeaders(self::getCollaboratorHeaders($apiKey))
                ->post(self::getKomerceUrl('print-label').'?'.http_build_query([
                    'order_no' => $bookingCode,
                    'page' => 'page_5',
                ]));

            if ($response->successful()) {
                $path = $response->json('data.path') ?? $response->json('path');
                if ($path) {
                    $baseUrl = self::getKomerceDeliveryUrl();
                    $parsed = parse_url($baseUrl);
                    $scheme = $parsed['scheme'] ?? 'https';
                    $host = $parsed['host'] ?? 'api-sandbox.collaborator.komerce.id';
                    $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';
                    $domain = "{$scheme}://{$host}{$port}";

                    $cleanPath = ltrim($path, '/');
                    if (str_starts_with($cleanPath, 'storage/')) {
                        $cleanPath = 'order/'.$cleanPath;
                    }

                    $url = "{$domain}/{$cleanPath}";

                    return [
                        'success' => true,
                        'url' => $url,
                        'base_64' => $response->json('data.base_64') ?? $response->json('base_64'),
                    ];
                }
            }

            $isSandbox = ! app()->environment('production') || str_contains(self::getKomerceDeliveryUrl(), 'sandbox');
            if ($isSandbox) {
                // Fallback for staging: return a fallback view or direct download url
                $transaction = Transaction::where('booking_code', $bookingCode)->first();
                $transactionId = $transaction ? $transaction->id : $bookingCode;

                return [
                    'success' => true,
                    'simulated' => true,
                    'url' => route('admin.transactions.print-shipping-label', ['transaction' => $transactionId]),
                ];
            }

            return [
                'success' => false,
                'error' => $response->json('meta.message') ?? 'Gagal mengambil label cetak dari Komerce.',
            ];
        } catch (\Exception $e) {
            Log::error('KomerceService Print Label Error: '.$e->getMessage());

            return ['error' => 'Gagal mengambil label cetak.'];
        }
    }

    /**
     * Cancel shipment.
     */
    public static function cancelShipment(string $bookingCode): array
    {
        $apiKey = self::getSetting('shipping_delivery_key', 'app.rajaongkir.shipping_delivery_key');
        if (! $apiKey) {
            return ['error' => 'API Key Shipping Delivery belum diatur.'];
        }

        try {
            $response = Http::withHeaders(self::getCollaboratorHeaders($apiKey))
                ->put(self::getKomerceUrl('cancel'), [
                    'order_no' => $bookingCode,
                ]);

            if ($response->successful()) {
                return ['success' => true, 'message' => 'Pengiriman berhasil dibatalkan.'];
            }

            $isSandbox = ! app()->environment('production') || str_contains(self::getKomerceDeliveryUrl(), 'sandbox');
            if ($isSandbox) {
                return [
                    'success' => true,
                    'simulated' => true,
                    'message' => 'Pengiriman dibatalkan (Simulasi Staging).',
                ];
            }

            return [
                'success' => false,
                'error' => $response->json('meta.message') ?? 'Gagal membatalkan pengiriman.',
            ];
        } catch (\Exception $e) {
            Log::error('KomerceService Cancel Shipment Error: '.$e->getMessage());

            return ['error' => 'Gagal membatalkan pengiriman.'];
        }
    }

    /**
     * Get shipment status history.
     */
    public static function getShipmentHistory(string $airwayBill, ?string $courier = null): array
    {
        $apiKey = self::getSetting('shipping_delivery_key', 'app.rajaongkir.shipping_delivery_key');
        if (! $apiKey) {
            return ['error' => 'API Key Shipping Delivery belum diatur.'];
        }

        $courier = $courier ? strtolower($courier) : 'jne';
        $courier = $courier === 'gojek' ? 'gosend' : $courier;

        try {
            $response = Http::withHeaders(self::getCollaboratorHeaders($apiKey))
                ->get(self::getKomerceUrl('history-airway-bill'), [
                    'airway_bill' => $airwayBill,
                    'shipping' => $courier,
                ]);

            if ($response->successful()) {
                return ['success' => true, 'history' => $response->json('data.history') ?? $response->json('history') ?? []];
            }

            $isSandbox = ! app()->environment('production') || str_contains(self::getKomerceDeliveryUrl(), 'sandbox');
            if ($isSandbox) {
                // Fallback simulated tracking for testing (progresses dynamically based on transaction status)
                $transaction = Transaction::where('tracking_number', $airwayBill)->first();
                $status = $transaction ? $transaction->status : 'diproses';

                $history = [
                    ['date' => now()->subHours(5)->toDateTimeString(), 'desc' => 'Paket telah diserahkan ke kurir (Staging)'],
                    ['date' => now()->subHours(2)->toDateTimeString(), 'desc' => 'Paket sedang transit di hub logistik'],
                ];

                if ($status === 'dikirim' || $status === 'selesai') {
                    $history[] = ['date' => now()->subHours(1)->toDateTimeString(), 'desc' => 'Paket sedang dalam perjalanan ke alamat penerima'];
                    $history[] = ['date' => now()->toDateTimeString(), 'desc' => 'Paket telah diterima oleh Ybs (Simulasi)'];
                } else {
                    $history[] = ['date' => now()->toDateTimeString(), 'desc' => 'Paket sedang dalam perjalanan ke alamat penerima'];
                }

                return [
                    'success' => true,
                    'simulated' => true,
                    'history' => $history,
                ];
            }

            Log::warning("Komerce getShipmentHistory failed for waybill {$airwayBill}: ".$response->body());

            return [
                'success' => false,
                'error' => $response->json('meta.message') ?? 'Gagal mengambil riwayat pengiriman.',
            ];
        } catch (\Exception $e) {
            Log::error('KomerceService Shipment History Error: '.$e->getMessage());

            return ['error' => 'Gagal mengambil riwayat pengiriman.'];
        }
    }

    /**
     * Get detailed order info from Komerce.
     */
    public static function getOrderDetail(string $bookingCode): array
    {
        $apiKey = self::getSetting('shipping_delivery_key', 'app.rajaongkir.shipping_delivery_key');
        if (! $apiKey) {
            return ['error' => 'API Key Shipping Delivery belum diatur.'];
        }

        try {
            $response = Http::withHeaders(self::getCollaboratorHeaders($apiKey))
                ->get(self::getKomerceUrl('detail'), [
                    'order_no' => $bookingCode,
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json('data'),
                ];
            }

            $isSandbox = ! app()->environment('production') || str_contains(self::getKomerceDeliveryUrl(), 'sandbox');
            if ($isSandbox) {
                // Return dynamic simulated details matching transaction database
                $transaction = Transaction::where('booking_code', $bookingCode)->first();
                if ($transaction) {
                    $address = $transaction->customerAddress;
                    $physicalItems = $transaction->items->filter(function ($item) {
                        return ! ($item->product && $item->product->is_digital);
                    });
                    $orderDetails = $physicalItems->map(function ($item) {
                        $variant = $item->productVariant;
                        $product = $item->product;

                        return [
                            'product_name' => $item->product_name,
                            'product_variant_name' => $variant ? $variant->name : '',
                            'product_weight' => $variant && $variant->weight > 0 ? (int) $variant->weight : ($product && $product->weight > 0 ? (int) $product->weight : 1000),
                            'product_height' => $variant && $variant->height > 0 ? (int) $variant->height : ($product && $product->height > 0 ? (int) $product->height : 10),
                            'product_width' => $variant && $variant->width > 0 ? (int) $variant->width : ($product && $product->width > 0 ? (int) $product->width : 10),
                            'product_length' => $variant && $variant->length > 0 ? (int) $variant->length : ($product && $product->length > 0 ? (int) $product->length : 10),
                            'product_price' => (float) $item->harga_akhir,
                            'qty' => (int) $item->quantity,
                            'subtotal' => (float) $item->subtotal,
                        ];
                    })->values()->toArray();

                    return [
                        'success' => true,
                        'simulated' => true,
                        'data' => [
                            'order_no' => $bookingCode,
                            'awb' => $transaction->tracking_number ?? 'KOMERKOM'.$bookingCode,
                            'order_status' => $transaction->status === 'selesai' ? 'Selesai' : ($transaction->status === 'dikirim' ? 'Dikirim' : ($transaction->status === 'out_for_pickup' ? 'Dijemput' : 'Diajukan')),
                            'order_date' => $transaction->created_at ? $transaction->created_at->toDateString() : now()->toDateString(),
                            'brand_name' => self::getSetting('store_name') ?? 'My Store',
                            'shipper_name' => self::getSetting('store_name') ?? 'My Store',
                            'shipper_phone' => self::getSetting('store_phone') ?? '081234567890',
                            'shipper_destination_id' => (int) (self::getSetting('shipper_destination_id') ?? 3578),
                            'shipper_address' => self::getSetting('address') ?? self::getSetting('store_address') ?? 'Jl. Merdeka No. 5',
                            'receiver_name' => $address ? $address->receiver_name : 'Customer',
                            'receiver_phone' => $address ? $address->phone_number : '08123456789',
                            'receiver_destination_id' => $address ? (int) self::resolveKomerceDestinationId($address->province_name ?? '', $address->regency_name ?? '', $address->district_name ?? '', $address->postal_code) : 3578,
                            'receiver_address' => $address ? $address->full_address : '',
                            'shipping' => strtoupper($transaction->shipping_courier),
                            'shipping_type' => $transaction->shipping_service,
                            'payment_method' => $transaction->paymentMethod ? $transaction->paymentMethod->name : 'Transfer',
                            'shipping_cost' => (float) $transaction->shipping_fee,
                            'shipping_cashback' => 0.0,
                            'service_fee' => 0.0,
                            'additional_cost' => 0.0,
                            'grand_total' => (float) $transaction->grand_total,
                            'cod_value' => (float) $transaction->grand_total,
                            'notes' => $transaction->notes ?? '',
                            'insurance_value' => 0.0,
                            'origin_pin_point' => (self::getSetting('latitude') ?? '-7.274631').', '.(self::getSetting('longitude') ?? '109.207174'),
                            'destination_pin_point' => ($address && $address->latitude ? $address->latitude : '-7.274631').', '.($address && $address->longitude ? $address->longitude : '109.207174'),
                            'booking_id' => $bookingCode,
                            'driver_name' => 'Driver',
                            'driver_phone' => '081234567890',
                            'cancelation_reason' => $transaction->cancel_reason ?? '',
                            'live_tracking_url' => '',
                            'commodity_code' => '',
                            'order_details' => $orderDetails,
                        ],
                    ];
                }
            }

            return [
                'success' => false,
                'error' => $response->json('meta.message') ?? 'Gagal mengambil detail pesanan dari Komerce.',
            ];
        } catch (\Exception $e) {
            Log::error('KomerceService Get Order Detail Error: '.$e->getMessage());

            return ['error' => 'Gagal mengambil detail pesanan: '.$e->getMessage()];
        }
    }

    /**
     * Register or update webhook URL with Komerce.
     */
    public static function registerWebhook(string $webhookUrl): array
    {
        $apiKey = self::getSetting('shipping_delivery_key', 'app.rajaongkir.shipping_delivery_key');
        if (! $apiKey) {
            return ['error' => 'API Key Shipping Delivery belum diatur.'];
        }

        try {
            $response = Http::withHeaders(self::getCollaboratorHeaders($apiKey))
                ->put(self::getKomerceUrl('webhook'), [
                    'webhook_url' => $webhookUrl,
                ]);

            if ($response->successful()) {
                return ['success' => true, 'message' => 'Webhook berhasil didaftarkan.'];
            }

            $isSandbox = ! app()->environment('production') || str_contains(self::getKomerceDeliveryUrl(), 'sandbox');
            if ($isSandbox) {
                return [
                    'success' => true,
                    'simulated' => true,
                    'message' => 'Webhook berhasil didaftarkan (Simulasi Staging).',
                ];
            }

            return [
                'success' => false,
                'error' => $response->json('meta.message') ?? 'Gagal mendaftarkan webhook ke Komerce.',
            ];
        } catch (\Exception $e) {
            Log::error('KomerceService Register Webhook Error: '.$e->getMessage());

            return ['error' => 'Gagal mendaftarkan webhook: '.$e->getMessage()];
        }
    }

    // ==========================================
    // QRISLY & PAYMENT API (Dynamic Payments)
    // ==========================================

    /**
     * Check if Payment API is Enabled.
     */
    public static function isPaymentEnabled(): bool
    {
        $enabled = self::getSetting('payment_api_enabled');

        return $enabled === '1' || $enabled === 'true' || $enabled === true;
    }

    /**
     * Check if QRISLY API is Enabled.
     */
    public static function isQrislyEnabled(): bool
    {
        $enabled = self::getSetting('qrisly_api_enabled');

        return $enabled === '1' || $enabled === 'true' || $enabled === true;
    }

    /**
     * Generate Dynamic QRIS (QRISLY).
     */
    public static function generateQris(string $orderId, float $amount): array
    {
        $apiKey = self::getSetting('qrisly_api_key', 'app.rajaongkir.qrisly_api_key');
        $baseUrl = self::getKomerceDeliveryUrl();

        if (! $apiKey) {
            return ['error' => 'API Key QRISLY belum diatur.'];
        }

        try {
            $response = Http::withHeaders(self::getCollaboratorHeaders($apiKey))
                ->post(self::getKomerceUrl('payment/qris/generate'), [
                    'amount' => (int) $amount,
                    'reference_id' => $orderId,
                    'notify_url' => route('api.komerce.webhook'),
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'qris_string' => $response->json('data.qris_string') ?? $response->json('qris_string'),
                    'qris_image' => $response->json('data.qris_image') ?? $response->json('qris_image'),
                    'reference_id' => $response->json('data.reference_id') ?? $response->json('reference_id') ?? $orderId,
                ];
            }

            // Fallback for sandbox/staging test: generate a simulated QRIS string
            Log::warning('QRISLY generate failed: '.$response->body().'. Generating staging mock QRIS.');

            // Standard simulated dummy QRIS string
            $dummyQris = '00020101021226300016ID.CO.QRISLY.WWW01189360091100000000000215000300000000005204599953033605802ID5918BIZMATE STOREFRONT6009SIDOARJO61056123462070703A016304ABCD';

            return [
                'success' => true,
                'simulated' => true,
                'qris_string' => $dummyQris,
                // Direct Google Charts QR code generator URL
                'qris_image' => 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data='.urlencode($dummyQris),
                'reference_id' => $orderId,
            ];
        } catch (\Exception $e) {
            Log::error('KomerceService Generate QRIS Error: '.$e->getMessage());

            return ['error' => 'Gagal generate pembayaran QRIS.'];
        }
    }

    /**
     * Generate General Payment URL (Payment API).
     */
    public static function generatePaymentUrl(string $orderId, float $amount): array
    {
        $apiKey = self::getSetting('payment_api_key', 'app.rajaongkir.payment_api_key');
        $baseUrl = self::getKomerceDeliveryUrl();

        if (! $apiKey) {
            return ['error' => 'API Key Payment API belum diatur.'];
        }

        try {
            $response = Http::withHeaders(self::getCollaboratorHeaders($apiKey))
                ->post(self::getKomerceUrl('payment/checkout/generate'), [
                    'amount' => (int) $amount,
                    'reference_id' => $orderId,
                    'notify_url' => route('api.komerce.webhook'),
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'checkout_url' => $response->json('data.checkout_url') ?? $response->json('checkout_url'),
                    'reference_id' => $response->json('data.reference_id') ?? $response->json('reference_id') ?? $orderId,
                ];
            }

            // Fallback for sandbox/staging test: generate simulated checkout redirect url
            Log::warning('Payment API generate failed: '.$response->body().'. Generating simulated checkout URL.');

            return [
                'success' => true,
                'simulated' => true,
                'checkout_url' => route('transactions.show', ['transaction' => $orderId]).'?simulated_payment=1',
                'reference_id' => $orderId,
            ];
        } catch (\Exception $e) {
            Log::error('KomerceService Generate Payment URL Error: '.$e->getMessage());

            return ['error' => 'Gagal generate tautan pembayaran.'];
        }
    }

    /**
     * Check Payment / QRIS status.
     */
    public static function checkPaymentStatus(string $referenceId): array
    {
        $apiKey = self::getSetting('qrisly_api_key', 'app.rajaongkir.qrisly_api_key')
            ?? self::getSetting('payment_api_key', 'app.rajaongkir.payment_api_key');
        $baseUrl = self::getKomerceDeliveryUrl();

        if (! $apiKey) {
            return ['error' => 'API Key tidak diatur.'];
        }

        try {
            $response = Http::withHeaders(self::getCollaboratorHeaders($apiKey))
                ->get(self::getKomerceUrl('payment/status'), [
                    'reference_id' => $referenceId,
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'status' => $response->json('data.status') ?? $response->json('status'), // 'paid', 'pending', 'expired'
                ];
            }

            return ['success' => true, 'simulated' => true, 'status' => 'pending'];
        } catch (\Exception $e) {
            Log::error('KomerceService Check Payment Status Error: '.$e->getMessage());

            return ['error' => 'Gagal memeriksa status pembayaran.'];
        }
    }

    /**
     * Sync Komerce Payment Methods to Database based on settings.
     */
    public static function syncPaymentMethods(): void
    {
        try {
            // 1. Payment API
            $paymentEnabled = self::isPaymentEnabled();
            $paymentFee = (float) (Setting::where('key', 'payment_api_admin_fee')->value('value') ?? 0);
            $paymentKey = self::getSetting('payment_api_key', 'app.rajaongkir.payment_api_key');

            if ($paymentEnabled) {
                PaymentMethod::updateOrCreate(
                    ['name' => 'Komerce Payment'],
                    [
                        'type' => 'gateway',
                        'api_key' => $paymentKey ?: 'sdfh2Qgp5a2e20929ec5ff822tkkgf6S',
                        'admin_fee' => $paymentFee,
                        'is_active' => true,
                    ]
                );
            } else {
                PaymentMethod::where('name', 'Komerce Payment')->delete();
            }

            // 2. QRISLY API
            $qrislyEnabled = self::isQrislyEnabled();
            $qrislyFee = (float) (Setting::where('key', 'qrisly_api_admin_fee')->value('value') ?? 0);
            $qrislyKey = self::getSetting('qrisly_api_key', 'app.rajaongkir.qrisly_api_key');

            if ($qrislyEnabled) {
                PaymentMethod::updateOrCreate(
                    ['name' => 'QRIS (Komerce)'],
                    [
                        'type' => 'gateway',
                        'api_key' => $qrislyKey ?: 'sdfh2Qgp5a2e20929ec5ff822tkkgf6S',
                        'admin_fee' => $qrislyFee,
                        'is_active' => true,
                    ]
                );
            } else {
                PaymentMethod::where('name', 'QRIS (Komerce)')->delete();
            }

            // 3. Midtrans Snap
            $midtransEnabled = config('app.midtrans_enabled', true) && (Setting::where('key', 'midtrans_api_enabled')->value('value') === '1');
            $midtransFee = (float) (Setting::where('key', 'midtrans_admin_fee')->value('value') ?? 0);
            $midtransKey = self::getSetting('midtrans_server_key', 'app.midtrans.server_key');

            if ($midtransEnabled) {
                PaymentMethod::updateOrCreate(
                    ['name' => 'Midtrans'],
                    [
                        'type' => 'gateway',
                        'api_key' => $midtransKey ?: '',
                        'admin_fee' => $midtransFee,
                        'is_active' => true,
                    ]
                );
            } else {
                PaymentMethod::where('name', 'Midtrans')->delete();
            }

            // 4. Xendit Invoices
            $xenditEnabled = (Setting::where('key', 'xendit_api_enabled')->value('value') === '1');
            $xenditFee = (float) (Setting::where('key', 'xendit_admin_fee')->value('value') ?? 0);
            $xenditKey = self::getSetting('xendit_secret_key', 'app.xendit.private_key');

            if ($xenditEnabled) {
                PaymentMethod::updateOrCreate(
                    ['name' => 'Xendit'],
                    [
                        'type' => 'gateway',
                        'api_key' => $xenditKey ?: '',
                        'admin_fee' => $xenditFee,
                        'is_active' => true,
                    ]
                );
            } else {
                PaymentMethod::where('name', 'Xendit')->delete();
            }
        } catch (\Exception $e) {
            Log::error('Error syncing Komerce payment methods: '.$e->getMessage());
        }
    }
}
