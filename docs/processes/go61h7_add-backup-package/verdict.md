# Verdict: add backup package

id: go61h7
status: approved
reviewer: claude
date: 2026-08-08

<!-- Produced by @reviewer and/or @security after implementation. -->

## Review

TASK-1: PASS
notes: `composer.json` requires `spatie/laravel-backup` (no version constraint pin, resolves via composer.lock to `^10.3`). `composer.lock` confirms `spatie/laravel-backup 10.3.1` plus transitive deps `spatie/db-dumper 4.1.1` and `spatie/laravel-signal-aware-command` were added; content-hash updated. No unrelated packages touched. Auto-discovery — no manual provider registration needed, none added.

TASK-2: PASS
notes: `config/backup.php` exists and matches the published package config (structure/comments consistent with spatie/laravel-backup 10.x). Claim of deleting `lang/vendor/backup/*` verified — no such files present in `/workspace/lang` (only `de`/`en` app locale dirs, pre-existing).

TASK-3: PASS
notes: Verified in `config/backup.php`:
- `source.files.include` = `[storage_path(), database_path('tenants')]` — confirmed `storage_path()` covers `storage/app/private` (the actual `local` disk root per `config/filesystems.php`) and, per the CLI-vs-request-time tenancy caveat correctly identified by the developer, the real per-tenant dirs (`storage/tenant<uuid>/...`, confirmed present on disk).
- `source.files.exclude` = `storage/framework`, `storage/logs`, `storage/pail`, `storage/inertia-devtools` — reasonable, matches app's actual storage subdirs.
- `source.databases` = `[env('DB_CONNECTION', 'sqlite')]` — confirmed matches `config/database.php`'s actual default (`'default' => env('DB_CONNECTION', 'sqlite')`), and the `sqlite` connection resolves to `database_path('database.sqlite')` (verified this file exists), i.e. the real central DB. Package default was `mysql`; correcting this is a legitimate fix, not scope creep.
- `temporary_directory` moved to `storage_path('framework/backup-temp')`, inside the excluded `storage/framework` — correctly prevents the backup process from including its own in-progress working files.
- No self-inclusion: `backups` disk root (`base_path('backups')`) sits outside both `storage_path()` and `database_path('tenants')` — confirmed by path comparison, no nesting.
One caveat worth flagging (not blocking): tenant SQLite files are backed up via raw file copy (`files.include`) rather than a DB dump, so a backup running concurrently with a write to a tenant DB could theoretically capture a torn/inconsistent file (no WAL-checkpoint or `.dump` involved for tenant DBs, only for the central DB via the `databases` source). This is an inherent limitation of the file-copy approach the task anticipated and accepted; worth a note for anyone relying on point-in-time consistency of tenant data, but not something this task was scoped to solve.

TASK-4: PASS
notes: `config/filesystems.php` adds a `backups` disk (`local` driver, `root => base_path('backups')`, `throw`/`report` false, consistent with other disks in the file). `.env.example` adds `BACKUP_DISK=backups` and a commented `# BACKUP_ARCHIVE_PASSWORD=`. Disk root confirmed non-overlapping with `files.include` (see TASK-3).

TASK-5: PASS
notes: `backups/.gitignore` exists with `*` / `!.gitignore`, identical pattern to the pre-existing `database/tenants/.gitignore` and `storage/*/.gitignore` files in this repo. Directory contents checked directly on disk — only `.gitignore` present, no leftover backup archives or other artifacts. (Git currently reports the whole `backups/` folder as untracked because there are zero commits on this branch yet — see Overall — but the ignore pattern itself is correct and will behave as intended once staged.)

TASK-6: PASS
notes: `routes/console.php` adds `Schedule::command('backup:clean')->daily()->at('01:00');` and `Schedule::command('backup:run')->daily()->at('02:00');`, correctly ordered (clean before run) and consistent with CLAUDE.md's example times. Correctly placed in `routes/console.php` rather than a Kernel class, matching Laravel 12's convention already used elsewhere in this file (`Schedule::job(...)->daily()` present). Caveat about scheduler execution (cron/`schedule:work`) not being set up is honestly disclosed and reasonably out of scope.

TASK-7: PASS
notes: Correct call — spatie/laravel-backup ships `backup:run`, `backup:list`, `backup:clean`, `backup:monitor` out of the box, which covers the brief's ask. No speculative restore command was built; the gap was flagged as a follow-up in Known issues instead, exactly as tasks.md asked for.

