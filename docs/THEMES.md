# Tenant Theme Guidelines

How to build a `resources/themes/<slug>/` theme for a tenant's public site. This
codifies a best practice going forward — most of it does **not** describe how
`dvm` was originally built. `dvm` was a 1:1 port of a real WordPress theme
done as a stress test of the loading mechanism (see `HANDOFF.md`); its
`style.css` still uses patterns (bare selectors, no scoping root, no design
tokens) this doc actively recommends against — that's a separate, non-blocking
follow-up. Its *file layout*, however, has been brought in line with §1 below,
same as every other theme.

`default` (`resources/themes/default/`) is the plain generic theme new
tenants get when nobody has built them anything bespoke yet — it stays
intentionally minimal, still flat (no `templates/`/`components/` split), and
isn't a style reference either. Give it the same layout the day it needs a
second template or its own asset.

---

## 1. What a theme is

A theme's folder is organized by role, not left flat:

```
resources/themes/<slug>/
├── theme.php               — see below; registers the theme
├── migrations/              — see §2; only exists once a schema change
│   └── ...                   needs one
├── style.css              — see §4, kept small
├── templates/              — one component per pages.template value,
│                             registered in this theme's theme.php
│   ├── Home.svelte
│   ├── Page.svelte
│   └── ...
├── components/              — shared pieces templates compose (Header,
│   ├── Header.svelte         Footer, card/row components, etc.) — never
│   ├── Footer.svelte         registered in theme.php directly
│   └── ...
└── assets/
    ├── fonts/               — referenced from style.css's @font-face
    └── images/              — logos etc., imported into .svelte files
```

- `theme.php` — registers the theme: a `label`, and a `templates` map from
  `pages.template` values to `{ label, component, sections }`. `component`
  is a path relative to the theme's own folder, so it's written
  `'templates/Home'`, not just `'Home'`. `App\Providers\ThemeServiceProvider`
  merges every theme's `theme.php` into `config('themes')` at boot — the rest
  of the app still just reads `config("themes.{$slug}...")`, same as before
  this was split per-theme; there's no reason to ever add anything back to
  `config/themes.php` itself, which stays an empty placeholder.

**A template name is a contract, not a label.** It must mean exactly one
rendering *and* exactly one set of editable sections — never "usually these
fields, but this particular page also happens to have an extra one." If two
pages need different sections, they need different template names, full
stop, even if their components would otherwise be nearly identical. The
`sections` array in each template's `theme.php` entry is the enforced
schema: `App\Services\TenantProvisioner` (initial tenant seeding) and
`Admin\PageController::store()`/`update()` (an editor creating a page or
switching its template) both provision from that one array — neither
hardcodes its own guess. A theme's Svelte component may only read a section
key that's declared in its template's `sections` — if you add a key to a
`.svelte` file, add it to `theme.php` in the same change.

A section's `label` and `type` are **never stored** — `App\Models\Section`
resolves both live from `theme.php` by `key` every time, and `order` is just
that key's position in the `sections` array. Reword a label, fix a typo,
change a type, reorder the array: edit `theme.php`, done, every tenant using
that template picks it up on the next page load. Nothing to run, nothing to
sync. The only thing that isn't free is changing a `key` itself once real
tenants have content under it — see §2.

**Assets live under `resources/themes/<slug>/assets/`, not `public/`.**
`public/` is served as-is by the web server; `resources/` is not — anything
under `assets/` must go through a real Vite reference so it gets resolved
(and fingerprinted at build time) rather than a plain URL string that
happens to work only because a file sits at that path today:

- From a `.svelte` file: `import logoUrl from '../assets/images/logo.svg';`
  then `<img src={logoUrl}>` — never `<img src="/images/<slug>/logo.svg">`.
- From `style.css`: `url('./assets/fonts/Inter-Variable.ttf')` (relative —
  `style.css` sits next to `assets/`, one level above `templates/`/`components/`)
  — never an absolute `/fonts/<slug>/...` path.

Never hotlink a client's original site or a third-party CDN either way —
download and commit what's actually used.

