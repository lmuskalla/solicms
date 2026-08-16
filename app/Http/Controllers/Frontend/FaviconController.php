<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\ThemeFavicon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves a tenant theme's favicon at /favicon/{theme}.
 *
 * The theme slug doubles as the cache key: the URL never changes for a given
 * theme, so the response can be cached as immutable. The blade template
 * (resources/views/app.blade.php) links this URL on tenant pages; changing
 * a theme's favicon file requires a new slug (or cache expiry) to reach
 * browsers again — accepted, see the brief's open question 4.
 *
 * Security: {theme} is a raw route segment, so it is validated against
 * config('themes') (assembled by ThemeServiceProvider from actual theme
 * directories) before it is ever used to look up a file — an unknown or
 * malformed slug never interpolates into a path. ThemeFavicon additionally
 * confines resolution to resources/themes/<slug>/assets/images/.
 */
class FaviconController extends Controller
{
    private const CACHE_HEADERS = ['Cache-Control' => 'public, max-age=31536000, immutable'];

    public function show(string $theme): BinaryFileResponse
    {
        $path = array_key_exists($theme, config('themes'))
            ? ThemeFavicon::resolve($theme)
            : null;

        // No dedicated favicon (or an unknown theme — e.g. a tenant whose
        // theme column references a slug that no longer exists): serve the
        // platform default rather than a broken tab icon.
        return $this->serve($path ?? public_path('favicon.ico'));
    }

    private function serve(string $path): BinaryFileResponse
    {
        return response()->file($path, self::CACHE_HEADERS);
    }
}
