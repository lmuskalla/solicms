# Result: add backup package

id: go61h7
status: ready-for-review
developer: claude
date: 2026-08-08

<!-- Produced by @developer after implementation. -->

## Summary

TASK-1: Done. `composer require spatie/laravel-backup` (resolved to ^10.3, auto-discovered).

TASK-2: Done. Published `config/backup.php` via `vendor:publish --provider="Spatie\Backup\BackupServiceProvider"`. Did not keep the also-published `lang/vendor/backup/*` translation files (30+ locales) — deleted them, since nothing here customizes notification copy and the package already ships its own bundled translations as a fallback.

TASK-3: Done, and the analysis in this task's description needed a correction: tenant media does **not** live under `storage/app/private`. `config/tenancy.php`'s `filesystem` bootstrapper suffixes `storage_path()` per tenant at request time (`storage/tenant<id>/...`), so a CLI run of `backup:run` — which never initializes tenancy — sees the real, unsuffixed `storage/` directory containing every tenant's subfolder directly. Configured:
- `source.files.include`: `storage_path()` (sweeps up central `storage/app` **and** every `storage/tenant<id>/...` in one entry) + `database_path('tenants')` (tenant SQLite files, raw-copied since they aren't static connections the `databases` source can dump).
- `source.files.exclude`: `storage/framework`, `storage/logs`, `storage/pail`, `storage/inertia-devtools` (regenerable/dev-only, no user data).
- `source.databases`: `env('DB_CONNECTION', 'sqlite')` — was `env('DB_CONNECTION', 'mysql')` in the package default, which didn't match this app's actual default connection.
- Added a dedicated `backups` disk (see TASK-4) as the destination, rooted at `base_path('backups')` — outside every directory listed in `files.include` — so backup archives never get swept into the next backup (which would otherwise balloon archive size run over run).
- Moved `temporary_directory` from the default `storage_path('app/backup-temp')` to `storage_path('framework/backup-temp')`, reusing the already-excluded `storage/framework` path for the same reason (mid-run working files would otherwise land inside `storage/app`, which is now backed up).

TASK-4: Done. Added `backups` disk to `config/filesystems.php` (`root = base_path('backups')`). Added `BACKUP_DISK=backups` and a commented `# BACKUP_ARCHIVE_PASSWORD=` to `.env.example`.

TASK-5: Done. Created `/backups/.gitignore` (`*` / `!.gitignore`), following the same pattern already used for `database/tenants/.gitignore` and the `storage/app/*` gitignores in this repo.

TASK-6: Done. Added to `routes/console.php` (this app schedules there, not via `app/Console/Kernel.php`):
```php
Schedule::command('backup:clean')->daily()->at('01:00');
Schedule::command('backup:run')->daily()->at('02:00');
```
Scheduler execution (cron → `schedule:run`, or `schedule:work`) was not verified/set up in this environment — flagging per the task's own caveat, out of scope unless requested.

TASK-7: Confirmed no custom Artisan command is needed. `php artisan list backup` shows the package already ships `backup:run`, `backup:list`, `backup:clean`, `backup:monitor` — full coverage of what the brief asked for.

TASK-8: Done, with one caveat. `php artisan backup:run --only-files` was run and verified end-to-end (see Known issues for why `--only-files` instead of the full run): the resulting zip contains all 4 tenant `.sqlite` files from `database/tenants/`, every tenant's uploaded media under `storage/tenant<id>/app/public/...` (including medialibrary conversions), and the central `storage/app` tree — nothing from `storage/framework`, `storage/logs`, `storage/pail`, or `storage/inertia-devtools` leaked in. Backup landed on the new `backups` disk at `backups/laravel-backup/*.zip`, confirming no self-inclusion. `backup:list`, `backup:clean`, and `backup:monitor` were also run and all completed successfully against the new disk. Test artifact was deleted after verification.

## Changes

- `composer.json`, `composer.lock` — added `spatie/laravel-backup` (^10.3).
- `config/backup.php` (new) — published package config, tuned per TASK-3 above.
- `config/filesystems.php` — added `backups` disk (`local` driver, root `base_path('backups')`).
- `.env.example` — added `BACKUP_DISK=backups` and commented `BACKUP_ARCHIVE_PASSWORD`.
- `backups/.gitignore` (new) — ignores backup archive contents, keeps the directory tracked.
- `routes/console.php` — scheduled `backup:clean` (01:00) and `backup:run` (02:00) daily.

## Known issues / follow-ups

- **This sandbox has no `sqlite3` CLI binary**, so a full `backup:run` (which shells out to `sqlite3` via `spatie/db-dumper` to dump the central database) fails here with `sh: sqlite3: not found`. This is not a code issue: both `docker/php/Dockerfile` and `deployment/docker/Dockerfile` already install `sqlite3` via apt for exactly this reason (their comments call out "tenant/central databases"). Verification of the database-dump path (as opposed to the files path, which was fully verified) should be re-run once this lands in an environment with `sqlite3` installed — e.g. as part of deploying this change, run `php artisan backup:run` once and confirm the zip contains a `db-dumps/*.sql` (or `.sql.gz`) entry for the central DB.
- Backups are local-only (`BACKUP_DISK=backups`, same server the data lives on). CLAUDE.md's own `.env` example has the same limitation. Flagged per tasks.md's open question — no off-server destination (S3/SFTP) was configured; add one if/when required.
- No backup-failure notifications were wired up (mail `to` in `config/backup.php` is left at the package's placeholder `your@example.com`). Per tasks.md's open question, treating this as out of scope unless requested — but worth noting the notification config is still "live" (mail channel enabled by default), so on a misconfigured mail setup a failed/successful backup will attempt to notify that placeholder address and fail silently in the log rather than doing anything useful.
- No restore tooling exists (`spatie/laravel-backup` doesn't ship one). Per the brief's wording ("a way to make backups") this is out of scope, but a backup that's never been restored from is unverified — worth a manual restore drill before relying on this in production.
- Retention policy left at package defaults (`config/backup.php` → `cleanup.default_strategy`) — no values were specified in the brief.
