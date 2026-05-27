<?php

namespace App\Http\Controllers;

use App\Models\CustomerAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class CustomerAddressController extends Controller
{
    /**
     * Display a listing of the customer's addresses.
     */
    public function index(Request $request)
    {
        $addresses = CustomerAddress::where('user_id', $request->user()->id)
            ->orderBy('is_primary', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Storefront/Addresses', [
            'addresses' => $addresses,
        ]);
    }

    /**
     * Store a newly created address in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:30',
            'receiver_name' => 'required|string|max:50',
            'phone_number' => 'required|string|max:15',
            'full_address' => 'required|string|max:200',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'note' => 'nullable|string|max:45',
            'is_primary' => 'boolean',
        ]);

        $userId = $request->user()->id;

        // If is_primary is requested, or if this is the first address, make it primary
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
    public function update(Request $request, CustomerAddress $address)
    {
        // Ensure user owns this address
        if ($address->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'label' => 'required|string|max:30',
            'receiver_name' => 'required|string|max:50',
            'phone_number' => 'required|string|max:15',
            'full_address' => 'required|string|max:200',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'note' => 'nullable|string|max:45',
            'is_primary' => 'boolean',
        ]);

        $userId = $request->user()->id;
        $isPrimary = $validated['is_primary'] ?? false;

        if ($isPrimary) {
            CustomerAddress::where('user_id', $userId)->update(['is_primary' => false]);
        } else {
            // Keep as primary if it is the only address
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
    public function destroy(Request $request, CustomerAddress $address)
    {
        // Ensure user owns this address
        if ($address->user_id !== $request->user()->id) {
            abort(403);
        }

        $wasPrimary = $address->is_primary;
        $userId = $address->user_id;

        $address->delete();

        // If the deleted address was primary, make the latest address primary
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
    public function makePrimary(Request $request, CustomerAddress $address)
    {
        // Ensure user owns this address
        if ($address->user_id !== $request->user()->id) {
            abort(403);
        }

        $userId = $request->user()->id;

        CustomerAddress::where('user_id', $userId)->update(['is_primary' => false]);
        $address->update(['is_primary' => true]);

        return redirect()->back()->with('success', 'Alamat utama berhasil diubah.');
    }

    /**
     * Search address query against OpenStreetMap Nominatim API (proxy).
     */
    public function searchApi(Request $request)
    {
        $query = $request->query('q');
        if (empty($query)) {
            return response()->json([]);
        }

        $response = Http::withUserAgent('BurningRoomEcommerce/1.0')
            ->get('https://nominatim.openstreetmap.org/search', [
                'format' => 'json',
                'q' => $query,
                'countrycodes' => 'id', // Indonesia only
                'addressdetails' => 1,
                'limit' => 8,
            ]);

        if ($response->successful()) {
            return response()->json($response->json());
        }

        return response()->json([], 500);
    }

    /**
     * Reverse geocode coordinates against OpenStreetMap Nominatim API (proxy).
     */
    public function reverseApi(Request $request)
    {
        $lat = $request->query('lat');
        $lon = $request->query('lon');

        if (empty($lat) || empty($lon)) {
            return response()->json(['error' => 'Coordinates missing'], 400);
        }

        $response = Http::withUserAgent('BurningRoomEcommerce/1.0')
            ->get('https://nominatim.openstreetmap.org/reverse', [
                'format' => 'json',
                'lat' => $lat,
                'lon' => $lon,
                'addressdetails' => 1,
            ]);

        if ($response->successful()) {
            return response()->json($response->json());
        }

        return response()->json(['error' => 'Geocoding failed'], 500);
    }
}
