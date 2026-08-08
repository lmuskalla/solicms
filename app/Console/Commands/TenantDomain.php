<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\SelectsTenant;
use Illuminate\Console\Command;
use Stancl\Tenancy\Database\Models\Domain;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Adds or removes a domain on an existing tenant — e.g. a client picks up a
 * second domain, or a legacy one is retired. tenant:setup only covers the
 * domain(s) a tenant is created with.
 */
class TenantDomain extends Command
{
    use SelectsTenant;

    protected $signature = 'tenant:domain';

    protected $description = 'Add or remove a domain on an existing tenant';

    public function handle(): int
    {
        $action = select(
            label: 'What do you want to do?',
            options: ['add' => 'Add a domain', 'remove' => 'Remove a domain'],
        );

        return $action === 'add' ? $this->add() : $this->remove();
    }

    private function add(): int
    {
        $tenant = $this->selectTenant('Which tenant should the domain be added to?');

        if (! $tenant) {
            return self::FAILURE;
        }

        $domain = text(
            label: 'New domain',
            placeholder: 'ngo-example.org',
            required: true,
            validate: fn (string $value) => Domain::where('domain', $value)->exists()
                ? 'That domain is already registered.'
                : null,
        );

        $tenant->domains()->create(['domain' => $domain]);

        $this->info("Added domain [{$domain}] to tenant \"{$tenant->name}\".");

        return self::SUCCESS;
    }

    private function remove(): int
    {
        $tenant = $this->selectTenant('Remove a domain from which tenant?');

        if (! $tenant) {
            return self::FAILURE;
        }

        if ($tenant->domains->count() <= 1) {
            $this->error("Tenant \"{$tenant->name}\" only has one domain ({$tenant->domains->first()->domain}). Use tenant:delete to remove the tenant instead.");

            return self::FAILURE;
        }

        $domainId = select(
            label: 'Which domain should be removed?',
            options: $tenant->domains->pluck('domain', 'id')->all(),
        );

        /** @var Domain $domainRecord */
        $domainRecord = $tenant->domains->firstWhere('id', $domainId);
        $domainRecord->delete();

        $this->info("Removed domain [{$domainRecord->domain}] from tenant \"{$tenant->name}\".");

        return self::SUCCESS;
    }
}
