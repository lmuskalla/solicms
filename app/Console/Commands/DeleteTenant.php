<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\SelectsTenant;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;

/**
 * Tears down a tenant entirely: central record, all its domains (cascade via
 * FK — see create_domains_table), and its SQLite database (via the
 * TenantDeleted -> DeleteDatabase pipeline in TenancyServiceProvider).
 */
class DeleteTenant extends Command
{
    use SelectsTenant;

    protected $signature = 'tenant:delete';

    protected $description = 'Delete a tenant, all its domains, and its database';

    public function handle(): int
    {
        $tenant = $this->selectTenant('Which tenant do you want to delete?');

        if (! $tenant) {
            return self::FAILURE;
        }

        $allDomains = $tenant->domains->pluck('domain')->implode(', ');

        if (! confirm(
            label: "Delete tenant \"{$tenant->name}\" ({$allDomains}) and its entire database? This cannot be undone.",
            default: false,
        )) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        // Fires TenantDeleted, which synchronously runs DeleteDatabase
        // (see TenancyServiceProvider::events()) — the tenant's SQLite file
        // is removed. Domain rows cascade-delete via their FK to tenants.
        $tenant->delete();

        $this->info("Deleted tenant \"{$tenant->name}\" ({$allDomains}).");

        return self::SUCCESS;
    }
}
