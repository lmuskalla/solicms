# Result: SEO findings

Implements `docs/tasks/2026-08-08_seo-findings.md` in full, after the security work and
alongside the feature-improvements work (coordinated per `docs/tasks/README.md` §2/§3).

## Group 1 — Open Graph / Twitter Card tags

- **TASK-1 (decision)**: Title reuses `page.title` + `config.site_name`, matching each
  template's existing convention exactly (some prefix with `page.title —`, home pages just use
  `site_name`). For `og:image`, there is no site-wide logo field to fall back to any more —
  confirmed by grep that `config.logo` doesn't exist anywhere in the current codebase
  (`TenantProvisioner::seedSiteConfig()`'s own comment: logos are hard-defined per theme, not
  tenant config, and themes don't even agree on a logo filename/format). Decision: derive
  `og:image` opportunistically from the first `image`-type section with a value on the current
  page (e.g. a hero image) and **omit the tag entirely** when none exists, rather than inventing
  a new config field or guessing with a theme asset.
- **TASK-2**: Built `resources/js/Components/Frontend/SeoHead.svelte` (title, description,
  canonical, `og:*`, `twitter:*`). Re-grepped `<svelte:head>` across `resources/themes/` first,
  per instruction — found 9 files (more than the report's illustrative 4) and wired all of them:
  `default/{Wysiwyg,HomeStandard}.svelte`, `dvm/templates/{Home,Page,Contact}.svelte`,
  `geko/templates/{Home,Page,Aktuelles}.svelte`, `tabubruch/templates/Home.svelte`. No theme was
  patched individually before the shared component existed.

## Group 2 — Meta description

- **TASK-3 (decision)**: Auto-derived inside `SeoHead.svelte` from the first
  `text`/`textarea`/`wysiwyg` section with content on the current page (HTML stripped, truncated
  to 160 chars), rather than adding a new `Page`/`SiteConfig` column. This was the deliberate
  choice at the coordination point with `feature-improvements` TASK-4/5 (alt text): that one
  needed a real schema change (a section's alt text isn't derivable from anything else), this one
  didn't, so only one new tenant-migration rollout exists for this whole session, not two. A
  caller can still pass an explicit `description` prop to `SeoHead` if a better source is ever
  identified later.
- **TASK-4**: Rendered as `<meta name="description">` and reused for `og:description`/
  `twitter:description`, inside the same `SeoHead.svelte`.

## Group 3 — `<html lang>` wrong for every real tenant

- **TASK-5 (decision)**: Added `tenants.locale` on the **central** `tenants` table (new migration
  `2026_08_08_040000_add_locale_to_tenants_table.php`, `Tenant::getCustomColumns()` updated) —
  option (a) from the report, mirroring how `theme` already works, rather than a per-theme
  default in `config/themes.php`: locale is a property of the client/content, not of which visual
  theme they picked.
- **TASK-6**: `resources/views/app.blade.php` now reads `tenant('locale') ?? app()->getLocale()`
  instead of `app()->getLocale()` alone — `tenant()` safely returns `null` on the central/admin
  domain (verified against `vendor/stancl/tenancy/src/helpers.php`), so the fallback covers that
  case correctly.
- **TASK-7 (backfill)**: Handled by the migration itself — `default('de')` on the new column
  backfills every existing tenant row automatically (SQLite `ALTER TABLE ADD COLUMN DEFAULT`
  applies to existing rows), so no separate one-off command was needed. **Not yet run** against
  the real central DB in this session (same `.env` limitation noted in the other two result
  docs) — run `php artisan migrate` centrally to apply it.

## Group 4 — robots.txt

- **TASK-8**: `public/robots.txt` now disallows `/admin` and `/superadmin`.

## Group 5 — sitemap.xml

- **TASK-9**: Added `GET /sitemap.xml` (registered before the `/{slug}` catch-all) and
  `Frontend\SitemapController`, listing every `published` page. Deliberately minimal per the
  report (no lastmod/priority/changefreq, no per-post URLs).

## Group 6 — Canonical tag for multi-domain tenants

- **TASK-10 (investigation)**: Queried the actual central `database/database.sqlite` directly
  (via a standalone PHP/PDO script, since artisan couldn't run — see above) rather than assuming.
  **Found a real case**: tenant `geko` (Gesundheitskollektiv Bremen) has three domains —
  `geko-bremen.de`, `geko-hb.de`, `geko.localhost` — all registered in one `TenantProvisioner::provision()`
  call. This is not theoretical; TASK-11 was worth doing now.
- **TASK-11**: Defined "primary domain" pragmatically as the tenant's first-registered domain
  (lowest `domains.id`) — no new column/flag, since `TenantProvisioner::provision()` already
  creates domains in the order an operator lists them (real domain first, aliases after), and
  this is trivially promotable to an explicit `is_primary` flag later without a migration if it
  ever needs to be operator-overridable. `Frontend\PageController::canonicalUrl()` rewrites the
  current request's URL onto that domain; used for both `og:url` and `<link rel="canonical">`
  in `SeoHead.svelte`, and reused by `SitemapController` for its `<loc>` URLs.

## Not done / explicitly deferred

- **TASK-7's migration**: not yet run against the real central DB in this session — see above.
- Nothing else from the source report was left out.
