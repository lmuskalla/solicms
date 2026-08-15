# Tasks: geko theme migrations

id: female
status: open
analyst: architect
date: 2026-08-15

<!-- Produced by @analyst from brief.md. -->

## Context / investigation notes

These are the answers to the brief's three questions, based on reading the
current codebase (not production, which I cannot reach from this sandbox).

**1. "Do we need any kind of migrations or do I just export/import?"**

Both channels exist and are needed, but for different things, and the theme
migrations are *already written*:

- Theme **content** migrations (`resources/themes/geko/migrations/*.php`) are
  for transforming existing tenant content when a template's section *keys*
  change (rename/drop). Two already exist and are wired into the deploy:
  - `2026_08_08_010000_drop_news_items_in_favor_of_preview.php`
  - `2026_08_08_020000_split_news_and_termine.php`
  They run automatically on every deploy via `php artisan themes:migrate`
  (step in `deployment/deploy.yml`, right after `tenants:migrate`), tracked
  per tenant in the `theme_migrations` table — idempotent, safe to run more
  than once.
- **Content** (section values, posts, media, nav items, site_config) moves
  between environments via `tenant:export` → `tenant:import` (`.zip`
  archives). The deploy does NOT carry tenant content: `deploy.yml` rsyncs
  the source tree but excludes `/database/tenants` and `/storage`.

So: if the only question is "do I need a *new* migration" — almost certainly
**no**, unless the `theme.php` changes rename or drop a section key that holds
real content *beyond* what the two existing migrations already cover. Adding a
brand-new key is free (Admin\PageController::edit() self-heals and creates it
empty); re-labelling/re-typing/re-ordering is free (Section resolves
label/type/order live from `theme.php`). Only a rename/removal of a
content-bearing key needs a migration, and that appears to be exactly what the
two committed geko migrations already do.

**2. "Do I need to deploy?"**

**Yes.** The theme *code* — `theme.php`, `templates/*.svelte`,
`components/*.svelte`, `style.css`, fonts, and the `migrations/` files
themselves — reaches production only through the deploy pipeline
(`make deploy` → `deployment/deploy.yml` → rsync + docker build + migrate +
restart). Export/import moves content only, never code. The deploy also runs
`themes:migrate`, so the geko migrations apply to the production tenant
automatically on the next deploy (if not applied already).

**3. So the plan is: deploy the code, then export/import the content.**

Order matters: deploy **first** (new `theme.php`/templates live, migrations
run), then import the content (destructive full replace — take a backup
first). Importing before deploying would briefly expose new-schema content to
old theme code, and the admin would self-heal sections against the old
schema.

Caveats found while reading the importer/exporter:
- `tenant:import` replaces ALL of a tenant's pages/sections/posts/nav/media/
  site_config (users, sessions, permissions, activity_log are preserved).
- Post slugs regenerate on import (title + new id), so detail-page URLs
  (`/aktuelles/{slug}`) can change.
- Media references inside values are rewritten to the new ids; embedded
  media files are carried in the archive.
- Export/import commands are interactive (Prompts), so they run as operator
  steps, not inside the ansible playbook.

## Task breakdown

TASK-1: Confirm the exact working-tree delta of the geko theme (theme.php, templates, components, style.css, migrations) and whether the two existing geko theme migrations have already been applied to the production tenant (check `theme_migrations` on prod, or the last deploy date) — this decides whether the next deploy's `themes:migrate` actually transforms anything or is a no-op.
     files: resources/themes/geko/**, deployment/deploy.yml
     depends: none
     risk: low — read-only investigation, but its answers shape the rest of the plan.

TASK-2: Verify whether a NEW theme migration is required: compare the current `resources/themes/geko/theme.php` template schemas (home_geko, wysiwyg, aktuelles) against the two committed geko migrations to confirm every rename/drop of a content-bearing key is already covered; if a gap exists, write the new migration (and update theme.php in the same change). If no gap, state that explicitly.
     files: resources/themes/geko/theme.php, resources/themes/geko/migrations/ (possibly new file)
     depends: TASK-1
     risk: medium — a missed rename/drop would silently orphan or lose production content; renameKey()/dropKey() conventions from App\Services\ThemeMigrations must be followed.

TASK-3: Write the plan/runbook document — the job's primary deliverable per the brief ("Write down a plan"): answers the three questions, gives the exact ordered steps with commands (commit → `make deploy` → `tenant:export` → transfer zip → `tenant:import` → verify), the backup-before-import step, and the post-import verification checklist.
     files: docs/jobs/female_geko-theme-migrations/implementation.md
     depends: TASK-1, TASK-2
     risk: low — documentation; the runbook must be accurate because it drives production actions.

TASK-4: Commit the geko theme changes on the job branch — note that `.gitignore` ignores `resources/themes/*` except `resources/themes/default/`, so any *new* files under `resources/themes/geko/` (e.g. a new migration) need `git add -f`; confirm pre-existing geko files are tracked before assuming they travel with the repo.
     files: resources/themes/geko/**
     depends: TASK-1, TASK-2
     risk: low — commit only; the gitignore nuance is the only gotcha.

TASK-5: Deploy the theme code to production via `make deploy` (ansible-playbook deploy.yml) — ships theme.php/templates/style.css/migrations, rebuilds the image (`npm run build` compiles the theme), runs central `migrate`, `tenants:migrate`, and `themes:migrate` (applies the geko migrations to the production tenant if pending), restarts the containers.
     files: deployment/deploy.yml, deployment/docker/Dockerfile (no changes expected)
     depends: TASK-4
     risk: high — production deploy; the playbook's `APP_ENV=production` gate must pass and the container restart causes brief downtime.

TASK-6: Export the current geko tenant content from the dev/local environment (`php artisan tenant:export`) — produces the `.zip` archive that carries the content changes made during the theme work.
     files: none (produces a .zip artifact)
     depends: TASK-3 (runbook only; can run in parallel with TASK-4/TASK-5)
     risk: low — read-only export of the tenant's content.

TASK-7: Import the export archive into the production geko tenant (`php artisan tenant:import` on the production server, interactive) after the deploy — full replace of pages/sections/posts/nav/media/site_config; take a `backup:run` snapshot first so the pre-import state is restorable.
     files: none (CLI operation on the production host)
     depends: TASK-5, TASK-6
     risk: high — destructive full-content replacement on production; the operator must select the correct tenant and confirm the destructive prompt.

TASK-8: Verify the production geko site end-to-end: public pages render with the new theme (Home with news_preview/termine, Aktuelles with news/termine, wysiwyg pages), admin page editor shows the expected sections and saves, posts/media/nav intact, and `theme_migrations` shows the geko migrations recorded.
     files: none (verification only)
     depends: TASK-7
     risk: medium — verification only, but must catch schema/content mismatches before sign-off.

## Open questions for @analyst / requester before implementation

- Which tenant(s) on production are the geko tenant(s), and have the two
  geko migrations already run against them? (Needed to predict whether
  `themes:migrate` transforms anything on the next deploy.)
- Were the "content changes necessary" made in a local/dev tenant DB (so they
  travel via export/import), or directly in production? The plan assumes
  dev → export → production import.
- Should the export/import + deploy be executed as part of this job, or does
  the requester only want the written plan (TASK-3)? The brief says "write
  down a plan", so TASK-4–8 are listed as the execution of that plan —
  confirm before running anything against production.
