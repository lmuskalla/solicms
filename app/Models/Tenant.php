<?php

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    /**
     * Columns stored as real table columns rather than in the `data` JSON blob.
     * Must match the columns defined in the create_tenants_table migration.
     */
    public static function getCustomColumns(): array
    {
        return ['id', 'name', 'theme', 'locale'];
    }
}
