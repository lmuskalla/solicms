# Tasks: add backup package

id: go61h7
status: open
analyst: architect
date: 2026-08-08

<!-- Produced by @analyst from brief.md. -->

## Task breakdown

TASK-1: Require `spatie/laravel-backup` via composer and let auto-discovery register its service provider.
     files: composer.json, composer.lock
     depends: none
     risk: low — additive dependency install, no existing code touched.

TASK-2: Publish the package config to `config/backup.php` (`vendor:publish --provider="Spatie\Backup\BackupServiceProvider"`).
     files: config/backup.php (new)
     depends: TASK-1
     risk: low — file is new, doesn't overwrite anything.

TASK-3: Configure `config/backup.php` source list for this app's actual layout — central DB is `database/database.sqlite` (not `central.sqlite` as CLAUDE.md's example implies), tenant DBs live in `database/tenants/*.sqlite`, and media lives under `storage/app/private` (per `config/filesystems.php`, not `storage/app` generically). Needs `'databases' => ['sqlite']` plus `files.include` pointing at `database_path('database.sqlite')`, `database_path('tenants')`, and the real media root. Also decide/set backup destination disk and zip naming.
     files: config/backup.php
     depends: TASK-2
     risk: medium — getting paths wrong silently produces empty/incomplete backups; also need to confirm whether the SQLite databases source type works correctly when connections point at per-tenant files vs. the default connection (the package's `databases` source dumps configured DB *connections*, tenant sqlite files aren't a configured connection — needs verification, possibly requiring the tenant files to be handled only via `files.include` rather than `databases`).

TASK-4: Add backup-related environment variables to `.env.example` (e.g. `BACKUP_DISK`, backup archive password if desired) and document them; confirm `config/filesystems.php` has (or gains) a disk suitable as a backup destination.
     files: .env.example, config/filesystems.php
     depends: TASK-3
     risk: low — config/docs only, unless a new disk driver needs adding, which would raise risk.

TASK-5: Add `.gitignore` entry for the local backup output directory (default `storage/app/Laravel` or wherever `BACKUP_DISK` points) so archives containing tenant data/media are never committed.
     files: .gitignore
     depends: TASK-4
     risk: low — but important: skipping this risks committing customer data into the repo.

TASK-6: Schedule `backup:run` and `backup:clean` in `routes/console.php` (this app uses Laravel 12's routes/console.php for scheduling, not `app/Console/Kernel.php` as CLAUDE.md's snippet shows).
     files: routes/console.php
     depends: TASK-3
     risk: low — additive schedule entries; verify the scheduler is actually running in this environment (cron/`schedule:work`), otherwise this is a no-op in practice — out of scope to set that up unless asked.

TASK-7: Verify whether custom Artisan commands are needed at all. The package already ships `backup:run`, `backup:list`, `backup:clean`, and `backup:monitor` — the brief only asks for commands "if the package does not include them already." Current read: no custom command is required; this task is to confirm that during implementation and, if a gap is found (e.g. a wrapper command for restore, since spatie/laravel-backup has no built-in restore command), scope a follow-up task rather than build it speculatively.
     files: none expected; possibly app/Console/Commands/ if a gap is found
     depends: TASK-3
     risk: low — verification task; flag rather than guess at restore tooling scope.

TASK-8: Manually run `backup:run` against a local/dev copy of the data and confirm the resulting archive contains the central DB, all tenant DBs, and media, then confirm `backup:clean` respects retention config.
     files: none (verification only)
     depends: TASK-3, TASK-6
     risk: low — test/verification step, but the one that actually validates TASK-3's correctness.

## Open questions for @analyst / requester before implementation

- Backup destination: local disk only, or should this provision an off-server target (S3-compatible, SFTP, etc.)? CLAUDE.md's `.env` example only defines `BACKUP_DISK=local`, which backs up onto the same server it's protecting against — worth confirming this is intentional for a first pass.
- Notification on backup failure (spatie/laravel-backup supports mail/Slack notifications) — not mentioned in the brief; treating as out of scope unless requested.
- Retention policy (`config/backup.php` `cleanup` strategy/limits) — no values specified in the brief; will use package defaults unless directed otherwise.
- No restore tooling is in scope per the brief's wording ("a way to make backups") — confirm that's correct, since a backup without a documented/tested restore path is of limited value.