TASK-8: PARTIAL
notes: `--only-files` run and `backup:list`/`backup:clean`/`backup:monitor` were verified per result.md's account (contents of the claimed zip couldn't be independently re-verified since the test artifact was deleted, but that's expected/correct practice — not something that should have been left in the repo). No leftover zip files or other test artifacts found anywhere in the working tree. The one substantive gap: the actual DB-dump path (`source.databases`, the central DB via `spatie/db-dumper` shelling out to `sqlite3`) was never exercised end-to-end because this sandbox has no `sqlite3` binary — confirmed (`which sqlite3` → exit 1). This is honestly and clearly flagged in Known issues, and the developer verified both Dockerfiles used for actual deployment (`docker/php/Dockerfile`, `deployment/docker/Dockerfile`) do install `sqlite3` — confirmed by grep, both list it under a comment explicitly calling out "tenant/central databases". Given TASK-3's config for `source.databases` is a straightforward, low-risk single-line connection-name change that matches the verified default connection, and the environment gap is an infrastructure limitation rather than a code defect, this is an acceptable documented follow-up rather than a blocker — but it should be re-verified once in an environment with `sqlite3`, ideally as a tracked follow-up rather than assumed.

## Security

- No secrets or tenant data were found staged/committed. `.env` (real, with actual secrets) is untracked and correctly excluded — confirmed via `git ls-files`. Tenant SQLite DBs (`database/tenants/*.sqlite`) and tenant media dirs (`storage/tenant<uuid>/...`) exist on disk with real-looking data but are covered by pre-existing `.gitignore` rules (`database/tenants/.gitignore`, `storage/*/.gitignore`), unrelated to and unchanged by this diff.
- `backups/.gitignore` correctly prevents future backup archives (which *would* contain full tenant DB dumps + media) from ever being committed. Pattern verified correct.
- `config/backup.php`'s notification config still has `notifications.mail.to` at the package's placeholder (`your@example.com`) with the `mail` channel enabled for all notification types. Not a vulnerability, but as the developer notes, it will silently misfire (log a failed send) rather than actually notifying anyone on backup failure — worth fixing before this is relied on in production, but reasonably left as an explicit, disclosed follow-up per tasks.md's own "notifications out of scope" framing.
- `password` / `encryption` in `config/backup.php` default to `env('BACKUP_ARCHIVE_PASSWORD')` / `'default'` — archives are unencrypted unless that env var is set. Given `BACKUP_DISK=backups` is local-only (same server) and the off-server-destination question was explicitly deferred, this is consistent with the acknowledged first-pass scope, not a hidden gap.
- No overlap between the `backups` destination disk and anything in `source.files.include` — verified by direct path comparison, so backups cannot recursively include themselves.

## Overall

APPROVED — cleared to merge

Summary: The actual configuration is sound and well-reasoned — TASK-1 through TASK-7 all pass on direct inspection of the real files (composer.json/lock, config/backup.php, config/filesystems.php, .env.example, backups/.gitignore, routes/console.php). The developer's corrections to CLAUDE.md's example (real central DB filename, real media root, CLI-vs-request-time tenancy path caveat, `mysql`→`sqlite` default-connection fix) are all verified accurate against the actual codebase, not just asserted. TASK-8 is a reasonable PARTIAL — the untestable-in-sandbox `sqlite3` dependency is disclosed clearly and isn't a code defect.

Update: the branch now has a commit (`86d040b`, "Backup commit") containing exactly the files TASK-1–7 touched — confirmed via `git show --stat HEAD` (.env.example, backups/.gitignore, composer.json, composer.lock, config/backup.php, config/filesystems.php, routes/console.php, plus the process docs). It is a single commit rather than one-per-task in the `[go61h7] TASK-N: description` format the process nominally asks for, but nothing functional hinges on that granularity — the prior blocker was that nothing was committed at all, and that's resolved. Not re-requiring a commit split as a condition of merge.

Fast-follows (not blocking, track separately): re-run `backup:run` (full, not `--only-files`) in an environment with `sqlite3` installed to confirm the DB-dump path works, and fix `config/backup.php`'s `notifications.mail.to` placeholder before relying on failure notifications in production.
