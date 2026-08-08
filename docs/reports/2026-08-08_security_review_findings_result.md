# Result: Security review findings

Implements `docs/tasks/2026-08-08_security-review-findings.md` in full. Landed before any
feature-improvements/SEO work, per the report's own priority and `docs/tasks/README.md` §1.

## Group 1 — Stored XSS via unsanitized WYSIWYG content

- **TASK-1**: Added `symfony/html-sanitizer` (composer). Allow-list built in the new
  `App\Services\WysiwygSanitizer`, derived from actually reading
  `node_modules/@tiptap/starter-kit/src/starter-kit.ts` (not memory): p, br, strong/b, em/i,
  s/strike/del/u, h1-h6, ul/ol/li, blockquote, code/pre, hr, `a[href,target,rel]`,
  `img[src,alt]`. Link/media schemes restricted to http/https(/mailto for links) — uploads
  always resolve to this app's own absolute `/media/{id}/{filename}` URLs, so no relative or
  `data:` URLs need to be allowed.
- **TASK-2**: `Admin\SectionController::update()` now sanitizes `value` only when
  `$section->type === 'wysiwyg'`.
- **TASK-3**: `Admin\PostController::update()` sanitizes `body` unconditionally (always rich
  text when present).
- **TASK-4**: Added `php artisan content:sanitize` (`--dry-run`, `--tenant=`) — loops tenants
  via `tenancy()->initialize()/end()`, reports every Section/Post whose value would change,
  and only writes when `--dry-run` is omitted (with a confirmation prompt). **Not run against
  real tenant data in this session** — this sandbox's `.env` is a non-functional device file, so
  no artisan command that touches a DB connection could run here. This must be run for real at
  deploy time, after a confirmed backup, per the report's own instruction not to bundle it with
  other work.
- **TASK-5**: Added `dompurify` (npm) and a `resources/js/lib/sanitizeHtml.ts` helper, applied at
  every `{@html}` call site under `resources/themes/` — re-grepped first (found 8 sites across 6
  files, more than the report's illustrative list): `default/Wysiwyg.svelte`,
  `dvm/templates/{Home,Page,Contact}.svelte` (Home has two), `geko/templates/{Page,Aktuelles}.svelte`,
  `tabubruch/templates/Home.svelte`.
- **TASK-6**: `TenantContentImporter::recreate()` now sanitizes `Section.value` (when the
  recreated section resolves to `wysiwyg`) and `Post.body` (always) before saving, so
  `tenant:import` can't reintroduce the hole via a hostile/legacy archive.

## Group 2 — Zip extraction hardening

- **TASK-7**: `TenantContentImporter::extract()` now rejects any zip entry with an absolute path,
  a Windows drive prefix, or `..` path segments, before calling `extractTo()`.
- **TASK-8**: `recreateMedia()` now runs `$item['archive_file']` through `basename()` before
  building a filesystem path from it.

## Group 3 — Dev-login backdoor

- **TASK-9**: `/admin/dev-login` is now only registered inside `routes/tenant.php` when
  `app()->environment('local')` — on top of the existing (already-sound) in-controller
  `abort_unless` check.
- **TASK-10**: No dedicated "deployment checklist" doc exists in this repo (checked
  `deployment/`, `docs/DESIGN.md`) — `deployment/deploy.yml` (the actual Ansible deploy pipeline)
  is the closest equivalent, so I added an automated task there
  (`Verify APP_ENV=production before deploying`) that fails the deploy if
  `/home/solicms/.env` doesn't contain `APP_ENV=production`, rather than a manual checklist
  line nobody would read.

## Decisions made without further sign-off (report didn't require a stop-and-ask, but worth
recording)

- Sanitizer library: `symfony/html-sanitizer` over `mews/purifier`/HTMLPurifier — actively
  maintained, no external binary, allow-list API matches this task's shape directly.
- DOMPurify treated as pure defense-in-depth (TASK-5's own framing) — the real fix is TASK-1-3;
  found & fixed 8 call sites, not the report's 4 illustrative ones.

## Not done / explicitly deferred

- **TASK-4 execution**: written but not run against production/any real tenant DB (see above).
  Run `php artisan content:sanitize --dry-run` first, review output, confirm a backup exists
  (spatie/laravel-backup), then re-run without `--dry-run`.
- Everything else in the source report is implemented.
