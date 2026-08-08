<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\SelectsTenant;
use App\Services\ThemeMigrator;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;

/**
 * The explicit, rarer "undo" for one tenant at a time — deliberately not
 * part of the automatic themes:migrate deploy step. See App\Services\ThemeMigrator.
 */
class RollbackThemeMigrations extends Command
{
    use SelectsTenant;

    protected $signature = 'themes:rollback';

    protected $description = "Revert the last batch of a tenant's theme content migrations";

    public function handle(ThemeMigrator $migrator): int
    {
        $tenant = $this->selectTenant('Which tenant?');

        if (! $tenant) {
            return self::FAILURE;
        }

        if (! confirm(
            label: "Revert the last batch of theme migrations for \"{$tenant->name}\"?",
            default: false,
        )) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $reverted = $migrator->rollbackTenant($tenant);

        if ($reverted === []) {
            $this->info('Nothing to roll back.');

            return self::SUCCESS;
        }

        foreach ($reverted as $migration) {
            $this->line("  Reverted: {$migration}");
        }

        return self::SUCCESS;
    }
}
