<?php

namespace App\Services\ThemeMigrations;

use App\Models\Page;
use Closure;

/**
 * Base class for a theme's content migrations — resources/themes/<slug>/
 * migrations/*.php, each returning `new class extends ThemeMigration`, run
 * by App\Services\ThemeMigrator. Unlike a schema migration, these operate on
 * a template's section *keys* (a rename is the whole point — label/type/
 * order need no migration at all now that App\Models\Section resolves them
 * live from config/themes.php). up()/down() run inside the tenant already
 * initialized by the migrator, exactly like a database migration runs
 * inside its connection.
 */
abstract class ThemeMigration
{
    abstract public function up(): void;

    abstract public function down(): void;

    /**
     * Renames a section's key on every page using $template that currently
     * has $from, preserving that row's value and media — the whole reason
     * this exists instead of "delete $from, create $to empty". Pages that
     * already have $to (e.g. self-healed by Admin\PageController since this
     * migration was written) are left alone rather than overwritten.
     */
    protected function renameKey(string $template, string $from, string $to): void
    {
        $this->eachPage($template, function (Page $page) use ($from, $to) {
            $section = $page->sections()->where('key', $from)->first();

            if ($section && ! $page->sections()->where('key', $to)->exists()) {
                $section->update(['key' => $to]);
            }
        });
    }

    /**
     * Deletes the section row for $key on every page using $template —
     * for a field the theme is dropping for good. Actually destroys the
     * value/media, so down() should only recreate the row (empty), never
     * the deleted content itself.
     */
    protected function dropKey(string $template, string $key): void
    {
        $this->eachPage($template, function (Page $page) use ($key) {
            $page->sections()->where('key', $key)->first()?->delete();
        });
    }

    /**
     * Low-level primitive the two helpers above are built on — for a
     * migration that needs to do something renameKey()/dropKey() don't cover.
     */
    protected function eachPage(string $template, Closure $callback): void
    {
        Page::where('template', $template)->get()->each($callback);
    }
}
