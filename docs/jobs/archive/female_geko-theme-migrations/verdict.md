# Verdict: geko theme migrations

id: female
status: open
reviewer:
date: 2026-08-15

<!-- Produced by @reviewer and/or @security after implementation. -->

## Review

Branch verified: `feature/female_geko-theme-migrations` (matches brief).
Base branch: `main` (no `.manigot/manigot.json` in repo, so `main` per
fallback). `git diff main...HEAD` covers only the job docs; the reviewed
rework is commit `6c5d07b [female] TASK-3: rework geko theme migrations
plan/runbook (changes on main, transfer into storage mount)` (correct
`[ID] TASK-N:` format, implementation.md committed with it).

This is a re-review. The previous verdict (`2756320`) blocked on two items:
(1) the plan's wrong premise — the requester's geko theme work lives on
`main` (`0cbcd2b`), not on this branch, so a deploy from the branch would
ship the old theme; (2) runbook Steps 5–6 copying the export archive to
`/home/solicms/`, which is not mounted into the app container, so
`tenant:import`'s `file_exists()` path check could not see it. Both are
addressed in the rework. I re-verified every claim in the reworked
implementation against the repo:

- **Premise (fixed):** `0cbcd2b "geko theme changes"` (Leander Muskalla)
  is on `main`, not reachable from HEAD (HEAD history ends at branch base
  `4d0a105`); `main` is ahead by exactly the three claimed commits
  (`0cbcd2b`, `4fc8179 save notifications`, `99b66fb wysiwyg editor
  floating nav` — messages and file stats confirmed via `git show`).
  `git diff HEAD main --stat -- resources/themes/geko/` is exactly the
  claimed 5 files / 227 insertions / 28 deletions (`Header.svelte`,
  `NewsCard.svelte`, `style.css`, `templates/Home.svelte`,
  `templates/Page.svelte`). `theme.php`, `templates/Aktuelles.svelte` and
  `migrations/` are byte-identical on branch and main. `git status` shows
  no uncommitted theme work. The claimed file-by-file table for `0cbcd2b`
  (incl. `app.blade.php` single-theme-CSS, PageController/Edit.svelte
  title editing, dvm cleanup, legal docs) matches the commit exactly.
  The flagged "brief says theme.php but no theme.php change exists"
  discrepancy is accurate — `0cbcd2b` does not touch `theme.php`.
- **Container path (fixed):** `deployment/docker/compose.yml` mounts only
  `./database/database.sqlite`, `./database/tenants`, `./storage`
  (container paths `/var/www/html/...`); `ImportTenant::handle()` validates
  the archive path with `file_exists()` inside the container (line 37).
  Step 5 now scps into `/home/solicms/storage/app/` (or `docker compose cp`
  into `solicms-app:/var/www/html/storage/app/`) and Step 6 enters
  `/var/www/html/storage/app/geko-2026-08-15.zip`. Correct.
- **Migration-coverage conclusion (TASK-2, re-verified against the
  deployable tree):** main's `Home.svelte` reads only `hero_text`,
  `hero_image`, `intro_body`, `news_preview`, `termine`; main's
  `Page.svelte` reads only `body`; `Aktuelles.svelte` reads only `body`,
  `news`, `termine` — all declared in `theme.php` (which has exactly one
  commit, `7a1dc3f v1`). The two committed geko migrations (`010000`
  drops `home_geko.news_items`; `020000` renames `aktuelles.beitraege`→
  `news`, moves dated posts into `termine`, drops `home_geko.termine`)
  cover the only historical renames/drops. **No new migration required** —
  conclusion correct.
- **Runbook mechanics (TASK-3):** all verified against the repo. `make
  deploy` → `deployment && ansible-playbook -i hosts.ini deploy.yml` (root
  Makefile line 10); deploy.yml order APP_ENV=production gate → rsync with
  the stated exclusions (`--exclude=.git` etc., lines 62–68) → `docker
  compose build` (Dockerfile assets stage runs `npm run build`, line 6) →
  `migrate --force` → `tenants:migrate` → `themes:migrate` → restart;
  `themes:migrate` (MigrateThemes + ThemeMigrator) is idempotent, prints
  `"{tenant}:  {identifier}"` per pending migration or "Nothing to
  migrate.", identifiers are `geko/2026_08_08_...`, tracked per tenant in
  the `theme_migrations` table (tenant migration
  `2024_01_01_000012_create_theme_migrations_table.php`); `tenant:export`
  is interactive with default `./<slug>-<date>.zip`; `tenant:import` shows
  the archive summary then a destructive confirm prompt; TenantContentImporter
  full-replaces pages/sections/posts/nav_items/media, preserves
  users/sessions/permissions/activity_log, regenerates post slugs
  (`Post::syncSlug()`), rewrites `/media/{id}` refs to new ids, rejects
  zip-slip archives and re-sanitizes WYSIWYG values; `backup:run` is
  available and `config/backup.php` includes the central DB,
  `database_path('tenants')` and storage. hosts.ini matches the SSH target
  (`solipages`, `~/.ssh/sozialit.pem`).
