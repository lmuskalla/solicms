<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\SelectsTenant;
use Illuminate\Console\Command;

/**
 * Quick entrypoints for a tenant you're poking at locally — pick one, get
 * every URL you'd otherwise have to reassemble by hand (domain + port +
 * path) for its frontend, admin login, and (local dev only) dev-login.
 */
class TenantUrls extends Command
{
    use SelectsTenant;

    protected $signature = 'tenant:urls';

    protected $description = "Print a tenant's frontend/admin/dev-login URLs";

    public function handle(): int
    {
        $tenant = $this->selectTenant('Which tenant?');

        if (! $tenant) {
            return self::FAILURE;
        }

        [$scheme, $port] = $this->schemeAndPort();

        $this->newLine();
        $this->info("{$tenant->name} ({$tenant->theme})");

        foreach ($tenant->domains as $domain) {
            $base = "{$scheme}://{$domain->domain}{$port}";

            $this->newLine();
            $this->line("<comment>{$domain->domain}</comment>");
            $this->line("  Frontend:   {$base}/");
            $this->line("  Admin:      {$base}/admin/login");

            if (app()->environment('local')) {
                $this->line("  Dev-Login:  {$base}/admin/dev-login");
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string} [scheme, ":port" or '']
     */
    private function schemeAndPort(): array
    {
        $url = parse_url(config('app.url'));
        $scheme = $url['scheme'] ?? 'http';
        $port = $url['port'] ?? null;

        return [$scheme, $port ? ":{$port}" : ''];
    }
}
