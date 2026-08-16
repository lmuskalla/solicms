<?php

namespace App\Services;

/**
 * Resolves a theme's favicon file by filename convention — see THEMES.md §1.
 *
 * A theme drops a square, light-background-safe mark into its own
 * `assets/images/` folder as `favicon.<ext>` and it is picked up
 * automatically — no registration, no config. To keep old themes working
 * without changes, resolution falls back from the dedicated favicon to the
 * theme's existing mark (`icon.<ext>`, then `logo.<ext>`); `null` means "no
 * usable favicon here", and the caller (Frontend\FaviconController) falls
 * back to the platform default then.
 *
 * The theme slug is validated before it is ever interpolated into a path —
 * an unvalidated `{theme}` is a path-traversal/asset-leak vector. Files are
 * only ever resolved inside `resources/themes/<slug>/assets/images/`.
 */
class ThemeFavicon
{
    /** Candidate base names, in priority order. */
    private const CANDIDATES = ['favicon', 'icon', 'logo'];

    /** Image extensions that can serve as a favicon, in priority order. */
    private const EXTENSIONS = ['svg', 'png', 'ico', 'webp', 'jpg', 'jpeg', 'gif'];

    /**
     * @return string|null Absolute path to the theme's favicon, or null when
     *                     the theme is invalid or has no usable mark.
     */
    public static function resolve(string $theme): ?string
    {
        // Theme slugs are directory names under resources/themes/ — anything
        // containing a path separator, dot, or other unexpected character is
        // not a theme and cannot resolve to a file.
        if (preg_match('/^[a-z0-9_-]+$/i', $theme) !== 1) {
            return null;
        }

        $imagesDir = resource_path("themes/{$theme}/assets/images");

        foreach (self::CANDIDATES as $name) {
            foreach (self::EXTENSIONS as $ext) {
                $path = "{$imagesDir}/{$name}.{$ext}";

                if (is_file($path)) {
                    return $path;
                }
            }
        }

        return null;
    }
}
