<?php

// See config/themes.php's docblock for what this file's shape means and who
// reads it. Merged under config('themes.geko') by ThemeServiceProvider.

return [
    'label' => 'GEKO Bremen',
    'templates' => [
        // 'home_geko' matches seedPages()'s "home_{$template}" convention
        // for --template=geko.
        'home_geko' => [
            'label' => 'Startseite',
            'component' => 'templates/Home',
            'sections' => [
                ['key' => 'hero_text', 'label' => 'Überschrift (Hero)', 'type' => 'text'],
                ['key' => 'hero_image', 'label' => 'Bild (Hero)', 'type' => 'image'],
                ['key' => 'intro_body', 'label' => 'Text „Unser Kollektiv"', 'type' => 'textarea'],
                // Neither of these is real content of its own — both are
                // the Aktuelles page's own 'news'/'termine', so an editor
                // manages each list exactly once. See §9 in THEMES.md
                // before adding a second posts_ref anywhere.
                ['key' => 'news_preview', 'label' => 'News (Vorschau)', 'type' => 'posts_ref', 'source' => 'news', 'limit' => 4],
                ['key' => 'termine', 'label' => 'Aktuelle Termine', 'type' => 'posts_ref', 'source' => 'termine', 'limit' => 10],
            ],
        ],
        // 'wysiwyg' matches the literal template seedPages() hardcodes
        // for every non-home page — defining it here means the seeded
        // Über uns/Kontakt pages render through this theme's own Page
        // component immediately, not the generic default Wysiwyg
        // fallback in Frontend\PageController::themeComponent().
        'wysiwyg' => [
            'label' => 'Textseite',
            'component' => 'templates/Page',
            'sections' => [
                ['key' => 'body', 'label' => 'Seiteninhalt', 'type' => 'wysiwyg'],
            ],
        ],
        // Distinct from 'wysiwyg' on purpose — Aktuelles.svelte reads
        // 'news'/'termine' posts sections that Page.svelte knows nothing
        // about. Same template must mean same rendering; two different
        // renderings get two different template names, not one template
        // that silently behaves differently per page.
        'aktuelles' => [
            'label' => 'Aktuelles-Übersicht',
            'component' => 'templates/Aktuelles',
            'sections' => [
                ['key' => 'body', 'label' => 'Einleitung', 'type' => 'wysiwyg'],
                // 'posts': never has a date — no checkbox, no date field at
                // all in the editor. 'events': always has one — required,
                // no checkbox either. See THEMES.md §8 and Post::isEvent().
                ['key' => 'news', 'label' => 'News', 'type' => 'posts'],
                ['key' => 'termine', 'label' => 'Termine', 'type' => 'events'],
            ],
        ],
    ],
];
