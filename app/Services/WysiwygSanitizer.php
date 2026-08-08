<?php

namespace App\Services;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

/**
 * Sanitizes rich-text HTML before it's persisted, so previously-trusted
 * markup (a compromised/malicious editor account, or a crafted paste) can
 * never end up stored and later rendered verbatim through a theme
 * template's `{@html}` — see docs/tasks/2026-08-08_security-review-findings.md
 * Group 1.
 *
 * The allow-list below is derived from Tiptap's actually-configured
 * extensions (see resources/js/Components/Admin/TiptapEditor.svelte's
 * `onMount`): `@tiptap/starter-kit` (whose real node/mark set was verified
 * against node_modules/@tiptap/starter-kit/src/starter-kit.ts, not assumed
 * from memory — it registers paragraph, bold, italic, strike, underline,
 * heading levels 1-6, bullet/ordered lists + list items, blockquote, code,
 * code block, horizontal rule, hard break and link) plus the separately
 * configured `Image` extension, and the plain `<a>` markup
 * TiptapEditor.svelte's insertUploaded() writes for non-image file
 * attachments (already covered by the link allow-list below). Nothing else
 * Tiptap can produce is allowed through.
 */
class WysiwygSanitizer
{
    private static ?HtmlSanitizerInterface $sanitizer = null;

    public static function clean(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        return self::sanitizer()->sanitize($html);
    }

    private static function sanitizer(): HtmlSanitizerInterface
    {
        return self::$sanitizer ??= new HtmlSanitizer(self::config());
    }

    private static function config(): HtmlSanitizerConfig
    {
        return (new HtmlSanitizerConfig)
            ->allowElement('p')
            ->allowElement('br')
            ->allowElement('strong')
            ->allowElement('b')
            ->allowElement('em')
            ->allowElement('i')
            ->allowElement('s')
            ->allowElement('strike')
            ->allowElement('del')
            ->allowElement('u')
            ->allowElement('h1')
            ->allowElement('h2')
            ->allowElement('h3')
            ->allowElement('h4')
            ->allowElement('h5')
            ->allowElement('h6')
            ->allowElement('ul')
            ->allowElement('ol')
            ->allowElement('li')
            ->allowElement('blockquote')
            ->allowElement('code')
            ->allowElement('pre')
            ->allowElement('hr')
            ->allowElement('a', ['href', 'target', 'rel'])
            ->allowElement('img', ['src', 'alt'])
            // Uploaded files/images are always served from this app's own
            // /media/{id}/{filename} route (see MediaController::store),
            // which route() always renders as an absolute http(s) URL — no
            // relative or data: URLs need to be allowed for either.
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowMediaSchemes(['http', 'https']);
    }
}
