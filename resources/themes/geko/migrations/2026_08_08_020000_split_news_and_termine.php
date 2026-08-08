<?php

use App\Services\ThemeMigrations\ThemeMigration;

/**
 * Aktuelles used to mix News and Termine in one 'beitraege' section, split
 * only by whether a post happened to have `starts_at` set — an unenforced
 * convention, not a real distinction. Splits them into two real sections:
 * 'news' (never has a date — renamed straight from 'beitraege', keeping
 * every post without one) and 'termine' (always does — created fresh, then
 * populated by moving every dated post out of 'news' and into it).
 *
 * home_geko's own separate 'termine' posts were exact duplicates of these
 * same events, kept in sync by hand — dropped in favor of referencing this
 * 'termine' via posts_ref, the same fix 'news_preview' already got.
 */
return new class extends ThemeMigration
{
    public function up(): void
    {
        $this->renameKey('aktuelles', 'beitraege', 'news');

        $this->eachPage('aktuelles', function ($page) {
            $news = $page->sections()->where('key', 'news')->first();

            if (! $news) {
                return;
            }

            $termine = $page->sections()->firstOrCreate(['key' => 'termine'], ['value' => '']);

            $news->posts()->whereNotNull('starts_at')->get()->each(
                fn ($post) => $post->update(['section_id' => $termine->id]),
            );
        });

        $this->dropKey('home_geko', 'termine');
    }

    public function down(): void
    {
        $this->eachPage('aktuelles', function ($page) {
            $news = $page->sections()->where('key', 'news')->first();
            $termine = $page->sections()->where('key', 'termine')->first();

            if ($news && $termine) {
                $termine->posts()->get()->each(
                    fn ($post) => $post->update(['section_id' => $news->id]),
                );
            }

            $termine?->delete();
        });

        $this->renameKey('aktuelles', 'news', 'beitraege');

        // Can only recreate it empty — see ThemeMigration::dropKey()'s doc
        // comment.
        $this->eachPage('home_geko', function ($page) {
            $page->sections()->firstOrCreate(['key' => 'termine'], ['value' => '']);
        });
    }
};
