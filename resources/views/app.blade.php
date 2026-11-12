<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <!-- Resource Hints & Preconnect for Speed -->
        <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
        <link rel="dns-prefetch" href="https://fonts.bunny.net">

        @php
            try {
                $settingsMap = app()->environment('testing')
                    ? \App\Models\Setting::pluck('value', 'key')->all()
                    : \Illuminate\Support\Facades\Cache::remember('global_settings_map', 60, function () {
                        return \App\Models\Setting::pluck('value', 'key')->all();
                    });
            } catch (\Throwable $e) {
                $settingsMap = [];
            }

            $storeName = $settingsMap['store_name'] ?? config('app.name', 'Bizmate Store');
            $storeDescription = $settingsMap['store_description'] ?? 'Platform e-commerce premium terbaik untuk kebutuhan belanja online Anda.';
            $storeIcon = $settingsMap['store_icon'] ?? null;
            $storeLogo = $settingsMap['store_logo'] ?? null;
            $primaryColor = $settingsMap['primary_color'] ?? '#ea580c';
            $secondaryColor = $settingsMap['secondary_color'] ?? '#f97316';
            $storeFont = $settingsMap['store_font'] ?? 'Plus Jakarta Sans';

            // Check if page provides specific context (e.g. Product page)
            $pageProps = $page['props'] ?? [];
            $product = $pageProps['product'] ?? null;

            if ($product && is_array($product) && !empty($product['name'])) {
                $metaTitle = $product['name'] . ' - ' . $storeName;
                $rawDesc = $product['summary'] ?? $product['description'] ?? $storeDescription;
                $metaDescription = \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', trim(strip_tags((string) $rawDesc))), 160);
                $metaImage = $product['image'] ?? ($product['images'][0]['path'] ?? null) ?: $storeLogo;
                $ogType = 'product';
            } else {
                $metaTitle = $storeName . ' - Toko Online';
                $metaDescription = \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', trim(strip_tags((string) $storeDescription))), 160);
                $metaImage = $storeLogo ?: $storeIcon;
                $ogType = 'website';
            }

            // Ensure absolute URL for social crawlers (WhatsApp, Facebook, Twitter, etc.)
            if ($metaImage) {
                if (!preg_match('/^https?:\/\//i', $metaImage)) {
                    $metaImage = url(ltrim($metaImage, '/'));
                }
            } else {
                $metaImage = asset('apple-touch-icon.png');
            }

            $faviconUrl = $storeIcon ? (preg_match('/^https?:\/\//i', $storeIcon) ? $storeIcon : url(ltrim($storeIcon, '/'))) : null;
            $appleIconUrl = $faviconUrl ?: $metaImage;
            $currentUrl = url()->current();
        @endphp

        <!-- Primary Meta Tags -->
        <title>{{ $metaTitle }}</title>
        <meta name="title" content="{{ $metaTitle }}">
        <meta name="description" content="{{ $metaDescription }}">
        <link rel="canonical" href="{{ $currentUrl }}">

        <!-- Favicons & Touch Icons -->
        @if($faviconUrl)
            <link rel="icon" href="{{ $faviconUrl }}">
        @else
            <link rel="icon" href="/favicon.ico" sizes="any">
            <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        @endif
        <link rel="apple-touch-icon" href="{{ $appleIconUrl }}">

        <!-- Open Graph / Facebook / WhatsApp -->
        <meta property="og:type" content="{{ $ogType }}">
        <meta property="og:url" content="{{ $currentUrl }}">
        <meta property="og:site_name" content="{{ $storeName }}">
        <meta property="og:title" content="{{ $metaTitle }}">
        <meta property="og:description" content="{{ $metaDescription }}">
        <meta property="og:image" content="{{ $metaImage }}">
        <meta property="og:image:secure_url" content="{{ $metaImage }}">
        <meta property="og:image:alt" content="{{ $metaTitle }}">

        <!-- Twitter Card -->
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:url" content="{{ $currentUrl }}">
        <meta name="twitter:title" content="{{ $metaTitle }}">
        <meta name="twitter:description" content="{{ $metaDescription }}">
        <meta name="twitter:image" content="{{ $metaImage }}">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        <x-inertia::head>
            <title inertia>{{ $metaTitle }}</title>
        </x-inertia::head>

        <!-- Dynamic Theme & Font Variables -->
        <style>
            :root {
                --dynamic-font-sans: '{{ $storeFont }}', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji' !important;
                --dynamic-font-outfit: '{{ $storeFont }}', sans-serif !important;
                --dynamic-primary: {{ $primaryColor }} !important;
                --dynamic-secondary: {{ $secondaryColor }} !important;
            }
        </style>
    </head>
    <body class="font-sans antialiased overflow-x-hidden">
        <x-inertia::app />
    </body>
</html>
