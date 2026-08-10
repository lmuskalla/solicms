<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', tenant('locale') ?? app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>{{ config('app.name', 'Platform') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if(app()->isProduction())
        @php
            $theme = tenant('theme') ?? 'default';
        @endphp
        @vite(["resources/css/themes/{$theme}.css"])
    @else
        @vite(['resources/css/themes/default.css', 'resources/css/themes/dvm.css', 'resources/css/themes/geko.css', 'resources/css/themes/tabubruch.css'])
    @endif
    @inertiaHead
</head>
<body class="font-sans antialiased">
    @inertia
</body>
</html>
