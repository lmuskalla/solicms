# Implementation: favicons

id: identity
status: open
developer:
date: 2026-08-16

<!-- Produced by @developer after implementation. -->

## Summary

Favicon support for tenant public sites and the admin/superadmin UI, per the
brief's open-question answers:

1. **Convention, uniform** — a theme's favicon is resolved by filename
   convention (`assets/images/favicon.<ext>`, falling back to
   `icon.<ext>`, then `logo.<ext>`), never declared in `theme.php`.
2. **Dedicated assets** — new, square, light-background-safe favicon files
   for `geko` and `dvm`; `tabubruch` keeps its existing (verified)
   `favicon.ico`.
3. **Yes** — `public/favicon.ico` stays the platform default for
   `default`-themed tenants and the central domain.
4. **Fine** — `/favicon/{theme}` is cached immutable, keyed on the theme
   slug; no version-query cache-busting.

## Changes

TASK-1: `App\Services\ThemeFavicon::resolve(string $theme): ?string`
(app/Services/ThemeFavicon.php, new) — resolves the favicon inside
`resources/themes/<slug>/assets/images/` by convention
(`favicon.<ext>` → `icon.<ext>` → `logo.<ext>` → null), validating the
theme slug (`/^[a-z0-9_-]+$/i`) before it is ever interpolated into a
path. Convention documented in THEMES.md §1 (new "Favicon." paragraph
after the assets paragraph).

TASK-2: dedicated favicon assets —
- `resources/themes/geko/assets/images/favicon.svg` (new, committed with
  `git add -f`): the existing white footer wordmark (`icon.svg`) centered
  on a GEKO-violet (#884aff) rounded tile — visible on light browser tabs,
  unlike the white-filled mark alone.
- `resources/themes/dvm/assets/images/favicon.png` (new, `git add -f`):
  256px square crop of the logo's non-white content bbox with ~8% padding.
- `resources/themes/tabubruch/assets/images/favicon.ico`: verified — a
  real 3-entry (16/32/48px) 32bpp ICO whose mark colors match the theme's
  multi-accent brand palette; kept as-is, now picked up by the convention.
- `default`: intentionally no asset — falls back to the platform default.

TASK-3: `App\Http\Controllers\Frontend\FaviconController` (new) — `show()`
validates `{theme}` against `config('themes')` (assembled from real theme
directories by ThemeServiceProvider) before any file lookup, resolves via
ThemeFavicon, and streams with `response()->file()` +
`Cache-Control: public, max-age=31536000, immutable`. Unknown theme or no
resolved file → serves the platform default `public/favicon.ico` (no 404,
no broken tab icon). Path traversal is blocked at three layers: config-key
lookup, slug regex, and confinement to the theme's own images dir.

TASK-4: `routes/tenant.php` — `Route::get('/favicon/{theme}', ...)`
registered immediately after `/sitemap.xml`, before the `/{slug}`
catch-all, with a comment explaining why it's two segments (a plain
`/favicon.ico` route would be shadowed by the static file under
`php artisan serve`).

TASK-5: `resources/views/app.blade.php` — per-context `<link rel="icon">`
before `@inertiaHead`, reusing the existing `$theme` computation:
first path segment `admin`/`superadmin` → `/images/brand/mark.png`
(the sidebar/login brand mark); any tenant page → `/favicon/{$theme}`;
no tenant → static `/favicon.ico`. Segment-precise so a page slug like
"administratives" can never get the admin favicon.

TASK-6: end-to-end verification (manual, per the established pattern for
this repo — only tests/Unit exists):
- `ThemeFavicon::resolve()` unit-checked for all four themes + invalid
  slugs (`default`→null, `geko`→favicon.svg, `dvm`→favicon.png,
  `tabubruch`→favicon.ico, `nonexistent`/`../../etc`/`admin`/`''`→null).
- Provisioned one tenant per theme (`tenant:setup` equivalent via
  TenantProvisioner: `{default,geko,dvm,tabubruch}.localhost`) and
  exercised the running app over HTTP:
  - `/favicon/{theme}` returns the right file with correct Content-Type
    (image/svg+xml, image/png, image/vnd.microsoft.icon) and
    `Cache-Control: immutable, max-age=31536000, public`.
  - `default` theme + unknown theme slugs → platform default (200).
  - Traversal attempts (`favicon/..`, `favicon/..%2F..%2F.env`,
    `favicon/..%2F..%2Fapp%2FModels%2FPage.php`) → platform default or
    404; never a file outside the theme dir. Central domain can't reach
    the route (PreventAccessFromCentralDomains → 404).
  - Blade: geko/default public pages link `/favicon/{theme}`, `/admin/*`
    and `/superadmin/*` link `/images/brand/mark.png`, central domain
    (when the blade renders, e.g. superadmin login) links `/favicon.ico`.
  - No regression: implicit static `/favicon.ico` still served (200).

## Known issues / follow-ups

- `public/favicon.ico` is a 0-byte placeholder, so `default`-themed
  tenants and the central domain show no icon in the browser tab. Kept
  deliberately (per the brief's answer to open question 3: keep the
  existing platform default). Generating a real default favicon (e.g. the
  brand mark as .ico) is a sensible follow-up but was out of scope.
- `/favicon/{theme}` responses are immutable-cached, so changing a theme's
  favicon file under the same slug needs a cache-busting strategy
  (accepted — open question 4 answered "fine").
- The `default`-theme fallback serves the 0-byte .ico with
  Content-Type `application/x-empty` (Symfony can't sniff an empty file) —
  cosmetic only, same as the pre-existing implicit `/favicon.ico`.
- Local verification required `composer install` + `npm run build`; test
  tenants live in gitignored `database/` sqlite files (left in place for
  reviewer re-verification).