- **TASK-4:** nothing to commit on the branch is correct — the code to
  ship is already committed on `main`; the gitignore nuance
  (`resources/themes/*` with `!resources/themes/default/` at lines
  29–31 of `.gitignore`) was re-verified.

Per task:

TASK-1: PASS
notes: Investigation now correct and verified: theme changes are on `main`
  (`0cbcd2b` + `4fc8179` + `99b66fb`), not on the branch; all file-level
  claims confirmed via `git show`/`git diff HEAD main`. Production
  `theme_migrations` state correctly deferred to runbook Step 2b
  (unreachable from sandbox). No files changed.

TASK-2: PASS
notes: Cross-check re-run against the deployable (main) tree — every key
  read by Home/Page/Aktuelles is declared in `theme.php`; the two committed
  migrations cover all historical renames/drops; no new migration required.
  The brief-vs-repo `theme.php` discrepancy is correctly flagged.

TASK-3: PASS
notes: Runbook is accurate and complete: premise corrected (deploy from
  `main`), Steps 0–2 verified against deploy.yml/Makefile/Dockerfile,
  Steps 4–6 export/transfer/import verified against
  ExportTenant/ImportTenant/TenantContentImporter/compose.yml (container
  path issue fixed), Step 7 checklist matches the deployed template
  behavior. One minor note, not a blocker: `backup:run` writes to the
  `backups` disk rooted at `base_path('backups')` (`config/filesystems.php`
  line 69), i.e. `/var/www/html/backups` inside the container — not one of
  the three mounted volumes — so the pre-import snapshot only survives for
  the current container lifetime. Within the runbook's own sequence
  (backup → import → verify) no restart occurs, so Step 7's restore
  fallback works; it would be lost after any `docker compose down/up`.
  Suggest noting in the runbook that the backup zip is container-ephemeral.

TASK-4: PASS
notes: Nothing to commit on the branch — correct, the theme code ships on
  `main` (`0cbcd2b`), which is already committed there; the gitignore
  nuance is re-verified and documented for future new files.

TASK-5: PASS
notes: Not executed (production operation — acceptable, the brief asks for
  a written plan; clearly documented as an operator step). Runbook now
  correctly directs the deploy from `main`, so the shipped theme is the
  requester's `0cbcd2b` state.

TASK-6: PASS
notes: Not executed; documented operator step. Export mechanics verified
  (interactive, read-only, default target `./<slug>-<date>.zip`).

TASK-7: PASS
notes: Not executed; documented operator step. Importer mechanics verified;
  the container-visible archive path fix (scp into
  `/home/solicms/storage/app/` or `docker compose cp`) is correct.

TASK-8: PASS
notes: Not executed; documented operator step. Verification checklist items
  match the deployed template behavior (welcome wall, news_preview limit 4,
  termine limit 10 sorted by `starts_at`, TOC in Page.svelte, sticky header,
  single-theme CSS).

## Security

No security findings. Read-only plan review; no code changed by this job.
The importer protections the runbook relies on (zip-slip rejection,
WYSIWYG re-sanitization) exist in `TenantContentImporter` and are
unaffected by the rework.

## Overall

APPROVED

The two blockers from the previous verdict are resolved and every claim in
the reworked implementation was independently verified against the repo.
No changes required before merge.

Non-blocking notes for the record (do not block merge):
1. `backup:run` (runbook Step 3) stores its archive inside the app
   container (`/var/www/html/backups`, the `backups` disk rooted at
   `base_path('backups')`), which is not one of the three composed-mounted
   volumes — the pre-import snapshot survives only for the current
   container lifetime. Fine within the runbook's own sequence, but the
   runbook could mention copying the zip out of the container if a longer
   retention window is wanted.
2. The working tree still carries uncommitted scaffold changes
   (`AGENTS.md`, `docs/jobs/female_geko-theme-migrations/tasks.md`) — job
   artifacts, not part of this task; left untouched, as documented.
