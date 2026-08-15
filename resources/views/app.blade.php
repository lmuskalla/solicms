<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', tenant('locale') ?? app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>{{ config('app.name', 'Platform') }}</title>
    @php
        // Only the active tenant's theme CSS ever loads — in dev as well as
        // prod. Previously dev loaded every theme's CSS (default, dvm, geko,
        // tabubruch), so a geko page downloaded and applied dvm/tabubruch
        // utilities too, and cascade conflicts between those builds silently
        // changed layout (wrong column counts, button sizing, headline size)
        // depending on load order. Each theme's own style.css (design tokens)
        // is already isolated per theme by Pages/Frontend/Page.svelte.
        $theme = tenant('theme') ?? 'default';
    @endphp
    @vite(['resources/css/app.css', 'resources/js/app.js', "resources/css/themes/{$theme}.css"])
    @inertiaHead
</head>
<body class="font-sans antialiased">
    @inertia
</body>
</html>
