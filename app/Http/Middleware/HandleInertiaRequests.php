<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $primaryColor = '#0c4cb4';
        $secondaryColor = '#fa7315';
        $taxEnabled = false;
        $taxPercentage = 0;
        $storeName = config('app.name');
        $storeLogo = null;

        try {
            if (Schema::hasTable('settings')) {
                $primaryColor = Setting::where('key', 'primary_color')->value('value') ?? $primaryColor;
                $secondaryColor = Setting::where('key', 'secondary_color')->value('value') ?? $secondaryColor;
                $taxEnabled = Setting::where('key', 'tax_enabled')->value('value') === '1';
                $taxPercentage = Setting::where('key', 'tax_percentage')->value('value') ?? 0;
                $storeName = Setting::where('key', 'store_name')->value('value') ?? $storeName;
                $storeLogo = Setting::where('key', 'store_logo')->value('value');
            }
        } catch (\Throwable $e) {
            // Fallback when database is not ready
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'theme' => [
                'primary_color' => $primaryColor,
                'secondary_color' => $secondaryColor,
            ],
            'settings' => [
                'tax_enabled' => $taxEnabled,
                'tax_percentage' => (float) $taxPercentage,
                'store_name' => $storeName,
                'store_logo' => $storeLogo,
            ],
        ];
    }
}
