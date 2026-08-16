# Tasks: favicons

id: identity
status: open
analyst: architect
date: 2026-08-16

<!-- Produced by @analyst from brief.md. -->

## Context / investigation notes

Findings from reading the current codebase (branch feature/identity_favicons):

- **No favicon plumbing exists today.** No `<link rel="icon">` anywhere —
  not in `resources/views/app.blade.php` (the shared Inertia root view), not in
  `Components/Frontend/SeoHead.svelte`. Browsers implicitly request `/favicon.ico`,
  which is served as a static file from `public/favicon.ico` (the platform default).
- **`app.blade.php` is the single root view for every request** — tenant public
  pages, tenant admin (`/admin/*`), and central superadmin (`/superadmin/*`). It
  already computes `$theme = tenant('theme') ?? 'default'`. This is the natural
  place to emit the per-context `<link rel="icon">` (it also covers login pages
  and error pages, which `SeoHead.svelte` never mounts on).
- **Theme logos live in `resources/themes/<slug>/assets/images/` with no
  consistent naming**, imported from Svelte components (Vite-fingerprinted, per
  THEMES.md §1):
  - `geko`: `logo.svg` (header wordmark, white-filled text) + `icon.svg`
    (footer icon — also white-filled, designed for the dark footer, so it would
    be nearly invisible on a light browser tab).
  - `dvm`: `logo.png` (wordmark).
  - `tabubruch`: `logo-animation.gif` (hero) + `favicon.ico` — the `.ico` is
    present but referenced nowhere; looks like it was dropped in anticipation of
    exactly this job.
  - `default`: no `assets/` folder at all; header renders `config.site_name`
    text only.
- **The admin sidebar logo is the static `/images/brand/mark.png`**
  (`public/images/brand/mark.png`) — used in `Components/Admin/Layout.svelte`
  line 67, the admin login screen, and the superadmin layout/login. This is the
  image the brief wants as the admin favicon.
- **Serving constraint — a `/favicon.ico` route would be shadowed by the static
  file.** Dev and prod both run `php artisan serve` (deployment Dockerfile CMD;
  the PHP built-in server serves any existing file under `public/` directly
  without invoking Laravel routing). So `Route::get('/favicon.ico')` would never
  fire. The theme favicon URL must not collide with a static file — a two-segment
  route like `/favicon/{theme}` (no such static file exists) works, and the theme
  slug doubles as a natural cache key (safe for `Cache-Control: immutable`).
- **Route ordering:** tenant routes are registered for all domains but rejected
  on central domains by `PreventAccessFromCentralDomains`. The `/{slug}`
  catch-all is registered last in `routes/tenant.php`, so the favicon route must
  be registered before it (same spot as `/sitemap.xml`).
- **`.gitignore` excludes `resources/themes/*` except `resources/themes/default/`**
  — new favicon assets for geko/dvm/tabubruch need `git add -f` to be committed
  (the deploy rsyncs the source tree regardless, so files on disk travel; the
  gitignore only affects version control).
- **Tests:** only `tests/Unit/ExampleTest.php` exists — no feature tests;
  verification by manual check is the established pattern for these jobs.

Design being planned (confirm via open questions below): the blade template
emits `<link rel="icon">` per context — `/images/brand/mark.png` for
admin/superadmin paths, `/favicon/{theme}` for tenant public pages, and the
static `/favicon.ico` for central-domain (no-tenant) requests. A new
`ThemeFavicon` resolver finds the theme's favicon by convention with a fallback
chain; a new `FaviconController` streams the file with the right content type
and long cache. `theme.php` (via `ThemeServiceProvider`) stays the per-theme
registration point if we adopt a declaration instead of a filename convention.

## Task breakdown

TASK-1: Define and implement the theme-favicon resolution as `App\Services\ThemeFavicon::resolve(string $theme): ?string` (returns an absolute path or null): priority `assets/images/favicon.<ext>` → `assets/images/icon.<ext>` → `assets/images/logo.<ext>` → null, resolved only inside `resource_path("themes/{$theme}/assets/images/")`; document the convention in THEMES.md §1 (assets paragraph) so future themes know where to put their favicon.
     files: app/Services/ThemeFavicon.php (new), docs/THEMES.md
     depends: none
     risk: medium — the fallback order decides what actually renders for each theme, and most existing "logo" assets are wordmarks (or white-on-transparent icons) that may be poor favicons; the convention choice ripples into TASK-2/TASK-3.

