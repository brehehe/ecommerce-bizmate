<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ImageHelper;
use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\Setting;
use App\Services\KomerceService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class SettingController extends Controller
{
    protected function authorizeAdminOnly(Request $request): void
    {
        $user = $request->user();
        if ($user && $user->is_seller && ! $user->hasAnyRole(['Super Admin', 'Admin'])) {
            abort(403, 'Akses ini khusus untuk Admin.');
        }
    }

    public function edit(Request $request)
    {
        $this->authorizeAdminOnly($request);
        $user = $request->user();
        $isSeller = $user && $user->is_seller && ! $user->hasAnyRole(['Super Admin', 'Admin']);

        // Get all settings and format as key => value pair
        $settings = Setting::pluck('value', 'key')->toArray();

        // Check if there is an env variable overriding these
        $keys = [
            'rajaongkir_url' => 'app.rajaongkir.url',
            'rajaongkir_shipping_cost' => 'app.rajaongkir.shipping_cost',
            'komerce_delivery_url' => 'app.rajaongkir.delivery_url',
            'shipping_delivery_key' => 'app.rajaongkir.shipping_delivery_key',
            'payment_api_key' => 'app.rajaongkir.payment_api_key',
            'qrisly_api_key' => 'app.rajaongkir.qrisly_api_key',
        ];

        foreach ($keys as $dbKey => $configPath) {
            $settings[$dbKey] = KomerceService::getSetting($dbKey, $configPath);
        }

        $settings['membership_enabled'] = (bool) config('app.membership_enabled', true);
        $settings['midtrans_enabled'] = (bool) config('app.midtrans_enabled', true);
        $settings['logistic_enabled'] = (bool) config('app.logistic_enabled', true);
        $settings['enable_3d_models'] = (bool) config('app.enable_3d_models', true);
        $settings['show_brands'] = ($settings['show_brands'] ?? '1') !== '0';

        if ($isSeller) {
            $settings['store_name'] = $user->store_name ?: $user->name;
            $settings['store_description'] = $user->store_description ?? '';
            $settings['store_phone'] = $user->phone_number ?? '';
            $settings['store_email'] = $user->email ?? '';
            if ($user->store_logo) {
                $settings['store_logo'] = str_starts_with($user->store_logo, '/storage/') ? $user->store_logo : '/storage/'.$user->store_logo;
            } elseif ($user->avatar) {
                $settings['store_logo'] = str_starts_with($user->avatar, '/storage/') ? $user->avatar : '/storage/'.$user->avatar;
            }
            $settings['is_seller_settings'] = true;
        }

        $envKeys = [
            'rajaongkir_url' => (bool) config('app.rajaongkir.has_url_env', false),
            'rajaongkir_shipping_cost' => (bool) config('app.rajaongkir.has_shipping_cost_env', false),
            'komerce_delivery_url' => (bool) config('app.rajaongkir.has_delivery_url_env', false),
            'shipping_delivery_key' => (bool) config('app.rajaongkir.has_shipping_delivery_key_env', false),
            'payment_api_key' => (bool) config('app.rajaongkir.has_payment_api_key_env', false),
            'qrisly_api_key' => (bool) config('app.rajaongkir.has_qrisly_api_key_env', false),
            'show_checkout_settings' => (bool) config('app.show_checkout_settings', true),
        ];

        $couriers = Courier::orderBy('order')->get(['id', 'code', 'name', 'is_active', 'order']);

        return Inertia::render('Admin/Settings/Index', [
            'settings' => $settings,
            'env_keys' => $envKeys,
            'couriers' => $couriers,
            'isSeller' => $isSeller,
        ]);
    }

    public function update(Request $request)
    {
        $this->authorizeAdminOnly($request);
        $user = $request->user();
        $isSeller = $user && $user->is_seller && ! $user->hasAnyRole(['Super Admin', 'Admin']);

        $request->validate([
            'store_logo' => 'nullable|image|max:2048',
            'store_icon' => 'nullable|image|max:2048',
        ]);

        if ($isSeller) {
            if ($request->hasFile('store_logo')) {
                $path = ImageHelper::compressAndStore($request->file('store_logo'), 'logos', 'public');
                $user->store_logo = '/storage/'.$path;
            }
            if ($request->filled('store_name')) {
                $user->store_name = $request->input('store_name');
                $user->store_slug = Str::slug($request->input('store_name')).'-'.substr($user->id, 0, 5);
            }
            if ($request->has('store_description')) {
                $user->store_description = $request->input('store_description');
            }
            if ($request->filled('store_phone')) {
                $user->phone_number = $request->input('store_phone');
            }
            if ($request->filled('store_email')) {
                $user->email = $request->input('store_email');
            }
            $user->save();

            return redirect()->back()->with('success', 'Pengaturan toko Anda berhasil disimpan.');
        }

        $data = $request->except(['_token', 'store_logo', 'store_icon']);

        // Handle File Uploads (like Logo)
        if ($request->hasFile('store_logo')) {
            $path = ImageHelper::compressAndStore($request->file('store_logo'), 'logos', 'public');
            Setting::updateOrCreate(
                ['key' => 'store_logo'],
                ['value' => '/storage/'.$path]
            );
        }

        if ($request->hasFile('store_icon')) {
            $path = ImageHelper::compressAndStore($request->file('store_icon'), 'logos', 'public');
            Setting::updateOrCreate(
                ['key' => 'store_icon'],
                ['value' => '/storage/'.$path]
            );
        }

        // Env-locked keys
        $envKeyMap = [
            'rajaongkir_url' => 'app.rajaongkir.has_url_env',
            'rajaongkir_shipping_cost' => 'app.rajaongkir.has_shipping_cost_env',
            'komerce_delivery_url' => 'app.rajaongkir.has_delivery_url_env',
            'shipping_delivery_key' => 'app.rajaongkir.has_shipping_delivery_key_env',
            'payment_api_key' => 'app.rajaongkir.has_payment_api_key_env',
            'qrisly_api_key' => 'app.rajaongkir.has_qrisly_api_key_env',
        ];

        // Handle other key-value pairs
        foreach ($data as $key => $value) {
            // Skip saving to database if the key is defined in .env
            if (isset($envKeyMap[$key]) && config($envKeyMap[$key], false)) {
                continue;
            }

            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            } elseif (is_array($value)) {
                $value = json_encode($value);
            }

            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        // Sync Komerce payment methods to payment_methods table
        KomerceService::syncPaymentMethods();

        return redirect()->back()->with('success', 'Pengaturan berhasil disimpan.');
    }

    public function completeTour()
    {
        Setting::updateOrCreate(
            ['key' => 'setup_tour_completed'],
            ['value' => '1']
        );

        return redirect()->route('admin.dashboard')->with('success', 'Setup awal toko berhasil diselesaikan!');
    }
}
