<?php

namespace App\Http\Controllers\Frontend\Concerns;

/**
 * Shared by every Frontend controller that renders through a theme's
 * component — resources/themes/<theme>/<component>.svelte, resolved from
 * config/themes.php. Falls back to the tenant's own theme's 'wysiwyg'
 * template (every theme is expected to register one — see THEMES.md §7) so
 * an unrecognized template never 500s.
 */
trait ResolvesThemeComponent
{
    private function themeComponent(string $template, string $fallbackTemplate = 'wysiwyg'): string
    {
        $theme = tenant('theme') ?? 'default';
        $component = config("themes.{$theme}.templates.{$template}.component")
            ?? config("themes.{$theme}.templates.{$fallbackTemplate}.component")
            ?? config('themes.default.templates.wysiwyg.component');

        return "{$theme}/{$component}";
    }
}
