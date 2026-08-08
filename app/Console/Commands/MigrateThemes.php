<?php

namespace App\Console\Commands;

use App\Services\ThemeMigrator;
use Illuminate\Console\Command;

/**
 * Meant to run on every deploy, right after `tenants:migrate` — idempotent,
 * no prompts. See App\Services\ThemeMigrator and
 * resources/themes/<slug>/migrations/ for what these actually are.
 */
class MigrateThemes extends Command
{
    protected $signature = 'themes:migrate';

    protected $description = "Run each tenant's pending theme content migrations";

    public function handle(ThemeMigrator $migrator): int
    {
        $ran = $migrator->migrate();

        if ($ran === []) {
            $this->info('Nothing to migrate.');

            return self::SUCCESS;
        }

        foreach ($ran as $tenantName => $migrations) {
            $this->info("{$tenantName}:");

            foreach ($migrations as $migration) {
                $this->line("  {$migration}");
            }
        }

        return self::SUCCESS;
    }
}