TASK-2: Audit and, where missing, add a real favicon asset per theme — `tabubruch` already has `assets/images/favicon.ico` (verify it's the correct mark); `geko` needs a square, light-background-safe version of `icon.svg` (its white-filled text is invisible on a light tab; `logo.svg` is a wide wordmark); `dvm` needs one (only a wordmark `logo.png` exists); `default` intentionally falls back to the platform default. New files under `resources/themes/{geko,dvm,tabubruch}/` are gitignored — commit with `git add -f`.
     files: resources/themes/{geko,dvm,tabubruch,default}/assets/images/
     depends: TASK-1 (convention defines the filename/priority)
     risk: low — pure asset work; the only gotchas are the gitignore (`git add -f`) and the visual quality of each favicon at 16–32px.

TASK-3: Create `App\Http\Controllers\Frontend\FaviconController` with `show(string $theme)` — validate `$theme` against `config('themes')`, resolve the file via `ThemeFavicon`, stream it with `response()->file()` (correct Content-Type from the file, `Cache-Control: public, max-age=31536000, immutable`); when nothing resolves, serve the platform default `public/favicon.ico` (or 404 — decide). Guard against path traversal: never interpolate `$theme` or a filename into a path unvalidated.
     files: app/Http/Controllers/Frontend/FaviconController.php (new)
     depends: TASK-1
     risk: medium — the security-relevant task: an unvalidated `{theme}`/filename is a path-traversal/asset-leak vector, and the resolved file must stay inside `resources/themes/<slug>/assets/`.

TASK-4: Register `Route::get('/favicon/{theme}', [FaviconController::class, 'show'])` in `routes/tenant.php` before the `/{slug}` catch-all (next to `/sitemap.xml`). Do NOT register a plain `/favicon.ico` route — the static `public/favicon.ico` shadows it under `php artisan serve`, which is why the URL is `/favicon/{theme}`.
     files: routes/tenant.php
     depends: TASK-3
     risk: low — route ordering is the only gotcha; also note the route exists in the table on central domains but `PreventAccessFromCentralDomains` 404s it there, which the blade template's central-domain fallback (TASK-5) must not rely on.

TASK-5: Emit the favicon `<link>` in `resources/views/app.blade.php`: request path starting with `admin` or `superadmin` → `<link rel="icon" href="/images/brand/mark.png">`; tenant context (public pages) → `<link rel="icon" href="/favicon/{$theme}">`; no tenant (central domain) → static `/favicon.ico`. Add it before `@inertiaHead`, reusing the existing `$theme` computation.
     files: resources/views/app.blade.php
     depends: TASK-4
     risk: low — blade-only; must branch on context correctly so the central domain (no `/favicon/{theme}` route reachable) never gets a broken favicon URL.

TASK-6: Verify end-to-end — provision one tenant per theme (`tenant:setup` with `--theme=default|geko|dvm|tabubruch`) and exercise: browser tab shows the theme favicon on public pages, the brand mark on `/admin/*` and `/superadmin/*`, the platform default on the central domain and on unknown routes; confirm content type/cache headers are correct and no regression where no `<link>` existed before (implicit `/favicon.ico` still works).
     files: none (manual verification; optionally a Pest feature test for the favicon route — currently only tests/Unit exists)
     depends: TASK-2, TASK-5
     risk: low — verification only; the one thing to watch is the central-domain fallback URL.

## Open questions for @analyst / requester before implementation

- **Convention vs declaration for the theme favicon:** a filename convention
  (`assets/images/favicon.<ext>`, tabubruch already fits it) satisfies the
  brief's "automatically" with zero per-theme config, but is implicit; declaring
  a `favicon` key in each `theme.php` is explicit and matches the "theme.php is
  the registration point" philosophy but requires touching every theme and
  contradicts a literal reading of "automatically". Which do we want?
- **Dedicated favicon assets or reuse of existing logos?** geko's `icon.svg`
  and `logo.svg` are white-filled (invisible on light browser tabs), dvm has
  only a wide wordmark, default has nothing. Should the developer produce
  square, contrast-correct favicon files per theme, or is reusing the existing
  logo/icon "as-is" acceptable for now?
- **Platform default:** keep the existing `public/favicon.ico` as the default
  for `default`-themed tenants and the central domain, or should the default
  tenant also get the brand mark?
- **Cache-busting:** with `immutable` caching keyed on `/favicon/{theme}`, a
  changed favicon file under the same theme slug won't be picked up by browsers
  until the cache expires. Acceptable, or should the blade add a version query
  param?
