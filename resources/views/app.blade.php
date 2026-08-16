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

        // Per-context favicon. This root view serves tenant public pages,
        // tenant admin and central superadmin alike, so which icon to link
        // depends on who's being served (see jobs/identity_favicons):
        //   - /admin/* and /superadmin/* → the brand mark shown in the
        //     admin sidebar (also covers the login screens)
        //   - tenant public pages → the theme's own favicon, served by
        //     Frontend\FaviconController at /favicon/{theme}
        //   - central domain (no tenant) → the static platform default
        $firstSegment = explode('/', request()->path())[0] ?? '';
        if (in_array($firstSegment, ['admin', 'superadmin'], true)) {
            $favicon = '/images/brand/mark.png';
        } elseif (tenant()) {
            $favicon = "/favicon/{$theme}";
        } else {
            $favicon = '/favicon.ico';
        }
    @endphp
    <link rel="icon" href="{{ $favicon }}">
    @vite(['resources/css/app.css', 'resources/js/app.js', "resources/css/themes/{$theme}.css"])
    @inertiaHead
</head>
<body class="font-sans antialiased">
    @inertia
</body>
</html>
