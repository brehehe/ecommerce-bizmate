<?php

namespace App\Services\Checkout;

use App\Models\CustomerAddress;
use App\Models\Setting;
use App\Services\BiteshipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ShippingCalculationService
{
    /**
     * Calculate shipping cost based on courier and destinations.
     *
     * @param  array<string, mixed>  $data
     */
    public function calculate(array $data, ?Collection $cartItems = null): JsonResponse
    {
        $courier = $data['courier'];
        $weight = (int) ($data['weight'] ?? 1);
        $addressId = $data['address_id'] ?? null;
        $destination = $data['destination'] ?? null;
        $isInternational = (bool) ($data['is_international'] ?? false);

        if ($courier === 'self_pickup') {
            $enabled = Setting::where('key', 'self_pickup_enabled')->value('value') === '1';
            if (! $enabled) {
                return response()->json(['error' => 'Metode Ambil di Toko tidak aktif.'], 422);
            }
            $fee = (float) (Setting::where('key', 'self_pickup_fee')->value('value') ?? 0);

            return response()->json([
                'results' => [
                    [
                        'code' => 'self_pickup',
                        'name' => 'Ambil di Toko',
                        'costs' => [
                            [
                                'service' => 'Self Pickup',
                                'description' => 'Ambil langsung di toko / outlet',
                                'cost' => [
                                    [
                                        'value' => $fee,
                                        'etd' => '0',
                                        'note' => 'Silakan ambil di alamat toko kami.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
        }

        if ($courier === 'store_courier') {
            $enabled = Setting::where('key', 'store_courier_enabled')->value('value') === '1';
            if (! $enabled) {
                return response()->json(['error' => 'Metode Kurir Toko tidak aktif.'], 422);
            }

            $customerAddress = $addressId ? CustomerAddress::find($addressId) : null;
            if (! $customerAddress) {
                return response()->json(['error' => 'Alamat pengiriman tidak ditemukan.'], 422);
            }

            $storeLat = Setting::where('key', 'latitude')->value('value');
            $storeLng = Setting::where('key', 'longitude')->value('value');

            if (! $storeLat || ! $storeLng) {
                return response()->json(['error' => 'Koordinat toko (latitude/longitude) belum diatur di pengaturan.'], 422);
            }

            if (! $customerAddress->latitude || ! $customerAddress->longitude) {
                return response()->json(['error' => 'Koordinat alamat pengiriman Anda belum diatur. Silakan edit alamat untuk menentukan pin point pada peta.'], 422);
            }

            // Haversine distance
            $earthRadius = 6371; // km
            $latDelta = deg2rad((float) $customerAddress->latitude - (float) $storeLat);
            $lonDelta = deg2rad((float) $customerAddress->longitude - (float) $storeLng);
            $a = sin($latDelta / 2) * sin($latDelta / 2) +
                cos(deg2rad((float) $storeLat)) * cos(deg2rad((float) $customerAddress->latitude)) *
                sin($lonDelta / 2) * sin($lonDelta / 2);
            $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
            $distance = $earthRadius * $c; // in km

            $maxRadius = (float) (Setting::where('key', 'store_courier_max_radius')->value('value') ?? 50);
            if ($distance > $maxRadius) {
                return response()->json(['error' => 'Jarak pengiriman melebihi radius maksimal Kurir Toko (maksimal '.$maxRadius.' km). Jarak saat ini: '.round($distance, 1).' km.'], 422);
            }

            $roundUp = Setting::where('key', 'store_courier_round_up')->value('value') === '1';
            if ($roundUp) {
                $distance = ceil($distance);
                if ($distance < 1) {
                    $distance = 1;
                }
            }

            $type = Setting::where('key', 'store_courier_type')->value('value') ?? 'flat';
            if ($type === 'radius') {
                $perKmFee = (float) (Setting::where('key', 'store_courier_per_km_fee')->value('value') ?? 0);
                $fee = round($perKmFee * $distance);
            } elseif ($type === 'radius_tiered') {
                $tieredRatesVal = Setting::where('key', 'store_courier_tiered_rates')->value('value');
                $tieredRates = $tieredRatesVal ? json_decode($tieredRatesVal, true) : [];
                if (! is_array($tieredRates)) {
                    $tieredRates = [];
                }

                usort($tieredRates, fn ($a, $b) => ((float) ($a['max_distance'] ?? 0)) <=> ((float) ($b['max_distance'] ?? 0)));

                $fee = null;
                foreach ($tieredRates as $tier) {
                    if ($distance <= (float) ($tier['max_distance'] ?? 0)) {
                        $fee = (float) ($tier['fee'] ?? 0);
                        break;
                    }
                }

                if ($fee === null) {
                    $fee = ! empty($tieredRates) ? (float) (end($tieredRates)['fee'] ?? 0) : 0.0;
                }
            } else {
                $fee = (float) (Setting::where('key', 'store_courier_flat_fee')->value('value') ?? 0);
            }

            $description = match ($type) {
                'flat' => 'Tarif Flat (Sama Rata)',
                'radius' => 'Tarif Berdasarkan Radius (per Km)',
                'radius_tiered' => 'Tarif Berdasarkan Radius Bertingkat (Tiered)',
                default => 'Dikirim menggunakan kurir internal toko',
            };

            return response()->json([
                'results' => [
                    [
                        'code' => 'store_courier',
                        'name' => 'Kurir Toko',
                        'costs' => [
                            [
                                'service' => 'Store Courier',
                                'description' => $description,
                                'cost' => [
                                    [
                                        'value' => $fee,
                                        'etd' => '1-2 hari',
                                        'note' => 'Jarak pengiriman: '.round($distance, 1).' km.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
        }

        $origin = Setting::where('key', 'rajaongkir_origin')->value('value')
            ?? Setting::where('key', 'regency_id')->value('value');

        if (! $origin) {
            return response()->json(['error' => 'Konfigurasi kota asal belum diatur di pengaturan.'], 422);
        }

        $customerAddress = $addressId ? CustomerAddress::find($addressId) : null;

        Log::info('Shipping cost calculation request payload:', [
            'origin_id' => $origin,
            'destination_id' => $destination,
            'weight' => $weight,
            'courier' => $courier,
            'is_international' => $isInternational,
            'address_id' => $addressId,
        ]);

        if ($isInternational) {
            return response()->json(['error' => 'Pengiriman internasional belum tersedia.'], 422);
        }

        if (! BiteshipService::isEnabled()) {
            return response()->json(['error' => 'Layanan pengiriman Biteship belum aktif.'], 422);
        }

        $response = BiteshipService::getDomesticCost(
            $origin,
            (string) ($destination ?? ''),
            $weight,
            $courier,
            $addressId,
            $cartItems ?? collect()
        );

        if (isset($response['error'])) {
            return response()->json(['error' => $response['error']], 422);
        }

        return response()->json(['results' => $response['results'] ?? []]);
    }
}
