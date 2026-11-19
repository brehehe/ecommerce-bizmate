<?php

namespace App\Services\Checkout;

use App\Models\CartItem;
use App\Models\CustomerAddress;
use App\Models\RestoProfile;
use App\Models\Setting;
use App\Services\BiteshipService;
use App\Services\KomerceService;
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
            $isFoodPick = config('app.is_foodpick');
            $restoProfile = null;
            if ($isFoodPick) {
                $restoId = $data['resto_id'] ?? null;
                if (! $restoId && $cartItems && $cartItems->isNotEmpty()) {
                    $restoId = $cartItems->first()?->product?->user_id;
                }
                if (! $restoId && auth()->check()) {
                    $firstCartItem = CartItem::with('product')->where('user_id', auth()->id())->first();
                    $restoId = $firstCartItem?->product?->user_id;
                }
                if ($restoId) {
                    $restoProfile = RestoProfile::where('user_id', $restoId)->first();
                }

                if ($restoProfile && ! $restoProfile->allow_pickup) {
                    return response()->json(['error' => 'Resto ini tidak melayani ambil di tempat / takeaway.'], 422);
                }
            }

            $enabled = $isFoodPick
                ? (bool) config('app.foodpick.allow_self_pickup', true)
                : (Setting::where('key', 'self_pickup_enabled')->value('value') === '1');
            if (! $enabled) {
                return response()->json(['error' => 'Metode Ambil di Toko tidak aktif.'], 422);
            }
            $fee = (float) (Setting::where('key', 'self_pickup_fee')->value('value') ?? 0);
            $storeName = $restoProfile?->resto_name ?? 'toko / resto';

            return response()->json([
                'results' => [
                    [
                        'code' => 'self_pickup',
                        'name' => $isFoodPick ? 'Ambil di Resto' : 'Ambil di Toko',
                        'costs' => [
                            [
                                'service' => $isFoodPick ? 'Ambil di Resto (Takeaway / Dine-In)' : 'Self Pickup',
                                'description' => $isFoodPick ? 'Ambil langsung di resto '.$storeName : 'Ambil langsung di toko / outlet',
                                'cost' => [
                                    [
                                        'value' => $fee,
                                        'etd' => '0',
                                        'note' => 'Silakan ambil di alamat '.$storeName.'.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
        }

        if ($courier === 'store_courier') {
            $isFoodPick = config('app.is_foodpick');
            $enabled = $isFoodPick
                ? (bool) config('app.foodpick.allow_store_courier', true)
                : (Setting::where('key', 'store_courier_enabled')->value('value') === '1');
            if (! $enabled) {
                return response()->json(['error' => 'Metode Kurir Toko tidak aktif.'], 422);
            }

            $customerAddress = $addressId ? CustomerAddress::find($addressId) : null;
            $customerLat = $customerAddress?->latitude ?? $data['latitude'] ?? null;
            $customerLng = $customerAddress?->longitude ?? $data['longitude'] ?? null;

            $storeLat = Setting::where('key', 'latitude')->value('value');
            $storeLng = Setting::where('key', 'longitude')->value('value');
            $maxRadius = (float) (Setting::where('key', 'store_courier_max_radius')->value('value') ?? 50);
            $restoProfile = null;
            $deliveryFeeType = 'flat';
            $baseFee = (float) (Setting::where('key', 'store_courier_flat_fee')->value('value') ?? 10000);
            $freeMinOrder = 0.0;

            if ($isFoodPick) {
                $restoId = $data['resto_id'] ?? null;
                if (! $restoId && $cartItems && $cartItems->isNotEmpty()) {
                    $restoId = $cartItems->first()?->product?->user_id;
                }
                if (! $restoId && auth()->check()) {
                    $firstCartItem = CartItem::with('product')->where('user_id', auth()->id())->first();
                    $restoId = $firstCartItem?->product?->user_id;
                }
                if ($restoId) {
                    $restoProfile = RestoProfile::where('user_id', $restoId)->first();
                }

                if ($restoProfile) {
                    if (! $restoProfile->allow_delivery) {
                        return response()->json(['error' => 'Resto ini tidak melayani pengantaran kurir resto.'], 422);
                    }
                    if ($restoProfile->latitude && $restoProfile->longitude) {
                        $storeLat = $restoProfile->latitude;
                        $storeLng = $restoProfile->longitude;
                    }
                    if ($restoProfile->max_delivery_radius_km > 0) {
                        $maxRadius = (float) $restoProfile->max_delivery_radius_km;
                    }
                    $deliveryFeeType = $restoProfile->delivery_fee_type ?? 'flat';
                    $baseFee = (float) ($restoProfile->delivery_fee_base ?? 10000);
                    $freeMinOrder = (float) ($restoProfile->delivery_free_min_order ?? 0);
                }
            }

            $distance = null;
            if ($storeLat && $storeLng && $customerLat && $customerLng) {
                // Haversine distance
                $earthRadius = 6371; // km
                $latDelta = deg2rad((float) $customerLat - (float) $storeLat);
                $lonDelta = deg2rad((float) $customerLng - (float) $storeLng);
                $a = sin($latDelta / 2) * sin($latDelta / 2) +
                    cos(deg2rad((float) $storeLat)) * cos(deg2rad((float) $customerLat)) *
                    sin($lonDelta / 2) * sin($lonDelta / 2);
                $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
                $distance = $earthRadius * $c; // in km

                if ($distance > $maxRadius) {
                    return response()->json([
                        'error' => 'Jarak pengiriman melebihi radius maksimal Kurir '.($isFoodPick ? 'Resto' : 'Toko').' (maksimal '.$maxRadius.' km). Jarak saat ini: '.round($distance, 1).' km.',
                    ], 422);
                }
            }

            // Fee calculation
            $fee = $baseFee;
            $serviceName = $isFoodPick ? 'Resto Delivery' : 'Kurir Toko';
            $etd = $isFoodPick ? '20-30 menit' : '1-2 Hari';
            $note = 'Pengantaran armada internal.';

            if ($distance !== null) {
                $roundUp = Setting::where('key', 'store_courier_round_up')->value('value') === '1' || $isFoodPick;
                $calcDist = $roundUp ? ceil(max(1, $distance)) : max(0.5, $distance);

                if ($deliveryFeeType === 'per_km' || (! $isFoodPick && Setting::where('key', 'store_courier_type')->value('value') === 'radius')) {
                    $fee = round($baseFee * $calcDist);
                    $description = 'Tarif per km (Rp '.number_format($baseFee, 0, ',', '.').'/km)';
                } else {
                    $fee = $baseFee;
                    $description = 'Tarif Flat Kurir Resto';
                }
                $note = 'Jarak pengiriman: '.round($distance, 1).' km.';
            } else {
                // When coordinates are not available (e.g. guest typed address without GPS)
                if (! $customerAddress && empty($destination) && empty($data['address_id'])) {
                    return response()->json(['error' => 'Alamat pengiriman belum diisi.'], 422);
                }
                $fee = $baseFee;
                $description = 'Tarif Standar Pengantaran';
                $note = 'Pengantaran kurir resto ke area alamat pemesan.';
            }

            // Free shipping if minimum order reached
            if ($freeMinOrder > 0 && $cartItems && $cartItems->isNotEmpty()) {
                $cartSubtotal = $cartItems->sum(function ($item) {
                    $price = $item->productVariant?->productPrice?->price ?? $item->product?->productPrice?->price ?? 0;

                    return (float) $price * (int) $item->quantity;
                });
                if ($cartSubtotal >= $freeMinOrder) {
                    $fee = 0.0;
                    $note .= ' (Promo Gratis Ongkir Resto)';
                }
            }

            return response()->json([
                'results' => [
                    [
                        'code' => 'store_courier',
                        'name' => $isFoodPick ? 'Kurir Resto' : 'Kurir Toko',
                        'costs' => [
                            [
                                'service' => $serviceName,
                                'description' => $description,
                                'cost' => [
                                    [
                                        'value' => $fee,
                                        'etd' => $etd,
                                        'note' => $note,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
        }

        if (config('app.is_foodpick') && config('app.foodpick.hide_expedition', true)) {
            return response()->json(['error' => 'Ekspedisi logistik reguler dinonaktifkan untuk pesanan makanan FoodPick.'], 422);
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
            $response = KomerceService::getInternationalCost(
                $origin,
                (string) ($destination ?? ''),
                $weight,
                $courier
            );
        } elseif (BiteshipService::isEnabled()) {
            $destAreaId = $data['dest_area_id'] ?? $data['biteship_area_id'] ?? null;

            $response = BiteshipService::getDomesticCost(
                $origin,
                (string) ($destination ?? ''),
                $weight,
                $courier,
                $addressId,
                $cartItems ?? collect(),
                $destAreaId
            );
        } elseif (KomerceService::isDeliveryEnabled()) {
            $response = KomerceService::getDomesticCost(
                $origin,
                (string) ($destination ?? ''),
                $weight,
                $courier,
                $addressId
            );
        } else {
            $response = KomerceService::getDomesticCost(
                $origin,
                (string) ($destination ?? ''),
                $weight,
                $courier,
                $addressId
            );
        }

        if (isset($response['error'])) {
            return response()->json(['error' => $response['error']], 422);
        }

        return response()->json(['results' => $response['results'] ?? []]);
    }
}
