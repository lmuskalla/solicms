<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\SelectsTenant;
use App\Services\TenantContentExporter;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Laravel\Prompts\text;

/**
 * Dumps a tenant's content to a .zip that tenant:import can load elsewhere
 * — e.g. moving what was built in local dev onto the production instance.
 */
class ExportTenant extends Command
{
    use SelectsTenant;

    protected $signature = 'tenant:export';

    protected $description = "Export a tenant's content (pages, sections, posts, nav, media) to a .zip";

    public function handle(TenantContentExporter $exporter): int
    {
        $tenant = $this->selectTenant('Which tenant do you want to export?');

        if (! $tenant) {
            return self::FAILURE;
        }

        $default = getcwd().'/'.Str::slug($tenant->name).'-'.now()->format('Y-m-d').'.zip';

        $destination = text(
            label: 'Save the export to',
            default: $default,
        );

        $this->info("Exporting \"{$tenant->name}\"...");

        $counts = $exporter->export($tenant, $destination);

        $this->info("Done: {$destination}");
        $this->line("  Pages:     {$counts['pages']}");
        $this->line("  Sections:  {$counts['sections']}");
        $this->line("  Posts:     {$counts['posts']}");
        $this->line("  Nav items: {$counts['nav_items']}");
        $this->line("  Media:     {$counts['media']}");

        return self::SUCCESS;
    }
}
