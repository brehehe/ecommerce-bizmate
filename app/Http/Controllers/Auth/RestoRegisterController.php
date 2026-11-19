<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\RestoProfile;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RestoRegisterController extends Controller
{
    /**
     * Show the resto registration form.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        // If user already has an active resto profile, redirect to admin dashboard
        if ($user && $user->is_seller && $user->restoProfile) {
            return redirect()->route('admin.dashboard')->with('info', 'Anda sudah terdaftar sebagai Mitra Resto.');
        }

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        $culinaryCategories = [
            'Aneka Nasi & Lauk',
            'Ayam & Bebek',
            'Kopi & Minuman',
            'Cepat Saji & Burger',
            'Bakmi, Bakso & Soto',
            'Seafood',
            'Jajanan, Snack & Martabak',
            'Kue, Roti & Dessert',
            'Makanan Sehat & Vegetarian',
            'Lainnya',
        ];

        return Inertia::render('Auth/RegisterResto', [
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
            'isFoodPick' => (bool) config('app.is_foodpick', false),
            'culinaryCategories' => $culinaryCategories,
            'currentUser' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
            ] : null,
        ]);
    }

    /**
     * Handle registration of a new resto merchant.
     */
    public function register(Request $request): RedirectResponse
    {
        $user = $request->user();
        $isGuest = ($user === null);

        $rules = [
            'resto_name' => 'required|string|max:255',
            'culinary_category' => 'required|string|max:100',
            'description' => 'nullable|string|max:1000',
            'full_address' => 'required|string|max:500',
            'province_name' => 'nullable|string|max:100',
            'regency_name' => 'nullable|string|max:100',
            'district_name' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'avg_prep_time_minutes' => 'nullable|integer|min:5|max:180',
            'allow_pickup' => 'nullable|boolean',
            'allow_delivery' => 'nullable|boolean',
            'operating_hours' => 'nullable|array',
        ];

        if ($isGuest) {
            $rules['name'] = 'required|string|max:255';
            $rules['email'] = 'required|string|email|max:255|unique:users,email';
            $rules['phone_number'] = 'required|string|min:8|max:25|unique:users,phone_number';
            $rules['password'] = 'required|string|min:8|confirmed';
        } else {
            if (empty($user->phone_number)) {
                $rules['phone_number'] = 'required|string|min:8|max:25';
            }
        }

        $validated = $request->validate($rules);

        return DB::transaction(function () use ($validated, $user, $isGuest, $request) {
            if ($isGuest) {
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => strtolower(trim($validated['email'])),
                    'phone_number' => $validated['phone_number'],
                    'password' => Hash::make($validated['password']),
                    'is_active' => true,
                    'is_seller' => true,
                    'store_name' => $validated['resto_name'],
                    'store_slug' => Str::slug($validated['resto_name']).'-'.substr(Str::uuid()->toString(), 0, 5),
                    'store_description' => $validated['description'] ?? null,
                    'email_verified_at' => now(),
                ]);

                if (Role::where('name', 'Seller')->exists()) {
                    $user->assignRole('Seller');
                }
                if (Role::where('name', 'Customer')->exists()) {
                    $user->assignRole('Customer');
                }

                Auth::login($user);
                $request->session()->regenerate();
            } else {
                $updateData = [
                    'is_seller' => true,
                    'store_name' => $validated['resto_name'],
                    'store_slug' => Str::slug($validated['resto_name']).'-'.substr($user->id, 0, 5),
                    'store_description' => $validated['description'] ?? null,
                ];

                if (! empty($validated['phone_number']) && empty($user->phone_number)) {
                    $updateData['phone_number'] = $validated['phone_number'];
                }

                $user->update($updateData);

                if (Role::where('name', 'Seller')->exists() && ! $user->hasRole('Seller')) {
                    $user->assignRole('Seller');
                }
            }

            // Create or update RestoProfile
            RestoProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'resto_name' => $validated['resto_name'],
                    'slug' => Str::slug($validated['resto_name']).'-'.substr($user->id, 0, 5),
                    'culinary_category' => $validated['culinary_category'],
                    'description' => $validated['description'] ?? null,
                    'address' => $validated['full_address'],
                    'latitude' => $validated['latitude'] ?? null,
                    'longitude' => $validated['longitude'] ?? null,
                    'operating_hours' => $validated['operating_hours'] ?? [
                        'monday' => ['active' => true, 'open' => '09:00', 'close' => '21:00'],
                        'tuesday' => ['active' => true, 'open' => '09:00', 'close' => '21:00'],
                        'wednesday' => ['active' => true, 'open' => '09:00', 'close' => '21:00'],
                        'thursday' => ['active' => true, 'open' => '09:00', 'close' => '21:00'],
                        'friday' => ['active' => true, 'open' => '09:00', 'close' => '21:00'],
                        'saturday' => ['active' => true, 'open' => '09:00', 'close' => '22:00'],
                        'sunday' => ['active' => true, 'open' => '09:00', 'close' => '22:00'],
                    ],
                    'avg_prep_time_minutes' => $validated['avg_prep_time_minutes'] ?? (int) config('app.foodpick.default_prep_minutes', 20),
                    'allow_pickup' => $validated['allow_pickup'] ?? true,
                    'allow_delivery' => $validated['allow_delivery'] ?? true,
                    'is_open_now' => true,
                    'approval_status' => 'approved',
                ]
            );

            // Create primary store / kitchen address for pickup & distance calculation
            $user->customerAddresses()->update(['is_primary' => false]);
            $user->customerAddresses()->create([
                'label' => 'Dapur / Outlet Utama',
                'receiver_name' => $validated['resto_name'],
                'phone_number' => $user->phone_number ?? ($validated['phone_number'] ?? '-'),
                'full_address' => $validated['full_address'],
                'province_name' => $validated['province_name'] ?? 'Indonesia',
                'regency_name' => $validated['regency_name'] ?? '-',
                'district_name' => $validated['district_name'] ?? '-',
                'postal_code' => $validated['postal_code'] ?? '00000',
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'is_primary' => true,
            ]);

            return redirect()->route('admin.dashboard')
                ->with('success', "Selamat! Resto '{$validated['resto_name']}' berhasil didaftarkan dan siap beroperasi.");
        });
    }
}