**Favicon.** Every theme should ship a square, light-background-safe version
of its mark at `assets/images/favicon.<ext>` (`svg`, `png` and `ico` all
work). It is picked up automatically: the platform serves it at
`/favicon/<slug>` (resolved by `App\Services\ThemeFavicon`) and the shared
root view links it on every tenant page — nothing to register, nothing to
bust, the URL is cache-immutable by design. The resolver falls back to
`assets/images/icon.<ext>`, then `assets/images/logo.<ext>`, so a theme
without a dedicated favicon still gets its closest existing mark — but
don't rely on that for the themes you ship: wordmarks and white-filled
icons designed for a dark header/footer read as smears at 16–32px on a
light browser tab. A theme with no usable mark at all (like `default`)
gets the platform default favicon instead.

Nothing else needs touching to add a theme. `Pages/Frontend/Page.svelte`
resolves `<theme>/<component>` and `<theme>/style.css` via
`import.meta.glob`, generically, for every theme that will ever exist.

## 2. Changing a template's schema once tenants are using it

Adding a brand-new key to a template's `sections` array is free — the next
time an editor opens a page using that template, `Admin\PageController::edit()`
self-heals and creates it (empty). Renaming or removing a key that already
holds real content is not: nothing reconciles an existing page's `Section`
rows with `theme.php` automatically, on purpose — a silent rename would
either orphan an editor's already-written text/media under the old key, or
(worse) an automatic key-similarity guess could attach it to the wrong field.
That step has to be explicit, which is what `resources/themes/<slug>/migrations/`
is for — the same idea as a database migration, scoped to template content
instead of table structure.

A migration is a file like
`resources/themes/dvm/migrations/2026_08_08_000000_rename_intro_to_section_1.php`,
returning an anonymous class extending `App\Services\ThemeMigrations\ThemeMigration`:

```php
<?php

use App\Services\ThemeMigrations\ThemeMigration;

return new class extends ThemeMigration
{
    public function up(): void
    {
        $this->renameKey('dvm_home', 'intro_body', 'section_1_body');
        $this->dropKey('dvm_home', 'hero_image'); // never used by this template's current component
    }

    public function down(): void
    {
        $this->renameKey('dvm_home', 'section_1_body', 'intro_body');
        // dropKey() has no real inverse — down() can only recreate an empty
        // row, not un-delete whatever value/media hero_image used to hold.
    }
};
```

`renameKey()` preserves the row's `value` and media — that's the entire
point of using it over deleting-and-recreating. `dropKey()` is for a key
that's genuinely gone for good; its `down()` can only bring the field back
empty. Update `theme.php` itself in the *same* change — the migration moves
existing tenants' data to match the new schema, it doesn't replace editing
the schema.

Run pending migrations for every tenant with `php artisan themes:migrate`
(idempotent, no prompts — wired into `deployment/deploy.yml` right after
`tenants:migrate`, so a normal deploy picks up whatever migrations shipped
with it). To undo the most recent batch for one tenant, `php artisan
themes:rollback` (interactive — picks the tenant, confirms first).
`App\Services\ThemeMigrator` tracks what's already run per tenant in that
tenant's own `theme_migrations` table, the same way Laravel tracks its own
`migrations` table.

## 3. Data contract

Every template component receives the same props, sent by
`Frontend\PageController`:

```ts
interface Props {
    page: { title: string; template: string };            // Home may skip this
    sections: Record<string, { value: string }>;           // keyed by Section.key
    config: Record<string, string>;                        // SiteConfig::allAsMap()
    nav: Array<{ label: string; href: string }>;            // editor-built menu
}
```

This is `ThemeProps`, defined once in `resources/js/types.ts` — shared
across every theme, since it's identical for all of them, not owned by any
one theme's folder. Import it rather than redeclaring the interface in
every component:

```ts
import type { ThemeProps } from '../../js/types';

let { page, sections, config, nav }: ThemeProps = $props();
```

Section keys and `site_config` keys are whatever `TenantProvisioner` seeded
plus whatever an editor added by hand afterward (see `HANDOFF.md` — there's
no "add page" UI yet, new pages/sections are provisioned once via
tinker/artisan and then edited normally). Design the template around the
keys the content actually needs; don't invent generic ones you don't use.

## 4. Styling: Tailwind first, `style.css` for three things only

`resources/css/app.css` already does this:

```css
@source '../themes/**/*.svelte';
```

