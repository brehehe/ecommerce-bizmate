<?php

namespace App\Http\Controllers;

use App\Models\MembershipLevel;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontController extends Controller
{
    /**
     * Handle customer registration.
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_active' => true,
        ]);

        $user->assignRole('Customer');

        event(new Registered($user));

        return redirect('/login')->with('success', 'Pendaftaran berhasil! Silakan periksa email Anda untuk memverifikasi akun sebelum masuk.');
    }

    /**
     * Display the About Us page.
     */
    public function about(Request $request): Response
    {
        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Storefront/About', [
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }

    /**
     * Display the Customer Digital Membership Card page.
     */
    public function membership(Request $request): Response
    {
        $levels = MembershipLevel::orderBy('order', 'asc')
            ->with('activeBenefits')
            ->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'name' => $l->name,
                'order' => $l->order,
                'badge_color' => $l->badge_color,
                'icon' => $l->icon,
                'benefits' => $l->activeBenefits->map(fn ($b) => [
                    'label' => $b->label,
                    'icon' => $b->icon,
                    'type' => $b->type,
                    'value' => $b->value,
                ]),
            ]);

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Storefront/Membership', [
            'levels' => $levels,
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }

    /**
     * Display seller storefront page.
     */
    public function sellerStore(Request $request, string $slug): Response
    {
        $seller = User::where('store_slug', $slug)
            ->where('is_seller', true)
            ->firstOrFail();

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Storefront/SellerStore', [
            'seller' => $seller,
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }
}
