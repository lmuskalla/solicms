<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class ListTenants extends Command
{
    protected $signature = 'tenant:list';

    protected $description = 'List all tenants and their domains';

    public function handle(): int
    {
        $tenants = Tenant::with('domains')->orderBy('name')->get();

        if ($tenants->isEmpty()) {
            $this->info('No tenants exist yet. Run tenant:setup to create one.');

            return self::SUCCESS;
        }

        // The UUID primary key is an implementation detail — other tenant:*
        // commands let you pick a tenant by name, so it's not worth a column.
        $this->table(
            ['Name', 'Domains', 'Theme'],
            $tenants->map(fn (Tenant $t) => [
                $t->name,
                $t->domains->pluck('domain')->implode(', '),
                $t->theme,
            ]),
        );

        return self::SUCCESS;
    }
}
