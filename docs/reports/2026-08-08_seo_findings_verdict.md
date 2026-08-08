# Verdict: SEO findings

Reviewed against `docs/reports/2026-08-08_seo_findings.md`,
`docs/tasks/2026-08-08_seo-findings.md`, and `docs/reports/2026-08-08_seo_findings_result.md`.

## Group 1 — Open Graph / Twitter Card tags

TASK-1: PASS
notes: Decision-only. Title reuses `page.title` + `config.site_name`. `og:image` derived
opportunistically from the first `image`-type section with a value, omitted entirely if none
exists, rather than inventing a config field. Confirmed via grep that no `config.logo` field
exists anywhere in the codebase, supporting the stated reasoning.

TASK-2: PASS
notes: `resources/js/Components/Frontend/SeoHead.svelte` built, renders title, description,
canonical, `og:*`, `twitter:*`. Re-grepped `<svelte:head>`/`SeoHead` across `resources/themes/`
myself: 9 files, and all 9 now import and use `SeoHead` instead of a standalone
`<svelte:head>` block (`default/{HomeStandard,Wysiwyg}.svelte`,
`dvm/templates/{Home,Page,Contact}.svelte`, `geko/templates/{Home,Page,Aktuelles}.svelte`,
`tabubruch/templates/Home.svelte`). No theme was left with its own independent head block. Matches
the report's explicit instruction to build the shared component first, not patch one theme.

## Group 2 — Meta description

TASK-3: PASS
notes: Decision-only, resolved jointly with the feature-improvements alt-text decision per
`docs/tasks/README.md` §2. Auto-derives description client-side inside `SeoHead.svelte` from the
first `text`/`textarea`/`wysiwyg` section with content (HTML stripped via regex, truncated to 160
chars with an ellipsis), avoiding a second tenant-wide schema migration in the same work session.
Reasonable, and the `SeoHead` component still accepts an explicit `description` prop for future
override. Verified the strip/truncate logic in `SeoHead.svelte` directly — regex-based HTML
stripping is crude but adequate for a fallback meta description (not rendered as HTML anywhere,
so no injection risk from the crude stripping).

TASK-4: PASS
notes: Rendered as `<meta name="description">` and reused for `og:description`/
`twitter:description` inside the same component. Confirmed in `SeoHead.svelte`.

## Group 3 — `<html lang>` wrong for every tenant

TASK-5: PASS
notes: Decision-only. Added `tenants.locale` on the central `tenants` table (option (a) from the
report), mirroring the existing `theme` column pattern. Reasonable — locale is a property of the
tenant/content, not the visual theme.

TASK-6: PASS
notes: `database/migrations/2026_08_08_040000_add_locale_to_tenants_table.php` adds
`locale` (string, default `'de'`) to `tenants`. `app/Models/Tenant.php::getCustomColumns()`
updated to include `locale`. `resources/views/app.blade.php` now reads
`tenant('locale') ?? app()->getLocale()`. Verified `tenant()` returns `null` outside tenant
context per the result doc's claim by reading the actual blade file — the fallback correctly
covers the central/admin domain. This migration has **not been run** against the actual central
`database/database.sqlite` in this repo — verified directly: `PRAGMA table_info(tenants)` shows
no `locale` column exists yet. Same sandbox `.env` limitation as the other two reports' deferred
migrations (confirmed non-functional device-file `.env`).

TASK-7: PASS
notes: Handled via the migration's `default('de')`, which backfills existing rows automatically on
`ALTER TABLE ADD COLUMN` in SQLite — no separate one-off command needed, correctly reasoned. This
still requires TASK-6's migration to actually run before it's real (see above).

## Group 4 — robots.txt

TASK-8: PASS
notes: `public/robots.txt` now has `Disallow: /admin` and `Disallow: /superadmin` (prefix-match
per robots.txt convention, so `/admin/login`, `/admin/forgot-password`, `/superadmin/login` are
all covered). Confirmed directly.

## Group 5 — sitemap.xml

TASK-9: PASS
notes: `GET /sitemap.xml` registered in `routes/tenant.php` before the `/{slug}` catch-all (order
matters here and is correct — verified). `app/Http/Controllers/Frontend/SitemapController.php`
lists every `published` page, minimal XML, no lastmod/priority — matches the report's explicit
"deliberately minimal" framing.

## Group 6 — Canonical tag for multi-domain tenants

TASK-10: PASS
notes: Investigation claim independently verified against the actual `database/database.sqlite`
in this repo (not taken on faith): tenant `geko` (Gesundheitskollektiv Bremen e.V.) genuinely has
three domains registered — `geko-bremen.de`, `geko-hb.de`, `geko.localhost`. This is a real,
live case, not a hypothetical — the result doc's decision to proceed with TASK-11 was justified.

TASK-11: PASS
notes: "Primary domain" defined as the tenant's first-registered domain (lowest `domains.id`), no
new schema. `Frontend\PageController::canonicalUrl()` correctly implements this
(`tenant()->domains()->orderBy('id')->first()`), used for both `og:url` and
`<link rel="canonical">` via `SeoHead`, and reused identically by
`SitemapController::pageUrl()` for `<loc>` URLs — consistent between the two. Reasonable,
low-risk, and explicitly promotable to an explicit flag later without a migration, as noted.

## Out-of-scope check

No evidence of scope creep beyond the 11 tasks. `Frontend\PageController` and `SitemapController`
changes are confined to what TASK-2/4/9/11 required.

## Process note

Same as the other two verdicts: no git commits exist in this repository. Cannot verify from
history that this work landed "after security, alongside feature-improvements" as the result doc
claims — only that the current code state is consistent with that narrative.

## Overall: APPROVED (with one required follow-up)

All 11 tasks are implemented correctly and match both the report and task breakdown. The two
product decisions (auto-derived description instead of a new field, `tenants.locale` central
column) are well-reasoned and explicitly coordinated with the feature-improvements report as
`docs/tasks/README.md` §2/§3 required — this cross-report coordination was actually followed, not
just mentioned.

Required before this is fully live:
- **Run `php artisan migrate`** centrally to apply the `tenants.locale` migration (TASK-6/7).
  Until this runs, `tenant('locale')` will error or fail silently depending on how the column
  read is handled, and every tenant's `<html lang>` remains wrong exactly as before this fix.

Not blocking, but should be fixed:
- Commit per-task on this branch/repo going forward.
