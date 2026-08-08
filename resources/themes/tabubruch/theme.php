<?php

// See config/themes.php's docblock for what this file's shape means and who
// reads it. Merged under config('themes.tabubruch') by ThemeServiceProvider.

return [
    'label' => 'Tabubruch Beratung',

    // A single scrolling one-page site — the whole site is one Page whose
    // sections *are* the page's content blocks, in the fixed order below.
    // No NavItem-driven navigation exists (there's nothing else to link
    // to), hence 'uses_page_nav' => false — see HandleInertiaRequests and
    // Components/Admin/Layout.svelte.
    'uses_page_nav' => false,
    'templates' => [
        'home_tabubruch' => [
            'label' => 'Startseite',
            'component' => 'templates/Home',
            'sections' => [
                ['key' => 'tagline', 'label' => 'Untertitel (Hero)', 'type' => 'text'],
                ['key' => 'ueber_mich', 'label' => 'Über mich', 'type' => 'wysiwyg'],
                ['key' => 'ueber_mich_portrait', 'label' => 'Porträtfoto', 'type' => 'image'],
                ['key' => 'konfliktmoderation', 'label' => 'Konfliktmoderation', 'type' => 'wysiwyg'],
                ['key' => 'diskriminierungssensibles_coaching', 'label' => 'Diskriminierungssensibles Coaching', 'type' => 'wysiwyg'],
                ['key' => 'rassismuskritische_beratung', 'label' => 'Rassismuskritische Beratung', 'type' => 'wysiwyg'],
                ['key' => 'fragen_und_antworten', 'label' => 'Fragen und Antworten', 'type' => 'wysiwyg'],
                ['key' => 'impressum', 'label' => 'Impressum', 'type' => 'wysiwyg'],
            ],
        ],
    ],
];
