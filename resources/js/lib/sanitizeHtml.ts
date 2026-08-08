import DOMPurify from 'dompurify';

/**
 * Client-side, defense-in-depth sanitization immediately before every
 * `{@html}` usage of editor-authored rich text in a public theme template.
 *
 * The actual fix is server-side (see App\Services\WysiwygSanitizer, applied
 * in Admin\SectionController::update() / Admin\PostController::update()) —
 * this is a second, independent layer in case previously-persisted content
 * predates that fix, or some other write path is ever added that forgets to
 * sanitize. See docs/tasks/2026-08-08_security-review-findings.md TASK-5.
 */
export function sanitizeHtml(html: string | null | undefined): string {
    if (!html) return '';

    return DOMPurify.sanitize(html);
}
