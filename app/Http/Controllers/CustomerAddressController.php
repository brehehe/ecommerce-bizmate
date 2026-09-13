<?php

namespace App\Http\Controllers;

use App\Http\Requests\Customer\StoreAddressRequest;
use App\Http\Requests\Customer\UpdateAddressRequest;
use App\Models\CustomerAddress;
use App\Models\Setting;
use App\Services\BiteshipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class CustomerAddressController extends Controller
{
    /**
     * Display a listing of the customer's addresses.
     */
    public function index(Request $request): Response
    {
        $addresses = CustomerAddress::where('user_id', $request->user()->id)
            ->orderBy('is_primary', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        $isBiteshipEnabled = BiteshipService::isEnabled();
        $isRajaOngkirEnabled = ! empty(Setting::where('key', 'rajaongkir_shipping_cost')->value('value'));

        return Inertia::render('Storefront/Addresses', [
            'addresses' => $addresses,
            'isBiteshipEnabled' => $isBiteshipEnabled,
            'isRajaOngkirEnabled' => $isRajaOngkirEnabled,
        ]);
    }

    /**
     * Display a listing of the seller's addresses in the admin panel.
     */
    public function adminIndex(Request $request): Response
    {
        $user = $request->user();

        if (! $user->is_seller) {
            abort(403, 'Akses hanya untuk seller.');
        }

        $addresses = CustomerAddress::where('user_id', $user->id)
            ->orderBy('is_primary', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        $isBiteshipEnabled = BiteshipService::isEnabled();
        $isRajaOngkirEnabled = ! empty(Setting::where('key', 'rajaongkir_shipping_cost')->value('value'));

        return Inertia::render('Admin/SellerAddresses', [
            'addresses' => $addresses,
            'isBiteshipEnabled' => $isBiteshipEnabled,
            'isRajaOngkirEnabled' => $isRajaOngkirEnabled,
        ]);
    }

    /**
     * Store a newly created address in storage.
     */
    public function store(StoreAddressRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $userId = $user->id;

        // Seller single store address enforcement
        if ($user->is_seller) {
            $existingAddress = CustomerAddress::where('user_id', $userId)->first();
            if ($existingAddress) {
                $validated['is_primary'] = true;
                $existingAddress->update($validated);

                return redirect()->back()->with('success', 'Alamat toko berhasil diperbarui.');
            }
        }

        $isFirst = CustomerAddress::where('user_id', $userId)->count() === 0;
        $isPrimary = $validated['is_primary'] ?? false;

        if ($isPrimary || $isFirst) {
            CustomerAddress::where('user_id', $userId)->update(['is_primary' => false]);
            $validated['is_primary'] = true;
        }

        $address = new CustomerAddress($validated);
        $address->user_id = $userId;
        $address->save();

        return redirect()->back()->with('success', 'Alamat berhasil ditambahkan.');
    }

    /**
     * Update the specified address in storage.
     */
    public function update(UpdateAddressRequest $request, CustomerAddress $address): RedirectResponse
    {
        $this->authorize('update', $address);

        $validated = $request->validated();
        $userId = $request->user()->id;
        $isPrimary = $validated['is_primary'] ?? false;

        if ($isPrimary) {
            CustomerAddress::where('user_id', $userId)->update(['is_primary' => false]);
        } else {
            $isOnly = CustomerAddress::where('user_id', $userId)->count() === 1;
            if ($isOnly || $address->is_primary) {
                $validated['is_primary'] = true;
            }
        }

        $address->update($validated);

        return redirect()->back()->with('success', 'Alamat berhasil diperbarui.');
    }

    /**
     * Remove the specified address from storage.
     */
    public function destroy(Request $request, CustomerAddress $address): RedirectResponse
    {
        $this->authorize('delete', $address);

        if ($request->user()->is_seller) {
            $addressCount = CustomerAddress::where('user_id', $request->user()->id)->count();
            if ($addressCount <= 1) {
                return redirect()->back()->with('error', 'Alamat Toko Penjual tidak dapat dihapus. Silakan lakukan edit jika ada perubahan.');
            }
        }

        $wasPrimary = $address->is_primary;
        $userId = $address->user_id;

        $address->delete();

        if ($wasPrimary) {
            $latest = CustomerAddress::where('user_id', $userId)->latest()->first();
            if ($latest) {
                $latest->update(['is_primary' => true]);
            }
        }

        return redirect()->back()->with('success', 'Alamat berhasil dihapus.');
    }

    /**
     * Set the specified address as the primary address.
     */
    public function makePrimary(Request $request, CustomerAddress $address): RedirectResponse
    {
        $this->authorize('update', $address);

        $userId = $request->user()->id;

        CustomerAddress::where('user_id', $userId)->update(['is_primary' => false]);
        $address->update(['is_primary' => true]);

        return redirect()->back()->with('success', 'Alamat utama berhasil diubah.');
    }

    /**
     * Reverse geocode coordinates using OpenStreetMap Nominatim.
     */
    public function reverseGeocode(Request $request): JsonResponse
    {
        $lat = $request->input('lat');
        $lng = $request->input('lng');

        if (! $lat || ! $lng) {
            return response()->json(['error' => 'Latitude and longitude are required.'], 400);
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'BisnisMate-Ecommerce/1.0',
            ])->get('https://nominatim.openstreetmap.org/reverse', [
                'lat' => $lat,
                'lon' => $lng,
                'format' => 'json',
                'addressdetails' => 1,
            ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json(['error' => 'Failed to retrieve address details.'], 500);
        } catch (\Exception $e) {
            Log::error('Reverse geocode error: '.$e->getMessage());

            return response()->json(['error' => 'Geocoding service unavailable.'], 500);
        }
    }

    /**
     * Proxy Biteship area search.
     */
    public function searchBiteshipAreas(Request $request): JsonResponse
    {
        $query = $request->query('q');
        if (empty($query) || strlen(trim($query)) < 3) {
            return response()->json(['success' => false, 'areas' => []]);
        }

        $apiKey = BiteshipService::getBiteshipKey();
        if (empty($apiKey)) {
            return response()->json(['success' => false, 'areas' => [], 'error' => 'Biteship API key not configured.'], 422);
        }

        try {
            $response = Http::withHeaders([
                'authorization' => $apiKey,
                'Accept' => 'application/json',
            ])
                ->timeout(10)
                ->get(BiteshipService::getBiteshipUrl().'/maps/areas', [
                    'countries' => 'ID',
                    'input' => $query,
                    'type' => 'single',
                ]);

            if ($response->successful()) {
                $data = $response->json();

                return response()->json([
                    'success' => true,
                    'areas' => $data['areas'] ?? [],
                ]);
            }

            return response()->json(['success' => false, 'areas' => []]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'areas' => [], 'error' => $e->getMessage()], 500);
        }
    }
}
