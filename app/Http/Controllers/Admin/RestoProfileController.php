<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RestoProfile;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RestoProfileController extends Controller
{
    /**
     * Standard culinary categories for food businesses.
     */
    protected array $culinaryCategories = [
        'Aneka Ayam & Bebek',
        'Kopi & Minuman',
        'Bakso, Mie & Soto',
        'Cepat Saji & Burger',
        'Aneka Nasi Nusantara',
        'Cemilan & Jajanan',
        'Seafood & Ikan Bakar',
        'Roti & Dessert Manis',
        'Masakan Padang & Minang',
        'Sate & Olahan Daging',
        'Menu Sehat & Diet',
    ];

    /**
     * Display the restaurant profile management page.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // If Super Admin / Admin, allow managing specific resto via ?resto_id=UUID or default
        $restoProfile = $user->restoProfile;

        if ($user->hasRole(['Super Admin', 'Admin'])) {
            if ($request->filled('resto_id')) {
                $restoProfile = RestoProfile::find($request->input('resto_id')) ?: $restoProfile;
            }
            if (! $restoProfile) {
                // Default to Ikan Goreng Nikmat Abadi or latest active resto
                $restoProfile = RestoProfile::where('slug', 'ikan-goreng-nikmat-abadi')->first()
                    ?: (RestoProfile::latest()->first() ?: RestoProfile::first());
            }
        }

        if (! $restoProfile) {
            // Create initial profile if none exists
            $restoProfile = RestoProfile::create([
                'user_id' => $user->id,
                'resto_name' => $user->store_name ?: ($user->name.' Kitchen'),
                'slug' => $user->store_slug ?: Str::slug($user->store_name ?: $user->name),
                'culinary_category' => $this->culinaryCategories[0],
                'description' => $user->store_description ?: 'Resto kuliner lezat & higienis.',
                'address' => $user->store_address ?: 'Jl. Utama No. 1',
                'latitude' => -7.983908,
                'longitude' => 112.621391,
                'avg_prep_time_minutes' => (int) config('app.foodpick.default_prep_minutes', 20),
                'allow_pickup' => true,
                'allow_delivery' => true,
                'max_delivery_radius_km' => 10,
                'is_open_now' => true,
                'approval_status' => 'approved',
                'operating_hours' => [
                    'monday' => ['open' => '09:00', 'close' => '21:00', 'is_closed' => false],
                    'tuesday' => ['open' => '09:00', 'close' => '21:00', 'is_closed' => false],
                    'wednesday' => ['open' => '09:00', 'close' => '21:00', 'is_closed' => false],
                    'thursday' => ['open' => '09:00', 'close' => '21:00', 'is_closed' => false],
                    'friday' => ['open' => '09:00', 'close' => '21:00', 'is_closed' => false],
                    'saturday' => ['open' => '09:00', 'close' => '22:00', 'is_closed' => false],
                    'sunday' => ['open' => '09:00', 'close' => '22:00', 'is_closed' => false],
                ],
            ]);
        }

        $availableRestos = $user->hasRole(['Super Admin', 'Admin'])
            ? RestoProfile::select('id', 'resto_name', 'slug', 'approval_status')->get()
            : [];

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Admin/RestoProfile/Index', [
            'restoProfile' => [
                'id' => $restoProfile->id,
                'user_id' => $restoProfile->user_id,
                'resto_name' => $restoProfile->resto_name,
                'slug' => $restoProfile->slug,
                'culinary_category' => $restoProfile->culinary_category,
                'description' => $restoProfile->description,
                'address' => $restoProfile->address,
                'latitude' => (float) $restoProfile->latitude,
                'longitude' => (float) $restoProfile->longitude,
                'avg_prep_time_minutes' => (int) ($restoProfile->avg_prep_time_minutes ?: 20),
                'allow_pickup' => (bool) $restoProfile->allow_pickup,
                'allow_delivery' => (bool) $restoProfile->allow_delivery,
                'max_delivery_radius_km' => (float) ($restoProfile->max_delivery_radius_km ?: 10),
                'is_open_now' => $restoProfile->is_open_now !== false,
                'approval_status' => $restoProfile->approval_status ?: 'approved',
                'operating_hours' => $restoProfile->getResolvedOperatingHours(),
                'payment_methods' => $restoProfile->getResolvedPaymentMethods(),
                'delivery_fee_type' => ($restoProfile->delivery_fee_type === 'fixed' ? 'flat' : $restoProfile->delivery_fee_type) ?: 'flat',
                'delivery_fee_base' => (float) ($restoProfile->delivery_fee_base ?? 10000),
                'delivery_free_min_order' => (float) ($restoProfile->delivery_free_min_order ?? 0),
                'qris_image_path' => $restoProfile->qris_image_path ? (str_starts_with($restoProfile->qris_image_path, 'http') ? $restoProfile->qris_image_path : ('/storage/'.ltrim($restoProfile->qris_image_path, '/'))) : null,
                'logo_path' => $restoProfile->logo_path ? (str_starts_with($restoProfile->logo_path, 'http') ? $restoProfile->logo_path : ('/storage/'.ltrim($restoProfile->logo_path, '/'))) : null,
                'banner_path' => $restoProfile->banner_path ? (str_starts_with($restoProfile->banner_path, 'http') ? $restoProfile->banner_path : ('/storage/'.ltrim($restoProfile->banner_path, '/'))) : null,
                'store_slug' => $restoProfile->user?->store_slug ?: $restoProfile->slug,
            ],
            'availableRestos' => $availableRestos,
            'culinaryCategories' => $this->culinaryCategories,
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }

    /**
     * Update the restaurant profile.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $restoProfile = $user->restoProfile;

        if ($user->hasRole(['Super Admin', 'Admin'])) {
            if ($request->filled('resto_id')) {
                $restoProfile = RestoProfile::find($request->input('resto_id')) ?: $restoProfile;
            }
            if (! $restoProfile) {
                $restoProfile = RestoProfile::where('slug', 'ikan-goreng-nikmat-abadi')->first()
                    ?: (RestoProfile::latest()->first() ?: RestoProfile::first());
            }
        }

        if (! $restoProfile) {
            return back()->with('error', 'Profil resto tidak ditemukan.');
        }

        // Normalize boolean fields that may arrive as string "true"/"false" via multipart/form-data
        $merges = [];
        foreach (['allow_pickup', 'allow_delivery', 'is_open_now'] as $boolField) {
            if ($request->has($boolField)) {
                $merges[$boolField] = filter_var($request->input($boolField), FILTER_VALIDATE_BOOLEAN);
            }
        }
        if ($request->input('delivery_fee_type') === 'fixed') {
            $merges['delivery_fee_type'] = 'flat';
        }
        if (! empty($merges)) {
            $request->merge($merges);
        }

        $validated = $request->validate([
            'resto_id' => 'nullable|string',
            'resto_name' => 'required|string|max:255',
            'culinary_category' => 'required|string|max:100',
            'description' => 'nullable|string|max:1000',
            'address' => 'required|string|max:500',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'avg_prep_time_minutes' => 'required|integer|min:5|max:180',
            'max_delivery_radius_km' => 'nullable|numeric|min:0.5|max:100',
            'allow_pickup' => 'boolean',
            'allow_delivery' => 'boolean',
            'is_open_now' => 'boolean',
            'operating_hours' => 'nullable|array',
            'payment_methods' => 'nullable|array',
            'delivery_fee_type' => 'nullable|string|in:flat,per_km,fixed',
            'delivery_fee_base' => 'nullable|numeric|min:0',
            'delivery_free_min_order' => 'nullable|numeric|min:0',
            'logo' => 'nullable|image|max:3072',
            'banner' => 'nullable|image|max:5120',
            'qris_image' => 'nullable|image|max:3072',
        ]);

        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('resto/logos', 'public');
            $restoProfile->logo_path = $logoPath;
            if ($user->id === $restoProfile->user_id) {
                $user->store_avatar = $logoPath;
            }
        }

        if ($request->hasFile('banner')) {
            $bannerPath = $request->file('banner')->store('resto/banners', 'public');
            $restoProfile->banner_path = $bannerPath;
            if ($user->id === $restoProfile->user_id) {
                $user->store_banner = $bannerPath;
            }
        }

        if ($request->hasFile('qris_image')) {
            $qrisPath = $request->file('qris_image')->store('resto/qris', 'public');
            $restoProfile->qris_image_path = $qrisPath;
        }

        $restoProfile->resto_name = $validated['resto_name'];
        $restoProfile->culinary_category = $validated['culinary_category'];
        $restoProfile->description = $validated['description'] ?? '';
        $restoProfile->address = $validated['address'];
        $restoProfile->latitude = $validated['latitude'] ?? $restoProfile->latitude;
        $restoProfile->longitude = $validated['longitude'] ?? $restoProfile->longitude;
        $restoProfile->avg_prep_time_minutes = $validated['avg_prep_time_minutes'];
        $restoProfile->max_delivery_radius_km = $validated['max_delivery_radius_km'] ?? 10;
        $restoProfile->delivery_fee_type = ($validated['delivery_fee_type'] ?? 'flat') === 'fixed' ? 'flat' : ($validated['delivery_fee_type'] ?? 'flat');
        $restoProfile->delivery_fee_base = $validated['delivery_fee_base'] ?? $restoProfile->delivery_fee_base ?? 10000;
        $restoProfile->delivery_free_min_order = $validated['delivery_free_min_order'] ?? $restoProfile->delivery_free_min_order ?? 0;
        $restoProfile->allow_pickup = (bool) ($validated['allow_pickup'] ?? true);
        $restoProfile->allow_delivery = (bool) ($validated['allow_delivery'] ?? true);
        $restoProfile->is_open_now = (bool) ($validated['is_open_now'] ?? true);

        if (isset($validated['payment_methods']) && is_array($validated['payment_methods'])) {
            $pm = $validated['payment_methods'];
            foreach ($pm as $key => $method) {
                if (is_array($method) && isset($method['is_active'])) {
                    $pm[$key]['is_active'] = filter_var($method['is_active'], FILTER_VALIDATE_BOOLEAN);
                }
            }
            if (! empty($restoProfile->qris_image_path) && isset($pm['qris'])) {
                $pm['qris']['image_path'] = $restoProfile->qris_image_path;
            }
            $restoProfile->payment_methods = $pm;
        }

        if (isset($validated['operating_hours'])) {
            $restoProfile->operating_hours = $validated['operating_hours'];
        }

        $restoProfile->save();

        // Keep User seller info in sync
        $restoUser = $restoProfile->user;
        if ($restoUser) {
            $restoUser->store_name = $restoProfile->resto_name;
            $restoUser->store_description = $restoProfile->description;
            $restoUser->save();
        }

        return back()->with('success', 'Profil dapur resto berhasil diperbarui!');
    }

    /**
     * Emergency toggle to pause or open the kitchen.
     */
    public function toggleOpen(Request $request): RedirectResponse
    {
        $user = $request->user();
        $restoProfile = $user->restoProfile;

        if (! $restoProfile && $user->hasRole(['Super Admin', 'Admin'])) {
            $restoProfile = RestoProfile::first();
        }

        if (! $restoProfile) {
            return back()->with('error', 'Profil resto tidak ditemukan.');
        }

        $restoProfile->is_open_now = ! $restoProfile->is_open_now;
        $restoProfile->save();

        $statusMsg = $restoProfile->is_open_now
            ? 'Resto sekarang BUKA! Siap menerima pesanan baru.'
            : 'Resto TUTUP SEMENTARA! Pesanan baru dihentikan sementara.';

        return back()->with('success', $statusMsg);
    }
}
