# Tasks: SEO findings

Source: `docs/reports/2026-08-08_seo_findings.md`
Verdict: REVISIT. Report priority: fix items 1–3 before the next client onboarding (they affect
every tenant live today); item 4 is a quick companion fix; items 5–6 can wait for real signal.

Verified against current code: every theme template owns its own `<svelte:head>` block with
only a `<title>` (confirmed in `resources/themes/default/Wysiwyg.svelte:15-17` — no `og:*`,
`twitter:*`, or `<meta name="description">` anywhere in that file, consistent with the report).
`resources/views/app.blade.php:2` renders `lang="{{ str_replace('_', '-', app()->getLocale()) }}"`
— confirmed one shared `app()->getLocale()` across all tenants. `public/robots.txt` confirmed
as `User-agent: *` / `Disallow:` (empty — allows everything). `TenantProvisioner` confirmed to
exist and to be the multi-domain provisioning path referenced in item 6; its "primary domain"
handling was not reviewed in depth as part of this breakdown and needs checking as part of
TASK-11.

---

## Group 1 — No Open Graph / Twitter Card tags (fix before next onboarding)

### TASK-1 (needs a decision before starting)
Decide the data source for OG/Twitter tags: title can reuse `page.title` + `config.site_name`
(both already available in every template's props per `Frontend\PageController::render()`), but
`og:image` and `og:description` have no obvious existing source — `config.logo` could serve as a
generic fallback image, but there's no per-page description or image field anywhere in the data
model today. Decide whether a site-wide fallback (logo + a generic description) is sufficient for
launch, or whether new fields are needed (this overlaps with Group 2 below — resolve together).
- Files: none (decision only)
- Dependencies: none
- Risk: n/a — flagging so scope isn't guessed; a wrong call here reshapes TASK-2 and the whole meta-description group.

### TASK-2
Build a shared component (e.g. `Components/Frontend/SeoHead.svelte`) that renders `og:title`,
`og:type`, `og:image`, `og:url`, and `twitter:card`/`twitter:title`/`twitter:image` using the
TASK-1 data source, and wire it into every theme template's existing `<svelte:head>` block.
- Files: new `resources/js/Components/Frontend/SeoHead.svelte`, every file under `resources/themes/*/templates/*.svelte` and `resources/themes/*/*.svelte` that currently has its own `<svelte:head>` (confirmed: `resources/themes/default/Wysiwyg.svelte`; report also names `geko/templates/Page.svelte`, `dvm/templates/Page.svelte`, `dvm/templates/Contact.svelte`, `dvm/templates/Aktuelles.svelte` — full list needs re-grepping for `<svelte:head>` across `resources/themes/` before starting, since each theme/template currently owns this independently)
- Dependencies: TASK-1
- Risk: **medium** — must touch every theme's head block individually (no single shared layout renders `<svelte:head>` today); risk of missing a template and leaving one theme un-fixed.

**Do this before any single-theme patch:** if a specific client request prompts fixing one
theme's `<svelte:head>` directly (e.g. just adding `og:title` to unblock one tenant), that
recreates the exact duplication this component exists to solve, and the next head-tag fix
(description, canonical) repeats the "touch N files" problem again. Build the shared component
first. See `docs/tasks/README.md` §3.

---

## Group 2 — No meta description (fix before next onboarding)

### TASK-3 (needs a decision before starting — likely resolved together with TASK-1)
Decide the source for `<meta name="description">`: options include (a) a new per-page field
(new `Page`/`SiteConfig` column, needs a migration run across every tenant DB), or (b)
auto-deriving from the first N characters of a page's primary text/wysiwyg section value. The
report doesn't prescribe either — flagging as an open product/design decision, not a code task,
since guessing wrong means a schema change has to be undone.
- Files: TBD pending decision (likely `database/migrations/tenant/*`, `app/Models/Page.php`)
- Dependencies: none
- Risk: **medium** — if a new column is chosen, it's a tenant-wide migration (same rollout consideration as the alt-text task in the feature-improvements list) affecting every existing tenant DB.

**Coordinate with:** `2026-08-08_feature-improvements.md` TASK-4/TASK-5 (alt text storage) is
the same shape of decision — a new persisted column on a tenant-scoped model, rolled out via
tenant migration to every existing tenant DB. Resolve both together and reuse one
migration-rollout mechanism. See `docs/tasks/README.md` §2.

### TASK-4
Render `<meta name="description">` (and reuse the same value for `og:description`) using the
TASK-3 data source, ideally inside the same `SeoHead.svelte` component from TASK-2.
- Files: same as TASK-2, plus whatever controller change TASK-3 requires (likely `app/Http/Controllers/Frontend/PageController.php` to pass the value through)
- Dependencies: TASK-2, TASK-3
- Risk: **low** once TASK-3 is decided — mechanical once the data source exists.

---

## Group 3 — `<html lang>` wrong for every real tenant (fix before next onboarding)

### TASK-5 (needs a decision before starting)
Decide how per-tenant locale should be modeled. Confirmed: `resources/views/app.blade.php:2`
uses `app()->getLocale()`, which is one shared, install-wide `.env` value
(`APP_LOCALE=en` per `.env.example`), while every real tenant's content is German. Two viable
approaches exist and the report doesn't pick one: (a) a new `locale` column on the tenant model
(central `tenants` table, mirroring how `Tenant::getCustomColumns()` already exposes `theme`),
or (b) a per-theme default locale in `config/themes.php` (mirroring how `tenant('theme')` is
already read in `app/Models/Section.php`). Don't guess — this affects a central-DB schema choice.
- Files: none (decision only)
- Dependencies: none
- Risk: n/a — wrong choice means redoing TASK-6/7.

### TASK-6
Implement the TASK-5 decision: add the column/config and read it in
`resources/views/app.blade.php` instead of `app()->getLocale()`.
- Files: `resources/views/app.blade.php`, and either a new central migration + `app/Models/Tenant.php` update, or `config/themes.php`, depending on TASK-5
- Dependencies: TASK-5
- Risk: **medium** — if a central migration is chosen, affects the shared `tenants` table (lower blast radius than a tenant-DB migration, but still a schema change on live data); must also handle the fallback for the central/admin domain itself, which isn't a tenant.

### TASK-7
Backfill existing tenant records to the correct locale. Report confirms all current tenants
(`dvm`, `geko`, `tabubruch`, `default`) are German-content sites.
- Files: one-off data fix (Tinker command, seeder, or Artisan command depending on TASK-5's shape)
- Dependencies: TASK-6
- Risk: **low-medium** — straightforward but must cover every existing tenant; missing one leaves that tenant's `lang` attribute silently wrong exactly as today.

---

## Group 4 — robots.txt disallows nothing (quick companion fix)

### TASK-8
Update `public/robots.txt` to disallow `/admin` and `/superadmin` paths, closing the "empty
`Disallow:` = allow all" gap that currently makes `/admin/login`, `/admin/forgot-password`, and
`/superadmin/login` crawlable on every client's public domain.
- Files: `public/robots.txt`
- Dependencies: none
- Risk: **low** — single static file; note it's shared identically across every tenant domain and the admin domain today (confirmed — no per-tenant robots.txt logic exists), which is acceptable per the report since disallowing `/admin` and `/superadmin` is correct for every context this file is served in.

---

## Group 5 — No sitemap.xml (low priority — "worth a line")

### TASK-9
Add a per-tenant `sitemap.xml` route/controller listing published pages (`Page::where('published', true)`).
- Files: new route in `routes/tenant.php`, new `app/Http/Controllers/Frontend/SitemapController.php`
- Dependencies: none
- Risk: **low** — small, self-contained addition; report explicitly marks this as lower priority than Groups 1–4.

---

## Group 6 — No canonical tag for multi-domain tenants (defer per report)

Report recommendation: "can wait for real signal (a client actually running two domains...)."
Included here for completeness, not for immediate action.

### TASK-10
Confirm whether any tenant currently has more than one domain attached (check via the central
`domains` table / `Tenant::domains()`) before prioritizing this at all — the report frames the
underlying capability (`TenantProvisioner::provision()` supports multiple domains per tenant) as
theoretical risk, not a confirmed live issue.
- Files: none (investigation only)
- Dependencies: none
- Risk: n/a — this determines whether TASK-11 is worth scheduling at all right now.

### TASK-11
If TASK-10 finds a live multi-domain tenant: add `<link rel="canonical">` pointing at that
tenant's primary domain. Requires first defining "primary domain," which doesn't appear to be a
modeled concept today (needs checking against `app/Services/TenantProvisioner.php` and the
`Domain`/`Tenant` models — not reviewed in depth here).
- Files: `app/Services/TenantProvisioner.php` and/or `app/Models/Tenant.php` (possible new "primary" flag/column), `resources/js/Components/Frontend/SeoHead.svelte` (if TASK-2 exists by then), `app/Http/Controllers/Frontend/PageController.php`
- Dependencies: TASK-10 (only proceed if it finds a real case), ideally TASK-2 for a shared head component
- Risk: **medium** — "primary domain" is an undefined concept in the current schema; defining it is itself a small design decision, not just a template change.
