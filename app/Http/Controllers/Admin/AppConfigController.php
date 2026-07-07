<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;

class AppConfigController extends Controller
{
    /**
     * Show the hidden app config page.
     */
    public function show(): Response
    {
        $currentAppName = config('app.name');
        $currentStoreName = Setting::where('key', 'store_name')->value('value') ?? $currentAppName;
        $currentStoreAppName = Setting::where('key', 'store_app_name')->value('value') ?? $currentStoreName;

        return Inertia::render('Admin/AppConfig', [
            'currentAppName' => $currentAppName,
            'currentStoreName' => $currentStoreName,
            'currentStoreAppName' => $currentStoreAppName,
        ]);
    }

    /**
     * Update the APP_NAME in .env, store_name, and store_app_name in settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'app_name' => ['required', 'string', 'min:2', 'max:100'],
            'store_name' => ['required', 'string', 'min:2', 'max:100'],
            'store_app_name' => ['required', 'string', 'min:2', 'max:100'],
            'secret_key' => ['required', 'string'],
        ]);

        // Simple secret key check (the URL slug itself acts as the key)
        if ($request->secret_key !== 'zozzuehmqewbobfo') {
            return back()->withErrors(['secret_key' => 'Kunci rahasia tidak valid.']);
        }

        $appName = trim($request->app_name);
        $storeName = trim($request->store_name);
        $storeAppName = trim($request->store_app_name);

        // Update APP_NAME in .env file
        $this->updateEnv('APP_NAME', '"'.$appName.'"');

        // Update store_name in settings table
        Setting::updateOrCreate(
            ['key' => 'store_name'],
            ['value' => $storeName]
        );

        // Update store_app_name in settings table
        Setting::updateOrCreate(
            ['key' => 'store_app_name'],
            ['value' => $storeAppName]
        );

        // Clear config cache
        Artisan::call('config:clear');

        return back()->with('success', "Konfigurasi berhasil disimpan! APP_NAME: \"{$appName}\", Store Name: \"{$storeName}\", App Name: \"{$storeAppName}\".");
    }

    /**
     * Update a value in the .env file.
     */
    private function updateEnv(string $key, string $value): void
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            return;
        }

        $contents = file_get_contents($envPath);

        // Check if key exists
        if (preg_match("/^{$key}=.*/m", $contents)) {
            // Replace existing
            $contents = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $contents);
        } else {
            // Append
            $contents .= "\n{$key}={$value}";
        }

        file_put_contents($envPath, $contents);
    }
}
