<?php

namespace App\Services;

use App\Models\Tenant;
use App\Services\ThemeMigrations\ThemeMigration;
use Illuminate\Support\Facades\DB;

/**
 * Runs resources/themes/<slug>/migrations/*.php against tenant content —
 * theme_migrations tracks what's already run per tenant, same idea as
 * Laravel's own migrations table. migrate() is meant to run on every
 * deploy (idempotent, no prompts — see deployment/deploy.yml, right after
 * `tenants:migrate`); rollbackTenant() is the explicit, rarer "undo" an
 * operator reaches for on one tenant at a time.
 */
class ThemeMigrator
{
    /**
     * @return array<string, string[]> tenant name => migration identifiers that ran
     */
    public function migrate(): array
    {
        $ran = [];

        foreach (Tenant::all() as $tenant) {
            tenancy()->initialize($tenant);

            try {
                $applied = $this->runPending($tenant->theme);

                if ($applied !== []) {
                    $ran[$tenant->name] = $applied;
                }
            } finally {
                tenancy()->end();
            }
        }

        return $ran;
    }

    /**
     * @return string[] migration identifiers reverted, in the order they were reverted
     */
    public function rollbackTenant(Tenant $tenant): array
    {
        tenancy()->initialize($tenant);

        try {
            $batch = DB::table('theme_migrations')->max('batch');

            if (! $batch) {
                return [];
            }

            $records = DB::table('theme_migrations')->where('batch', $batch)->orderByDesc('id')->get();
            $files = $this->migrationFiles($tenant->theme);
            $reverted = [];

            foreach ($records as $record) {
                $path = $files[$record->migration] ?? null;

                if (! $path) {
                    continue;
                }

                DB::transaction(function () use ($path, $record) {
                    $this->load($path)->down();

                    DB::table('theme_migrations')->where('id', $record->id)->delete();
                });

                $reverted[] = $record->migration;
            }

            return $reverted;
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Must run inside an already-initialized tenant.
     *
     * @return string[] migration identifiers that ran, in order
     */
    private function runPending(string $theme): array
    {
        $files = $this->migrationFiles($theme);
        $already = DB::table('theme_migrations')->pluck('migration')->all();
        $batch = (DB::table('theme_migrations')->max('batch') ?? 0) + 1;
        $applied = [];

        foreach ($files as $identifier => $path) {
            if (in_array($identifier, $already, true)) {
                continue;
            }

            DB::transaction(function () use ($path, $identifier, $batch) {
                $this->load($path)->up();

                DB::table('theme_migrations')->insert([
                    'migration' => $identifier,
                    'batch' => $batch,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

            $applied[] = $identifier;
        }

        return $applied;
    }

    /**
     * @return array<string, string> migration identifier ("dvm/2026_..._x") => absolute file path, sorted by filename
     */
    private function migrationFiles(string $theme): array
    {
        $files = glob(resource_path("themes/{$theme}/migrations/*.php")) ?: [];
        sort($files);

        $keyed = [];

        foreach ($files as $file) {
            $keyed["{$theme}/".basename($file, '.php')] = $file;
        }

        return $keyed;
    }

    private function load(string $path): ThemeMigration
    {
        return require $path;
    }
}
