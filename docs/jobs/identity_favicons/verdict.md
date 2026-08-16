# Verdict: favicons

id: identity
status: open
reviewer:
date: 2026-08-16

<!-- Produced by @reviewer and/or @security after implementation. -->

## Review

TASK-1: PASS
notes: `app/Services/ThemeFavicon.php` implements exactly the specified convention
chain — `favicon.<ext>` → `icon.<ext>` → `logo.<ext>` → null, each checked across
`svg/png/ico/webp/jpg/jpeg/gif`, confined to `resource_path("themes/{$theme}/assets/images")`.
The slug is validated with `/^[a-z0-9_-]+$/i` before any path interpolation. Convention
documented in `docs/THEMES.md` §1 (new "Favicon." paragraph, lines 89–100) in the right
place (after the assets paragraph, before "Nothing else needs touching to add a theme").

TASK-2: PASS
notes: geko gets a dedicated square, light-background-safe `assets/images/favicon.svg`
(512×512, GEKO-violet #884aff rounded tile with the white wordmark centered); dvm gets
`assets/images/favicon.png` (43 KB); tabubruch keeps its pre-existing `favicon.ico`
(verified as a real multi-entry ICO — the developer's analysis temp files were committed
and then removed in b1af589, leaving the tree clean); `default` intentionally has none.
Both new assets are tracked in git despite the `resources/themes/*` gitignore entry
(confirmed via `git diff main...HEAD`; `git status` is clean), i.e. committed with
`git add -f` as the task required.

TASK-3: PASS
notes: `app/Http/Controllers/Frontend/FaviconController.php` validates `{theme}` with
`array_key_exists($theme, config('themes'))` before any file lookup — and
`config('themes')` is assembled by `ThemeServiceProvider` from actual theme directories
(keys are real directory basenames), so an unknown/malformed slug never reaches a path.
`ThemeFavicon::resolve()` adds a second guard (slug regex) and confines resolution to the
theme's own `assets/images/`. Serves via `response()->file()` with the exact requested
`Cache-Control: public, max-age=31536000, immutable` (the passed header survives Symfony's
`prepare()`, which only sets a private/no-cache default when Cache-Control is absent).
Unknown theme or unresolvable file falls back to the platform default `public/favicon.ico`
(decided per open question 3 — no broken tab icon). Path traversal is closed at three
layers; the only files ever served are whitelisted image extensions inside a theme dir.

TASK-4: PASS
notes: `routes/tenant.php` registers `Route::get('/favicon/{theme}', ...)` immediately
after `/sitemap.xml` and before the `/aktuelles/{slug}` and `/{slug}` catch-alls (line 59),
with a comment explaining the two-segment URL choice. No `/favicon.ico` route was
registered (would be shadowed by the static file under `php artisan serve`).

TASK-5: PASS
notes: `resources/views/app.blade.php` emits `<link rel="icon">` before `@inertiaHead`
(line 34), branching on the first path segment: `admin`/`superadmin` → `/images/brand/mark.png`
— verified this is exactly the sidebar/login brand mark used in `Admin/Layout.svelte:67`,
`Admin/Login.svelte:49`, `Superadmin/Layout.svelte:32`, `Superadmin/Login.svelte:44`;
tenant page → `/favicon/{$theme}` (reuses the existing `$theme` computation); no tenant →
static `/favicon.ico`. First-segment matching is precise, so a page slug like
"administratives" cannot get the admin icon. The central domain never references the
`/favicon/{theme}` route, so `PreventAccessFromCentralDomains` 404s are irrelevant.

TASK-6: PASS
notes: manual verification per the repo's established pattern. Not independently re-runnable
here (no php in the review environment), but the claims are consistent with the code, and
there is corroborating on-disk evidence: four tenant SQLite files in `database/tenants/`
(one per theme: default/geko/dvm/tabubruch), session files, and a built `public/build/`.
Static review confirms the expected outcomes for every branch (theme resolution, headers,
traversal attempts, central-domain fallback, implicit `/favicon.ico`).

## Security

No findings. `{theme}` is validated against config('themes') keys (derived from real
directory names) and additionally against `/^[a-z0-9_-]+$/i`; filenames are fixed
candidates (`favicon|icon|logo` + whitelisted extensions) and resolution is confined to
`resources/themes/<slug>/assets/images/`. Traversal payloads can only fall through to the
platform default. No other assets or files are reachable via the new route.

## Overall

APPROVED

Nothing blocks merge. All six tasks are implemented as specified, the diff is confined to
the planned files (plus the job-scaffolding AGENTS.md/docs commits), commit discipline
matches the `[ID] TASK-N:` format with a separate implementation commit, and the security
requirements (path-traversal guard, immutable cache, content-type correctness) hold under
static review.

Non-blocking observations (already documented by the developer, out of scope per the
brief's answers):
- `public/favicon.ico` is a 0-byte placeholder, so `default`-themed tenants and the
  central domain show no icon in the tab; the fallback response also carries the
  cosmetic `application/x-empty` content type. Pre-existing behaviour, unchanged.
- Immutable caching keyed on the theme slug means a changed favicon file needs a new slug
  or cache expiry to propagate — accepted by the open-question decision.
