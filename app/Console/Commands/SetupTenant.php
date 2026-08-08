<?php

namespace App\Console\Commands;

use App\Services\TenantProvisioner;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class SetupTenant extends Command
{
    protected $signature = 'tenant:setup';

    protected $description = 'Create and configure a new tenant';

    public function handle(TenantProvisioner $provisioner): int
    {
        [$domains, $name, $email, $editorName, $template, $theme] = $this->wizard();

        $primaryDomain = $domains[0];

        $this->info("Creating tenant: {$name} (".implode(', ', $domains).')');

        try {
            $result = $provisioner->provision($domains, $name, $email, $editorName, $template, $theme);
        } catch (RuntimeException|InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Done.');
        $this->line("  URL:      https://{$primaryDomain}/admin/login");
        $this->line("  Email:    {$email}");
        $this->line("  Password: {$result['password']}");
        $this->warn('Tell the client to change their password immediately — it is not stored anywhere.');

        return self::SUCCESS;
    }

    /**
     * @return array{0: array<int, string>, 1: string, 2: string, 3: string, 4: string, 5: string}
     */
    private function wizard(): array
    {
        $domains = [];

        while (true) {
            $domain = text(
                label: $domains === [] ? 'Domain' : 'Another domain (leave blank to finish)',
                placeholder: 'ngo-example.org',
                required: $domains === [],
            );

            if ($domain === '' || $domain === null) {
                break;
            }

            $domains[] = $domain;
        }

        $primaryDomain = $domains[0];

        $name = text(
            label: 'Display name',
            default: $primaryDomain,
        );

        $email = text(
            label: 'Admin email',
            default: 'admin@'.$primaryDomain,
        );

        // Shown as "Willkommen, {name}" and in the admin sidebar — a real
        // person's name, not the generic 'Website-Redakteur' this used to
        // hardcode for every tenant.
        $editorName = text(
            label: 'Editor name',
            default: 'Website-Redakteur',
        );

        $themes = config('themes');

        $theme = select(
            label: 'Theme',
            options: collect($themes)->mapWithKeys(
                fn (array $t, string $key) => [$key => $t['label'] ?? $key]
            )->all(),
            default: 'default',
        );

        // Home page templates for a theme are keyed 'home_<template>', per
        // seedPages()'s "home_{$template}" convention in TenantProvisioner.
        $templateOptions = collect($themes[$theme]['templates'] ?? [])
            ->filter(fn (array $t, string $key) => str_starts_with($key, 'home_'))
            ->mapWithKeys(fn (array $t, string $key) => [substr($key, 5) => $t['label'] ?? $key]);

        $template = $templateOptions->count() > 1
            ? select(label: 'Home page template', options: $templateOptions->all())
            : ($templateOptions->keys()->first() ?? 'default');

        return [$domains, $name, $email, $editorName, $template, $theme];
    }
}
