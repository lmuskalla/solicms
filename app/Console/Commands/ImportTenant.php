<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\SelectsTenant;
use App\Services\TenantContentImporter;
use Illuminate\Console\Command;
use InvalidArgumentException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

/**
 * Loads a tenant:export archive into a tenant, replacing its content
 * entirely. See TenantContentImporter's class docblock for exactly what
 * that does and does not preserve.
 */
class ImportTenant extends Command
{
    use SelectsTenant;

    protected $signature = 'tenant:import';

    protected $description = "Import a tenant's content from a .zip, replacing what's there";

    public function handle(TenantContentImporter $importer): int
    {
        $tenant = $this->selectTenant('Which tenant do you want to import into?');

        if (! $tenant) {
            return self::FAILURE;
        }

        $archivePath = text(
            label: 'Path to the export .zip',
            required: true,
            validate: fn (string $value) => file_exists($value) ? null : 'No file found at that path.',
        );

        try {
            $summary = $importer->inspect($archivePath);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('This archive contains:');
        $this->line("  From tenant: {$summary['tenant_name']}");
        $this->line("  Exported at: {$summary['exported_at']}");
        $this->line("  Pages:     {$summary['pages']}");
        $this->line("  Sections:  {$summary['sections']}");
        $this->line("  Posts:     {$summary['posts']}");
        $this->line("  Nav items: {$summary['nav_items']}");
        $this->line("  Media:     {$summary['media']}");
        $this->newLine();

        if (! confirm(
            label: "Replace ALL current content on \"{$tenant->name}\" with the above? This deletes its existing pages, sections, posts, nav items, and media first. This cannot be undone.",
            default: false,
        )) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $this->info("Importing into \"{$tenant->name}\"...");

        try {
            $counts = $importer->import($tenant, $archivePath);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Done.');
        $this->line("  Pages:     {$counts['pages']}");
        $this->line("  Sections:  {$counts['sections']}");
        $this->line("  Posts:     {$counts['posts']}");
        $this->line("  Nav items: {$counts['nav_items']}");
        $this->line("  Media:     {$counts['media']}");

        return self::SUCCESS;
    }
}