Tailwind scans every theme's `.svelte` files and compiles their utility
classes into the one global `app.css`, already loaded on every request —
admin, every tenant, all of them. This is safe by construction: an unused
utility class costs a few bytes and does nothing unless that exact class is
on an element in the DOM. There is no bleed risk the way there is with bare
element selectors (`button {}`, `h2 {}`), which style *any* matching element
regardless of which theme "owns" the page.

**So: write markup with Tailwind utility classes directly, the same way the
rest of the app does.** Don't write a parallel hand-rolled CSS file that
reimplements spacing/typography utilities Tailwind already gives you — that
was the DVM approach and it produced 650 lines of bespoke CSS for things
`px-6 py-24 text-4xl font-semibold` already do.

`style.css` (still lazy-loaded per-theme — see §4) is for the handful of
things Tailwind classes genuinely can't express:

1. **`@font-face`** declarations for the theme's own fonts.
2. **Design tokens as CSS custom properties**, scoped to one root class
   unique to the theme — e.g. `.theme-geko { --brand: #884AFF; }` — never on
   `:root` or bare elements. Wrap the theme's top-level markup in that class:
   `<div class="theme-geko">`. This means even if the lazy-load/never-coexist
   assumption were ever violated, the tokens stay contained. Reference the
   tokens from Tailwind arbitrary values (`bg-[var(--brand)]`) or plain
   `style="color: var(--brand)"` where a utility class can't reach a
   brand-specific value.
3. **Rich-text (`{@html}`) overrides.** Section values of type `wysiwyg` are
   stored as HTML and rendered with `{@html}` — Tailwind classes can't reach
   into that markup. Use the `@tailwindcss/typography` plugin's `prose`
   class as the base, scoped to one wrapper (e.g. `.rich-text`), and add
   only the specific overrides the brand needs on top — not a full
   from-scratch `p`/`h2`/`ul` ruleset per theme.

Keep animation subtle regardless of brand — short fades/slides, nothing
bouncing or spinning — this matches the platform-wide motion guidance in
`DESIGN.md`, which is about the *admin/marketing* side but the restraint
applies equally to tenant sites: the organization's content is the point,
not the site chrome.

## 5. Why lazy-loading still matters even though Tailwind is global

`Pages/Frontend/Page.svelte` loads each theme's component code and
`style.css` via `import.meta.glob(..., )` (not `{ eager: true }`) keyed by
the resolved `themeComponent` path. This has nothing to do with CSS bleed
(§3 covers that) — it's about bundle size: a tenant visiting a
`geko`-themed site should never download `dvm`'s component code or fonts.
Keep new themes lazy the same way; don't refactor this back to eager glob
imports (see `HANDOFF.md` for the two real bugs that caused).

## 6. Accessibility baseline (non-negotiable, every theme)

- Semantic landmarks: `<header>`, `<nav>`, `<main>`, `<footer>`.
- A skip-to-content link as the first focusable element.
- Don't strip focus outlines; style `:focus-visible` instead of removing it.
- Check the theme's actual text/background pairs against WCAG AA — brand
  colors (e.g. a saturated accent as body text) often fail contrast even
  when they look fine as an accent chip.
- If a page's real content is in a language other than the tenant's
  default, mark it — e.g. `<span lang="ar">...</span>` for embedded
  multilingual snippets. Relevant for tenants whose audience is explicitly
  multilingual (GEKO's welcome strip is German + Arabic + Turkish + Farsi +
  Russian).

## 7. TypeScript conventions

- `<script lang="ts">` everywhere, real prop interfaces (see §2), no `any`.
- After any frontend change: `docker compose exec vite npx svelte-check
  --tsconfig ./tsconfig.json` — must stay at 0 errors.

## 8. Repeatable content: 'posts' and 'events' sections

Some content isn't a single value — a "News" grid, an "Upcoming events" list.
That's what a section of type `'posts'` or `'events'` is for: instead of a
`value`, its content is its `posts` relation (`App\Models\Post`), a
repeatable, editor-managed list. The editor manages it from the same
page-edit screen as every other section — a "Verwalten" button opens a
popup — not a separate top-level admin area. This is deliberate: the
platform's editing model is "go to the page you think the content lives
on," not a set of content-type concepts the editor has to learn (see the
GEKO build's actual back-and-forth on this — a dedicated Events/News admin
section was considered and rejected for exactly this reason).

