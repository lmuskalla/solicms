<?php

namespace App\Services;

use App\Models\NavItem;
use App\Models\Page;
use App\Models\SiteConfig;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Stancl\Tenancy\Database\Models\Domain;

/**
 * Shared by `tenant:setup` and the superadmin "create tenant" form — the two
 * places a new tenant can be provisioned from. Keeping the logic here means
 * neither can drift from the other.
 */
class TenantProvisioner
{
    /**
     * @param  string|array<int, string>  $domains  One or more domains this tenant
     *                                               should resolve on — e.g. a client
     *                                               with both a .de and a legacy
     *                                               .org domain pointed at the same site.
     * @param  string  $editorName  The seeded user's actual name — shown as
     *                               "Willkommen, {name}" and in the admin
     *                               sidebar. Not the tenant/org name ($name).
     * @return array{tenant: Tenant, password: string}
     */
    public function provision(string|array $domains, string $name, string $email, string $editorName, string $template = 'default', string $theme = 'default'): array
    {
        $domains = is_array($domains) ? $domains : [$domains];
        $domains = array_values(array_unique(array_filter(array_map('trim', $domains))));

        if ($domains === []) {
            throw new InvalidArgumentException('At least one domain is required.');
        }

        $taken = Domain::whereIn('domain', $domains)->pluck('domain');
        if ($taken->isNotEmpty()) {
            throw new RuntimeException('Domain(s) already registered to a tenant: '.$taken->implode(', ').'.');
        }

        if (! config("themes.{$theme}")) {
            $available = implode(', ', array_keys(config('themes')));

            throw new InvalidArgumentException("Unknown theme [{$theme}]. Available: {$available}.");
        }

        // Tenant::create() fires TenantCreated, which synchronously runs
        // CreateDatabase + MigrateDatabase (see TenancyServiceProvider::events()).
        // By the time control returns here, the tenant DB exists and is migrated.
        $tenant = Tenant::create(['name' => $name, 'theme' => $theme]);
        foreach ($domains as $domain) {
            $tenant->domains()->create(['domain' => $domain]);
        }

        $password = Str::password(16);

        tenancy()->initialize($tenant);

        try {
            $this->seedPages($template, $theme);
            $this->seedSiteConfig($name);
            $this->createEditorUser($email, $editorName, $password);
        } finally {
            tenancy()->end();
        }

        return ['tenant' => $tenant, 'password' => $password];
    }

    private function seedPages(string $template, string $theme): void
    {
        // Slugs stay ASCII for clean URLs; titles are what the client sees in the admin.
        $pages = [
            ['slug' => 'home', 'title' => 'Startseite', 'template' => "home_{$template}", 'order' => 1],
            ['slug' => 'about', 'title' => 'Über uns', 'template' => 'wysiwyg', 'order' => 2],
            ['slug' => 'contact', 'title' => 'Kontakt', 'template' => 'wysiwyg', 'order' => 3],
        ];

        foreach ($pages as $order => $pageData) {
            $page = Page::create($pageData);
            $this->seedSections($page, $theme);

            // Navigation is editor-built from here on (see NavItem), but a
            // fresh tenant shouldn't start with an empty menu.
            NavItem::create([
                'type' => 'page',
                'page_id' => $page->id,
                'label' => $page->title,
                'order' => $order + 1,
            ]);
        }
    }

    /**
     * The page's own template's declared schema — see config/themes.php's
     * doc comment on `sections`. Must be the same source Admin\PageController
     * ::store() reads, so a page ends up with identical sections regardless
     * of which of the two ever provisions it.
     */
    private function seedSections(Page $page, string $theme): void
    {
        $schema = config("themes.{$theme}.templates.{$page->template}.sections", []);

        foreach ($schema as $section) {
            // See Admin\PageController::provisionSections()'s identical check.
            if ($section['type'] === 'posts_ref') {
                continue;
            }

            $page->sections()->create(['key' => $section['key'], 'value' => '']);
        }
    }

    private function seedSiteConfig(string $name): void
    {
        // Colors and logo are hard-defined per theme (resources/themes/<theme>/),
        // not editable per tenant — only genuinely per-client text belongs here.
        $config = [
            ['key' => 'site_name', 'label' => 'Name der Website', 'type' => 'text', 'value' => $name],
            ['key' => 'footer_text', 'label' => 'Fußzeilentext', 'type' => 'text', 'value' => ''],
            ['key' => 'contact_email', 'label' => 'Kontakt-E-Mail', 'type' => 'email', 'value' => ''],
        ];

        foreach ($config as $item) {
            SiteConfig::create($item);
        }
    }

    private function createEditorUser(string $email, string $editorName, string $password): void
    {
        foreach (['superadmin', 'editor'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }

        $user = User::create([
            'name' => $editorName,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'editor',
        ]);

        $user->assignRole('editor');
    }
}
