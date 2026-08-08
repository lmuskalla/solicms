<?php

namespace App\Console\Commands\Concerns;

use App\Models\Tenant;

use function Laravel\Prompts\select;

/**
 * Shared by every tenant:* command — lets the operator pick a tenant from a
 * list instead of having to remember/type an exact domain.
 */
trait SelectsTenant
{
    protected function selectTenant(string $label = 'Tenant'): ?Tenant
    {
        $tenants = Tenant::with('domains')->orderBy('name')->get();

        if ($tenants->isEmpty()) {
            $this->error('No tenants exist yet. Run tenant:setup first.');

            return null;
        }

        $id = select(
            label: $label,
            options: $tenants->mapWithKeys(fn (Tenant $t) => [
                $t->id => $t->name.' — '.$t->domains->pluck('domain')->implode(', '),
            ])->all(),
        );

        return $tenants->firstWhere('id', $id);
    }
}