A `Post` has no `type` column of its own, but its owning *section's* type
does draw one real line: a `'posts'` section's entries never have
`starts_at` — no date field in the editor at all, enforced in
`Admin\PostController`, not just by which fields `PostEditor.svelte`
happens to show — while an `'events'` section's entries always do
(required, no checkbox either). Everything else about a post still follows
from which fields are filled in, freely combinable regardless of section
type:

- `image` set → renders as a photo card.
- `body` set → gets a real detail page at `/aktuelles/{slug}`, rendered
  through the theme's own generic `wysiwyg` template component — a post
  with a body is structurally the same thing as any other content page, so
  it doesn't get a second rendering pipeline.
- `auto_delete` set (`'events'` only) → this Termin gets removed once it's
  in the past — see `App\Jobs\PruneExpiredPosts`, scheduled daily.

A theme's job is just to read whichever section(s) its template declares
(by a fixed key, e.g. `sections.news_preview`, `sections.termine`) and
decide how to lay out `.posts` — filter for `image` for a card grid, sort
by `starts_at` for a dated list, as `geko/Home.svelte` and
`geko/Aktuelles.svelte` do. **Read sections by fixed key, never by scanning
`Object.values(sections)` for `type === 'posts'`** — a generic scan is
exactly how a template ends up rendering differently per page for no
declared reason (this happened once during the GEKO build: a shared
`Page.svelte` scanned for any posts-type section, so two pages using the
literal same template value rendered different things depending on which
sections a developer happened to create by hand — fixed by splitting it
into `Page.svelte`, plain wysiwyg only, and `Aktuelles.svelte`, which reads
its own fixed `news`/`termine` keys). The `NewsCard`/`EventRow` split in the
geko theme is one reasonable rendering of `.posts`, not a required shape —
a different theme can lay the same data out completely differently, as
long as it's still driven by fixed, `theme.php`-declared keys.

## 9. Showing the same posts on a second page: 'posts_ref'

Two templates both wanting a "News" list is not two lists to manage — a
post's real home is exactly one `'posts'`/`'events'` section; anything else
that wants the same content declares a `'posts_ref'` section instead, e.g.
`home_geko` showing a preview of `aktuelles`'s `news`:

```php
['key' => 'news_preview', 'label' => 'News (Vorschau)', 'type' => 'posts_ref', 'source' => 'news', 'limit' => 4],
```

`source` is the *other* section's `key` — it must be unique tenant-wide, not
just on its own page, since `Frontend\PageController::withPostsRefs()`
resolves it with an unscoped `Section::where('key', $source)`. This is
already true for any key one single template declares (like `news`, only
ever on the `aktuelles` template) — don't reuse a key several templates
declare (like `body`) as a `source`, it'd resolve to whichever page happens
to be found first.

`source`/`limit` are dev-set in `theme.php`, not editor-configurable — the
same "developer hardcodes almost everything besides the content" trade-off
`theme.php` already makes everywhere else. A `posts_ref` section never gets
a `Section` row of its own (`Admin\PageController::provisionSections()`
skips it) — there's nothing *for it* to provision — but it still shows up
in the page editor as a fully editable card, pointed straight at the real
source section's id and its full posts list, with a hint that this page
only shows the newest `limit` of them (`Admin\PageController::postsRefCard()`).
Editing it from either page's editor writes through to the exact same rows
— it's one "News", edited once, not a read-only preview an editor has to
know to go elsewhere for. The public frontend is where `limit` actually
bites: `Frontend\PageController::withPostsRefs()` resolves `.posts` fresh
on every render, in the source section's existing (editor-controlled)
order, capped to `limit`, only for what's shown on that page's public URL.
Renaming or dropping a `source` that already has real posts is the same
kind of change as renaming any other key with real data — see §2 — the
`geko` migration that retired `news_items` in favor of `news_preview` is the
worked example.

## 10. Minimal template set

Two templates cover almost every tenant: a `Home` and a generic content
page (what `default` calls `Wysiwyg`, `HomeStandard`). Add more only when a
tenant's real content needs a genuinely different layout (a contact page
with a form, an events list) — don't pre-build templates speculatively.
