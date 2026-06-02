<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        @php
            $storeIcon = \App\Models\Setting::where('key', 'store_icon')->value('value');
        @endphp

        @if($storeIcon)
            <link rel="icon" href="{{ $storeIcon }}">
            <link rel="apple-touch-icon" href="{{ $storeIcon }}">
        @else
            <link rel="icon" href="/favicon.ico" sizes="any">
            <link rel="icon" href="/favicon.svg" type="image/svg+xml">
            <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        @endif

        <!-- Google Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        
        @php
            $storeFont = \App\Models\Setting::where('key', 'store_font')->value('value') ?: 'Plus Jakarta Sans';
            $fontQuery = str_replace(' ', '+', $storeFont);
        @endphp
        
        @if(!in_array($storeFont, ['Arial', 'Verdana', 'Helvetica', 'Times New Roman', 'Georgia']))
            <link href="https://fonts.googleapis.com/css2?family={{ $fontQuery }}:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,700&display=swap" rel="stylesheet">
        @endif
        
        <style>
            :root {
                --dynamic-font-sans: '{{ $storeFont }}', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji' !important;
                --dynamic-font-outfit: '{{ $storeFont }}', sans-serif !important;
            }
        </style>
        
        <!-- Tabler Icons -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">

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
