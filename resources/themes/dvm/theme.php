<?php

// See config/themes.php's docblock for what this file's shape means and who
// reads it. Merged under config('themes.dvm') by ThemeServiceProvider.

return [
    'label' => 'DVM Bündnis',
    'templates' => [
        'dvm_home' => [
            'label' => 'Startseite',
            'component' => 'templates/Home',
            'sections' => [
                ['key' => 'hero_text', 'label' => 'Überschrift (Hero)', 'type' => 'text'],
                ['key' => 'hero_subtext', 'label' => 'Unterzeile (Hero)', 'type' => 'text'],
                ['key' => 'section_1_body', 'label' => 'Abschnitt 1 — Text', 'type' => 'wysiwyg'],
                ['key' => 'section_1_readmore', 'label' => 'Abschnitt 1 — „Mehr lesen"', 'type' => 'wysiwyg'],
                ['key' => 'section_2_body', 'label' => 'Abschnitt 2 — Text', 'type' => 'wysiwyg'],
                ['key' => 'section_2_readmore', 'label' => 'Abschnitt 2 — „Mehr lesen"', 'type' => 'wysiwyg'],
                ['key' => 'section_3_body', 'label' => 'Abschnitt 3 — Text', 'type' => 'wysiwyg'],
                ['key' => 'section_3_readmore', 'label' => 'Abschnitt 3 — „Mehr lesen"', 'type' => 'wysiwyg'],
            ],
        ],
        'dvm_page' => [
            'label' => 'Unterseite',
            'component' => 'templates/Page',
            'sections' => [
                ['key' => 'body', 'label' => 'Seiteninhalt', 'type' => 'wysiwyg'],
            ],
        ],
        'dvm_contact' => [
            'label' => 'Kontaktseite',
            'component' => 'templates/Contact',
            'sections' => [
                ['key' => 'body', 'label' => 'Seiteninhalt', 'type' => 'wysiwyg'],
            ],
        ],
    ],
];
