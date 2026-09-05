<?php

use App\Services\ThemeMigrations\ThemeMigration;

/**
 * Adds home_geko's multilingual welcome strip — the CI calls it out in
 * resources/themes/geko/style.css ("multilingual welcome strip on Home"),
 * so it's brand-mandated, not optional. Home.svelte renders the section as
 * a full-width band above the hero; this seeds every existing home page
 * with the CI's set of greetings (one per line) so the strip shows up
 * without an editor having to type them first. The value stays editable —
 * the client can add or remove languages. firstOrCreate leaves a page
 * alone if it already has the section (e.g. created empty by
 * Admin\PageController's self-healing), respecting any editor changes.
 */
return new class extends ThemeMigration
{
    public function up(): void
    {
        $this->eachPage('home_geko', function ($page) {
            $page->sections()->firstOrCreate(['key' => 'welcome_languages'], [
                'value' => "Herzlich Willkommen\nWelcome!\n¡Bienvenidos!\nSerdecznie witamy!\nДобро пожаловать!\nأهلاً وسهلاً!\nHoş geldiniz!\nخوش آمدید!\nBi xêr hatî!\nBienvenue!\nDobrodošli!\nЛаскаво просимо!",
            ]);
        });
    }

    public function down(): void
    {
        $this->dropKey('home_geko', 'welcome_languages');
    }
};
