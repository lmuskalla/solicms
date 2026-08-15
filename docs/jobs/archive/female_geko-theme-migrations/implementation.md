# Implementation: geko theme migrations

id: female
status: open
developer:
date: 2026-08-15

<!-- Produced by @developer after implementation. -->

## Summary

The brief asks three questions about shipping the current geko theme state to
production ("I have made some adjustments (also theme.php) to the geko theme.
I also made all content changes necessary. Do we need any kind of migrations
or do I just export/import? Do I need to deploy? Write down a plan").

**Premise correction (found during rework — this is what makes this runbook
different from the first draft):** the requester's geko theme adjustments are
committed on **`main`**, in commit `0cbcd2b "geko theme changes"` (author
Leander Muskalla, 2026-08-15), **not** on this job branch
(`feature/female_geko-theme-migrations`). This branch shares the merge base
`4d0a105` with main and carries **zero** theme code — only the job-scaffold
and planning docs. `0cbcd2b` reworks `templates/Home.svelte` (multilingual
welcome wall, responsive hero), `templates/Page.svelte` (h2/h3 table of
contents), `components/Header.svelte` (sticky header + mobile hamburger),
`components/NewsCard.svelte`, `style.css`, plus `app.blade.php` (only the
active tenant's theme CSS loads, in dev and prod) and the admin
title-editing change (`Admin\PageController` + `Admin/Pages/Edit.svelte`).
`main` is ahead of the branch by three commits in total: `0cbcd2b`,
`4fc8179` (admin save notifications) and `99b66fb` (wysiwyg editor floating
nav). **Deploying this job branch as-is would ship the OLD geko theme.**
The deploy must run from `main` (or from a branch that has these commits).

The answers to the brief's three questions, verified against the repo:

1. **Do we need any kind of migrations or do I just export/import?**
   Both channels exist and are needed, but for *different things* — and **no
   new theme migration is required**. The two geko theme content migrations
   already committed (`resources/themes/geko/migrations/2026_08_08_010000_drop_news_items_in_favor_of_preview.php`,
   `2026_08_08_020000_split_news_and_termine.php`) cover every rename/drop of
   a content-bearing section key that the current `theme.php` schema implies
   (see "Investigation" below). They run automatically on every deploy via
   `php artisan themes:migrate` (idempotent, tracked per tenant in the
   `theme_migrations` table). *Content* (section values, posts, media, nav
   items, site_config) travels between environments via `tenant:export` →
   `tenant:import` (`.zip` archives); the deploy itself never carries tenant
   content (deploy.yml rsync excludes `/database/tenants` and `/storage`).

2. **Do I need to deploy?** **Yes.** The theme *code* — `theme.php`,
   `templates/*.svelte`, `components/*.svelte`, `style.css`, fonts, and the
   `migrations/` files themselves — reaches production only through the
   deploy pipeline (`make deploy` → `deployment/deploy.yml` → rsync + docker
   build + migrate + restart). Export/import moves content only, never code.
   The deploy also runs `themes:migrate`, so the two geko migrations apply to
   the production geko tenant automatically if they have not already run.

3. **The plan: deploy the code from `main`, then export/import the content.**
   Order matters: deploy **first** (new theme components/style live,
   `themes:migrate` runs), then import the content (destructive full replace
   — take a `backup:run` snapshot first). Importing before deploying would
   briefly expose new-schema content to old theme code, and the admin would
   self-heal sections against the old schema.

This job is the investigation + the written plan (the runbook in
"Runbook (operator steps)" below). The deploy and the export/import are
**production operations that require the requester's confirmation and
production access** — they are documented here as operator steps, not
executed from this sandbox (see "Known issues / follow-ups").

## Investigation

### TASK-1 — Where the geko theme changes actually live

The requester's geko theme adjustments are **not on this job branch**. They
are committed on `main`:

- `git merge-base --is-ancestor 0cbcd2b HEAD` → no: `0cbcd2b` is not reachable
  from `feature/female_geko-theme-migrations` (branch base is `4d0a105`).
- `git diff HEAD main --stat -- resources/themes/geko/` shows the full
  delta the requester made: `Header.svelte`, `NewsCard.svelte`, `style.css`,
  `templates/Home.svelte`, `templates/Page.svelte` (227 insertions /
  28 deletions across 5 files). `theme.php`, `templates/Aktuelles.svelte`
  and the `migrations/` files are **identical** on both branches.
- The two follow-up admin commits on main (`4fc8179` save notifications,
  `99b66fb` wysiwyg floating nav) also ship with a deploy from main.

What `0cbcd2b` does (from the commit diff):

| File | Change |
|---|---|
| `resources/themes/geko/templates/Home.svelte` | Multilingual "welcome wall" of tilted greeting badges, responsive hero (text sizes, image aspect, CTA placement), tag pills reworded (Mehrsprachig/Kostenlos/Nachbarschaftlich), responsive Termine/news/value-grid sizing |
| `resources/themes/geko/templates/Page.svelte` | `slugify()` + h2/h3 → `id` rewriting + desktop "Auf dieser Seite" table-of-contents sidebar |
| `resources/themes/geko/components/Header.svelte` | Sticky header with scroll shadow + mobile hamburger menu |
| `resources/themes/geko/components/NewsCard.svelte` | Truncated date badge (no overflow) |
| `resources/themes/geko/style.css` | `overflow-wrap`/`hyphens` for long compounds, `scroll-margin-top` for TOC anchors, tightened list rhythm (`ul/ol/li/li p`) |
| `resources/views/app.blade.php` | Only the active tenant's theme CSS loads (dev and prod) — removes the dvm/tabubruch cascade bleed |
| `app/Http/Controllers/Admin/PageController.php` + `resources/js/Pages/Admin/Pages/Edit.svelte` | Editable page title (new `title` validation rule + inline input) |
| `resources/js/Themes/dvm/Home.svelte` (deleted), `resources/themes/dvm/components/Footer.svelte` | dvm cleanup (unused Home template; contact_email no longer auto-appended in footer) |
| `docs/Datenschutzerklaerung.md`, `docs/Impressum.md` (new) | Legal-page templates (repo content, ship with the deploy) |

**Theme state on this job branch:** `git ls-tree` shows the same geko file
set, but the files are the pre-`0cbcd2b` versions (`7a1dc3f v1` +
`26cd4d7` footer-nav switch). `git status` shows nothing under
`resources/themes/geko/` — there is no uncommitted theme work here either.
All geko files are tracked (`git ls-files resources/themes/geko/` lists
them), so whatever branch is deployed carries them. **Gitignore nuance
confirmed:** `.gitignore` line 29 `resources/themes/*` (with
`!resources/themes/default/`) ignores *new* files under
`resources/themes/geko/` — verified with `git check-ignore` on a test file
under `resources/themes/geko/`. Any future new file (e.g. a new migration)
must be committed with `git add -f`. Not needed for this change — `0cbcd2b`
adds no new geko files.

**Not verifiable from this sandbox:** whether the two geko migrations have
already run against the production geko tenant (`theme_migrations` rows, or
the last deploy date). This determines whether the next deploy's
`themes:migrate` transforms anything or is a no-op. Made an explicit
operator check in the runbook (Step 2b).

### TASK-2 — Is a new theme migration required? (No.)

Re-checked **against the tree that will actually be deployed** (main's
`0cbcd2b` versions of the Svelte components — the first draft cross-checked
the stale branch templates):

| Template | Keys read by the deployed component | Declared in theme.php? |
|---|---|---|
| `home_geko` (Home.svelte) | `hero_text`, `hero_image`, `intro_body`, `news_preview`, `termine` | All five — `news_preview`/`termine` are `posts_ref` sections (no `Section` row to migrate) |
| `wysiwyg` (Page.svelte) | `body` | Yes |
| `aktuelles` (Aktuelles.svelte) | `body`, `news`, `termine` | Yes |

No component reads an undeclared key; `theme.php` is byte-identical on
branch and main. The two committed migrations cover the only historical
renames/drops:
- `010000` drops `home_geko.news_items` in favor of the `news_preview`
  posts_ref (new key; posts_ref sections never have a `Section` row).
- `020000` renames `aktuelles.beitraege` → `news` and moves dated posts into
  a new `termine` section; drops `home_geko`'s own duplicate `termine` posts.

**Conclusion: no new theme migration is required** — nothing to write, and
nothing new to commit under `resources/themes/geko/` (TASK-4: the theme code
lives on `main`, see below).

**Discrepancy to flag to the requester:** the brief says "also theme.php",
but `0cbcd2b` does **not** touch `theme.php` — its only commit in history is
`7a1dc3f v1`, identical on both branches and in this working tree. If the
requester made theme.php edits (a new section key, a reworded label, a
changed type/order), they are **not in the repository** and would not ship
with any deploy. New keys/reworded labels/types/order are all free
(label/type/order resolve live from theme.php; `Admin\PageController::edit()`
self-heals new keys empty) — but only after the edit is actually committed
to the deployed branch. Only a rename/drop of a content-bearing key would
need a migration, and there is no evidence of one in the repo.

## Runbook (operator steps)

Executed by the requester/operator — production access is required from
Step 2 onward (SSH to `solipages`, `~/.ssh/sozialit.pem` per
`deployment/hosts.ini`). The dev/local environment (where the content changes
were made) is needed for the export.

> **Read this first.** The theme code you are shipping is on `main`
> (`0cbcd2b`), not on this job branch. Every command below must run on a
> checkout that contains `0cbcd2b` — `main` itself, or a branch you created
> by merging main into this job branch. Deploying the job branch as-is ships
> the old theme.

### Step 0 — Sanity-check the tree you will deploy

```bash
git checkout main                 # or: merge main into your working branch
git log --oneline -3
# expect 99b66fb wysiwyg editor floating nav / 4fc8179 save notifications /
#         0cbcd2b geko theme changes  — the theme work is present
git show 0cbcd2b --stat | head    # the geko theme changes
git status                        # clean, no uncommitted theme work
```

If you do have uncommitted theme.php (or template/style.css) adjustments on
top of `0cbcd2b`, **commit them now** — the deploy ships the checked-out
working tree, nothing else.

### Step 1 — Commit

Nothing to commit on this job branch: it contains no theme code (TASK-4).
The code to ship is already committed on `main` as `0cbcd2b` (plus
`4fc8179`, `99b66fb`). Do not create a "recommit" of it here.

### Step 2 — Deploy the code to production

```bash
make deploy     # = cd deployment && ansible-playbook -i hosts.ini deploy.yml
```

`deployment/deploy.yml` will, in order: verify `APP_ENV=production` on the
host (hard gate, fails the deploy otherwise), rsync the source tree
(excluding `.git`, `deployment`, `vendor`, `node_modules`,
`database/database.sqlite`, `database/tenants`, `storage`), `docker compose
build` (the Dockerfile's assets stage runs `npm run build`, compiling the
theme Svelte/CSS into the image), `php artisan migrate --force`,
`php artisan tenants:migrate`, `php artisan themes:migrate`, then restart the
containers (brief downtime by design).

**Step 2b — Record what `themes:migrate` reported.** Its output names each
tenant that ran migrations and which identifiers ran. For the geko tenant:
either `geko/2026_08_08_010000_drop_news_items_in_favor_of_preview` +
`geko/2026_08_08_020000_split_news_and_termine` (migrations were pending —
content was transformed) or "Nothing to migrate." (they had already run — the
deploy was a no-op for the theme). This answers the "have they run on prod
already?" question from TASK-1.

Optional explicit check (inside the app container):

```bash
docker compose exec app php artisan tinker --execute="
  \App\Models\Tenant::where('theme', 'geko')->get()->each(function (\$t) {
      tenancy()->initialize(\$t);
      echo \$t->name.': '.implode(', ', DB::table('theme_migrations')->pluck('migration')->all()).PHP_EOL;
      tenancy()->end();
  });
"
```

### Step 3 — Back up production *before* the destructive import

```bash
# on the production host, in /home/solicms:
docker compose exec app php artisan backup:run
```

(spatie/laravel-backup is installed; `config/backup.php` includes the central
DB, `database/tenants/`, and uploaded media.)

### Step 4 — Export the content from the dev/local environment

```bash
php artisan tenant:export
# interactive: pick the geko tenant, save to e.g. /tmp/geko-2026-08-15.zip
# (default target is ./<slug>-<date>.zip in the current dir)
```

### Step 5 — Transfer the archive onto a container-visible path

The import runs inside the app container, and the container mounts **only**
`/home/solicms/database/database.sqlite`, `/home/solicms/database/tenants`
and `/home/solicms/storage` (see `deployment/docker/compose.yml`). The
`tenant:import` archive prompt validates `file_exists()` **inside the
container**, so a zip sitting at `/home/solicms/` (outside every mount) is
invisible to it. Copy it into the storage mount:

```bash
scp -i ~/.ssh/sozialit.pem /tmp/geko-2026-08-15.zip \
    root@solipages:/home/solicms/storage/app/geko-2026-08-15.zip
```

(or copy it into the running container with `docker compose cp
/home/solicms/geko-2026-08-15.zip solicms-app:/var/www/html/storage/app/` —
equivalent result, both land in the same mount).

### Step 6 — Import into the production geko tenant

```bash
# on the production host, in /home/solicms:
docker compose exec app php artisan tenant:import
# interactive: pick the geko tenant, then enter the container-visible path:
#   /var/www/html/storage/app/geko-2026-08-15.zip
# then confirm the destructive prompt ("Replace ALL current content ... This
# cannot be undone.")
```

What import does (from `TenantContentImporter`): deletes the tenant's
existing pages/sections/posts/nav_items/media and recreates them from the
archive with fresh ids; rewrites media references inside values to the new
ids. Users, sessions, permissions, and activity_log are preserved. Post slugs
**regenerate** on import (title + new id), so detail-page URLs
(`/aktuelles/{slug}`) can change.

### Step 7 — Post-import verification checklist

Public site (geko domain):
- Home renders with the new theme: welcome wall of greeting badges, hero
  (`hero_text`/`hero_image`), intro (`intro_body`), News preview
  (`news_preview`, newest 4 of `news`) and Termine (`termine`, up to 10,
  sorted by `starts_at`).
- Aktuelles shows the `news` list and the `termine` list (with dates).
- Wysiwyg pages (Über uns / Kontakt) render `body` through the theme's own
  `Page.svelte`, with the "Auf dieser Seite" TOC on desktop for long pages.
- Sticky header with hamburger on mobile; no CSS bleed from other themes
  (app.blade.php change).

Admin:
- Page editor shows the editable title input and the expected sections
  (labels/types/order match `theme.php`) and a save round-trip works.
- Posts (news + Termine) and their media are intact; navigation (header +
  footer menus) is intact.

Migrations:
- `theme_migrations` in the geko tenant's DB lists both geko migrations
  (see Step 2b).

If anything is off: restore the pre-import snapshot
(`php artisan backup:restore` — see the spatie/laravel-backup docs for the
interactive restore flow) and re-check Step 2 before re-importing.

## Changes

TASK-1: Investigation, read-only. **Reworked after the review verdict:**
  confirmed the requester's geko theme adjustments are committed on `main`
  (`0cbcd2b "geko theme changes"` + follow-ups `4fc8179`, `99b66fb`), NOT on
  this job branch (branch base `4d0a105`; `0cbcd2b` is not an ancestor of
  HEAD; the branch has zero theme code). Documented what `0cbcd2b` changes
  file-by-file. Confirmed `theme.php`, `Aktuelles.svelte` and the two geko
  migrations are identical on branch and main; all geko files are tracked;
  the `.gitignore` `resources/themes/*` rule would ignore any *new* geko
  file (needs `git add -f`). Production `theme_migrations` state is not
  reachable from this sandbox — deferred to runbook Step 2b. No files
  changed.

TASK-2: Verification, read-only. Re-ran the section-key cross-check against
  the **deployable** tree (main's `0cbcd2b` component versions): every key
  read by Home/Page/Aktuelles is declared in `theme.php`; **no new migration
  required** — the two committed geko migrations cover the only historical
  renames/drops. Flagged the brief's "also theme.php" as a discrepancy: no
  theme.php change exists in any commit or the working tree. No files
  changed.

TASK-3: Reworked this plan/runbook document
  (`docs/jobs/female_geko-theme-migrations/implementation.md`) — corrects
  the first draft's wrong premise (theme state "fully committed on this
  branch"), directs the deploy at `main`, fixes the archive-transfer step
  (scp into the compose-mounted `/home/solicms/storage/app/` or
  `docker compose cp`, because the container only mounts
  database/tenants/storage and `tenant:import` validates the path inside
  the container), and keeps the verified mechanics (deploy → record
  `themes:migrate` → `backup:run` → `tenant:export` → transfer → import →
  verify).

TASK-4: Commit the geko theme changes — nothing to commit on this branch:
  the theme code ships on `main` (`0cbcd2b`), which is already committed
  there. The gitignore nuance was re-verified and is documented for future
  new files.

TASK-5 (deploy), TASK-6 (export), TASK-7 (import), TASK-8 (verify): **not
  executed from this sandbox** — production operations requiring the
  requester's go-ahead and SSH access to `solipages`. Fully specified as
  runbook Steps 2–7 above.

## Known issues / follow-ups

- **The deploy + export/import (TASK-5–8) are not executed — they are
  production operations.** The brief asks for a written plan, and the
  analyst's open questions require confirmation before anything touches
  production. The operator runbook (Steps 2–7) is the executable form of
  those tasks; run it once the requester confirms.
- **The theme changes must be deployed from `main`, not from this job
  branch.** This branch carries no theme code; a deploy from it ships the
  old geko theme and omits everything in `0cbcd2b`. Either deploy `main`
  directly or merge main into this branch first (the operator has full git
  access; this sandbox is limited to the job branch).
- **Brief vs. repo discrepancy to resolve with the requester:** the brief
  mentions "adjustments (also theme.php)", but no theme.php change exists in
  the committed history (`0cbcd2b` touches only templates/components/
  style.css/blade/admin) or the working tree. If real theme.php edits
  (especially a renamed/dropped section key) were made but never committed,
  commit them to the deployed branch first and re-check whether a theme
  migration is needed.
- **Open question:** which tenant(s) on production use `theme = 'geko'`, and
  have the two geko migrations already run against them? Resolved by reading
  the `themes:migrate` output on the next deploy (runbook Step 2b).
- **Open question:** the content changes were presumably made in a
  dev/local tenant DB (they travel via export/import). If they were made
  directly in production instead, the export step is moot — only Steps 2 and
  3 apply.
- **Import caveat (by design, not a bug):** post slugs regenerate on import,
  so `/aktuelles/{slug}` URLs may change; archive-exported media references
  are rewritten to the new ids. Neither needs follow-up unless a slug change
  matters to the requester.
- Pre-existing uncommitted workspace changes (`AGENTS.md`,
  `docs/jobs/female_geko-theme-migrations/tasks.md`) were left untouched —
  they are job-scaffold artifacts, not part of this task.
