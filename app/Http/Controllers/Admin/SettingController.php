<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\KomerceService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function edit()
    {
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

        $envKeys = [
            'rajaongkir_url' => (bool) config('app.rajaongkir.has_url_env', false),
            'rajaongkir_shipping_cost' => (bool) config('app.rajaongkir.has_shipping_cost_env', false),
            'komerce_delivery_url' => (bool) config('app.rajaongkir.has_delivery_url_env', false),
            'shipping_delivery_key' => (bool) config('app.rajaongkir.has_shipping_delivery_key_env', false),
            'payment_api_key' => (bool) config('app.rajaongkir.has_payment_api_key_env', false),
            'qrisly_api_key' => (bool) config('app.rajaongkir.has_qrisly_api_key_env', false),
        ];

        return Inertia::render('Admin/Settings/Index', [
            'settings' => $settings,
            'env_keys' => $envKeys,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->except(['_token', 'store_logo', 'store_icon']);

        // Handle File Uploads (like Logo)
        if ($request->hasFile('store_logo')) {
            $path = $request->file('store_logo')->store('logos', 'public');
            Setting::updateOrCreate(
                ['key' => 'store_logo'],
                ['value' => '/storage/'.$path]
            );
        }

        if ($request->hasFile('store_icon')) {
            $path = $request->file('store_icon')->store('logos', 'public');
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
