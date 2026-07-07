<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">


        @php
            $storeIcon = \App\Models\Setting::where('key', 'store_icon')->value('value');
            $title = \App\Models\Setting::where('key', 'store_name')->value('value');
            $primaryColor = \App\Models\Setting::where('key', 'primary_color')->value('value') ?: '#0c4cb4';
            $secondaryColor = \App\Models\Setting::where('key', 'secondary_color')->value('value') ?: '#fa7315';
        @endphp

        @if($storeIcon)
            <link rel="icon" href="{{ $storeIcon }}">
            <link rel="apple-touch-icon" href="{{ $storeIcon }}">
        @else
            <link rel="icon" href="/favicon.ico" sizes="any">
            <link rel="icon" href="/favicon.svg" type="image/svg+xml">
            <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        @endif

        <title>{{$title}} - Toko Online</title>

        @php
            $storeFont = \App\Models\Setting::where('key', 'store_font')->value('value') ?: 'Plus Jakarta Sans';
        @endphp
        
        <style>
            :root {
                --dynamic-font-sans: '{{ $storeFont }}', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji' !important;
                --dynamic-font-outfit: '{{ $storeFont }}', sans-serif !important;
                --dynamic-primary: {{ $primaryColor }} !important;
                --dynamic-secondary: {{ $secondaryColor }} !important;
            }
        </style>
        
        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased overflow-x-hidden">
        <x-inertia::app />
    </body>
</html>
