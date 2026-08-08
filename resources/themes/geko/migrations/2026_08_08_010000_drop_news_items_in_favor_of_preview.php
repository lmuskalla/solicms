<?php

use App\Services\ThemeMigrations\ThemeMigration;

/**
 * home_geko used to maintain its own separate 'news_items' posts list,
 * duplicating whatever the editor already wrote under the Aktuelles page's
 * 'beitraege' — the exact problem 'posts_ref' exists to avoid. Drops it in
 * favor of 'news_preview', which reads the newest 4 of 'beitraege' instead.
 */
return new class extends ThemeMigration
{
    public function up(): void
    {
        $this->dropKey('home_geko', 'news_items');
    }

    public function down(): void
    {
        // Can only recreate it empty — see ThemeMigration::dropKey()'s doc
        // comment. 'news_preview' has no row of its own to remove; it's
        // never provisioned in the first place (posts_ref sections aren't).
        $this->eachPage('home_geko', function ($page) {
            $page->sections()->firstOrCreate(['key' => 'news_items'], ['value' => '']);
        });
    }
};
