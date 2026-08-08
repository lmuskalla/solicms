<?php

// See config/themes.php's docblock for what this file's shape means and who
// reads it. Merged under config('themes.default') by ThemeServiceProvider.

return [
    'label' => 'Standard',
    'templates' => [
        'home_default' => [
            'label' => 'Startseite',
            'component' => 'HomeStandard',
            'sections' => [
                ['key' => 'hero_text', 'label' => 'Überschrift (Hero)', 'type' => 'text'],
                ['key' => 'hero_subtext', 'label' => 'Unterzeile (Hero)', 'type' => 'text'],
                ['key' => 'hero_image', 'label' => 'Bild (Hero)', 'type' => 'image'],
                ['key' => 'intro_title', 'label' => 'Einleitungstitel', 'type' => 'text'],
                ['key' => 'intro_body', 'label' => 'Einleitungstext', 'type' => 'textarea'],
            ],
        ],
        'wysiwyg' => [
            'label' => 'Textseite',
            'component' => 'Wysiwyg',
            'sections' => [
                ['key' => 'body', 'label' => 'Seiteninhalt', 'type' => 'wysiwyg'],
            ],
        ],
    ],
];
