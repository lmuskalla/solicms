<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Assembles config('themes') from resources/themes/<slug>/theme.php — one
 * file per theme, colocated with that theme's templates/components, instead
 * of one growing array in config/themes.php. Every existing consumer keeps
 * reading config("themes.{$slug}...") exactly as before; only where the
 * array comes from changes.
 *
 * Runs in register() (before config:cache would serialize the merged
 * result), same as Laravel's own mergeConfigFrom() for package configs.
 */
class ThemeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $themes = [];

        foreach (glob(resource_path('themes/*/theme.php')) as $file) {
            $slug = basename(dirname($file));
            $themes[$slug] = require $file;
        }

        $this->app['config']->set('themes', $themes);
    }
}
