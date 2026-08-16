# Platform Build Plan — Laravel + Inertia + Svelte Multi-Tenant CMS

## Project Overview

A self-hosted, multi-tenant CMS platform for hosting simple websites for non-profit clients. One Laravel installation serves all client sites. Tenant isolation is handled at the database level — each tenant gets their own SQLite database. The admin UI is built with Svelte via Inertia.js. Public-facing sites are also rendered via Svelte/Inertia. Caddy handles HTTPS and domain routing.

@docs/DESIGN.md
@docs/THEMES.md

---

## Goals

- **Primary** — host multiple non-profit client sites from one server, one codebase
- **Secondary** — give clients a genuinely simple, non-technical editing experience
- **Tertiary** — keep maintenance effort as low as possible

---

## Stack

| Layer | Technology | Notes |
|---|---|---|
| Backend | Laravel 12 | PHP, handles routing, auth, data, Inertia responses |
| Multi-tenancy | stancl/tenancy | Domain-based tenant resolution, per-tenant SQLite DB |
| Frontend | Svelte 5 (Runes) via Inertia.js | Admin UI + public site rendering |
| Rich text | Tiptap | WYSIWYG editor for content sections |
| Media | spatie/laravel-medialibrary | File uploads, image handling |
| Auth | Laravel built-in + spatie/laravel-permission | Roles: superadmin, editor |
| Reverse proxy | Caddy | Automatic HTTPS, domain routing |
| Hosting | Single root server | Everything runs here |

---

## Architecture Overview

```
Internet
    │
    ▼
Caddy (reverse proxy, automatic HTTPS)
    │
    ├── ngo-example.org         ─┐
    ├── another-nonprofit.de    ─┤──► Laravel (port 8000)
    ├── third-client.org        ─┘         │
    │                                      ▼
    │                              stancl/tenancy resolves
    │                              tenant from Host header
    │                                      │
    │                              Switches DB connection to
    │                              /databases/{domain}.sqlite
    │                                      │
    │                              Controller runs, returns
    │                              Inertia response
    │                                      │
    │                              Svelte renders page
    │
    └── admin.yourplatform.com ──► Same Laravel app
                                   Central context (your DB)
                                   Superadmin interface
```

### Two Database Contexts

**Central database** (`central.sqlite`) — yours only:
- `tenants` table — registered client sites
- `domains` table — domain → tenant mapping

**Tenant databases** (`/databases/{domain}.sqlite`) — one per client:
- `users`, `pages`, `sections`, `site_config`, plus media library tables
- Completely isolated, one client can never access another's data

---

## Installation Steps

### 1. Create Laravel project

```bash
laravel new platform
cd platform
```

When prompted:
- No starter kit
- SQLite for database
- No additional testing framework needed beyond PHPUnit

### 2. Install PHP packages

```bash
composer require inertiajs/inertia-laravel
composer require stancl/tenancy
composer require spatie/laravel-permission
composer require spatie/laravel-medialibrary
composer require spatie/laravel-backup
composer require spatie/laravel-activitylog
composer require barryvdh/laravel-debugbar --dev
```

### 3. Install Node packages

```bash
npm install @inertiajs/svelte
npm install svelte @sveltejs/vite-plugin-svelte
npm install @tiptap/core @tiptap/starter-kit
npm install @tiptap/extension-image @tiptap/extension-link @tiptap/extension-placeholder
npm install --save-dev svelte-check typescript tslib
```

### 4. Publish vendor files

```bash
php artisan inertia:middleware
php artisan tenancy:install
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider"
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider"
```

### 5. Configure Vite

Replace `vite.config.js` entirely:

```js
import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import { svelte } from '@sveltejs/vite-plugin-svelte'

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        svelte(),
    ],
})
```

### 6. Configure Inertia client bootstrap

Replace `resources/js/app.js` entirely:

```js
import { createInertiaApp } from '@inertiajs/svelte'

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.svelte', { eager: true })

        return pages[`./Pages/${name}.svelte`]
    },
})
```

Note: with `@inertiajs/svelte` v3 + Svelte 5, omit `setup` — the adapter calls Svelte 5's
`mount()`/`hydrate()` itself. The old `setup({ el, App, props }) { new App({ ... }) }` form is
Svelte 4 syntax and throws on Svelte 5. CSS is loaded via `@vite` in the blade template, so it
does not need importing here.

### 7. Create Inertia root blade template

Create `resources/views/app.blade.php`:

```html
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>Platform</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
```

### 8. Register Inertia middleware

In `bootstrap/app.php`, add to the middleware stack:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \App\Http\Middleware\HandleInertiaRequests::class,
    ]);
})
```

---

## stancl/tenancy Configuration

### How it works

1. A request arrives for `ngo-example.org`
2. Tenancy middleware reads the `Host` header
3. Looks up `ngo-example.org` in the central `domains` table
4. Finds the associated tenant record
5. Switches the default database connection to that tenant's SQLite file
6. The rest of the request runs normally — models, queries, auth all use the tenant DB
7. Response returned, connection resets for the next request

### Register the service provider

`tenancy:install` creates `app/Providers/TenancyServiceProvider.php` but does **not** register it.
Add it to `bootstrap/providers.php` — without this, `routes/tenant.php` is never loaded.

### Configure `config/tenancy.php`

Key settings to change from defaults:

```php
'tenant_model' => \App\Models\Tenant::class,

'database' => [
    'central_connection' => env('DB_CONNECTION', 'sqlite'),
    'template_tenant_connection' => null, // null = clone the central connection

    // Tenant DB name = prefix + tenant_id + suffix.
    // SQLiteDatabaseManager resolves that through database_path(), so these must be
    // RELATIVE — passing an absolute database_path() here produces a broken doubled path.
    // Result: database/tenants/<uuid>.sqlite
    'prefix' => 'tenants/',
    'suffix' => '.sqlite',
],
```

The `database/tenants/` directory must exist — `SQLiteDatabaseManager::createDatabase()` uses
`file_put_contents()` and will not create it.

Tenant routes live in `routes/tenant.php` and already carry
`InitializeTenancyByDomain` + `PreventAccessFromCentralDomains` in their middleware group;
there is no `identification_middleware` config key in stancl/tenancy v3.

### Create the Tenant model

`app/Models/Tenant.php`:

```php
<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    public static function getCustomColumns(): array
    {
        return ['id', 'name'];
    }
}
```

### Central migrations (run on your DB)

These are created by `tenancy:install`. They create:
- `tenants` table — add a `name` string column here; it must mirror `Tenant::getCustomColumns()`,
  otherwise `name` silently falls into the `data` JSON blob
- `domains` table

### Tenant migrations

Live in `database/migrations/tenant/`. These run against each tenant's database when the tenant is created.

---

## Data Model

### Central database tables

#### tenants
| column | type | notes |
|---|---|---|
| id | string (uuid) | primary key |
| name | string | e.g. "Example NGO" |
| created_at | timestamp | |
| updated_at | timestamp | |

#### domains
| column | type | notes |
|---|---|---|
| id | bigint | primary key |
| tenant_id | string | → tenants.id |
| domain | string unique | e.g. "ngo-example.org" |
| created_at | timestamp | |
| updated_at | timestamp | |

---

### Tenant database tables

#### users
| column | type | notes |
|---|---|---|
| id | bigint | primary key |
| name | string | |
| email | string unique | |
| password | string | bcrypt hashed |
| role | string | 'superadmin' or 'editor' |
| remember_token | string | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

Note: roles are also managed via spatie/laravel-permission. The `role` column is a convenience — use the permission package for actual access control checks.

#### pages
| column | type | notes |
|---|---|---|
| id | bigint | primary key |
| slug | string unique | e.g. "home", "about", "contact" |
| title | string | e.g. "About Us" — shown in admin nav |
| template | string | e.g. "wysiwyg", "home_standard" — tells Svelte which layout component to use |
| published | boolean | default true |
| order | integer | for navigation ordering |
| created_at | timestamp | |
| updated_at | timestamp | |

#### sections
| column | type | notes |
|---|---|---|
| id | bigint | primary key |
| page_id | bigint | → pages.id |
| key | string | e.g. "hero_text", "body", "cta_label" — used in Svelte templates to reference content |
| label | string | e.g. "Hero Text" — what the client sees in the admin |
| type | string | enum: text, textarea, wysiwyg, image, email, url, color |
| value | longtext | the actual content |
| order | integer | display order within the page editor |
| created_at | timestamp | |
| updated_at | timestamp | |

Unique constraint: `(page_id, key)`

#### site_config
| column | type | notes |
|---|---|---|
| id | bigint | primary key |
| key | string unique | e.g. "site_name", "color_primary", "footer_text", "logo", "contact_email" |
| label | string | e.g. "Site Name" — shown in admin settings |
| type | string | enum: text, image, color, email, url |
| value | string | the config value |
| created_at | timestamp | |
| updated_at | timestamp | |

Media files are handled by spatie/laravel-medialibrary via polymorphic relationships — no manual media table needed.

---

## Migrations to Write

### Tenant migrations (`database/migrations/tenant/`)

```
2024_01_01_000000_create_sessions_table.php
2024_01_01_000001_create_users_table.php
2024_01_01_000002_create_pages_table.php
2024_01_01_000003_create_sections_table.php
2024_01_01_000004_create_site_config_table.php
2024_01_01_000005_create_permission_tables.php  (from spatie/laravel-permission)
2024_01_01_000006_create_media_table.php        (from spatie/laravel-medialibrary)
2024_01_01_000007_create_activity_log_table.php (from spatie/laravel-activitylog)
```

Sessions are per-tenant so a login on one client site grants nothing on another.

Cache and queue tables are **not** per-tenant — they stay in the central DB, pinned via
`DB_CACHE_CONNECTION` / `DB_CACHE_LOCK_CONNECTION` / `DB_QUEUE_CONNECTION` in `.env`. Without
those, `CACHE_STORE=database` resolves to the default connection, which becomes the tenant DB
once tenancy initializes — `create_permission_tables` flushes the permission cache and will
fail mid-migration, leaving half-created tables behind. Cache keys are still isolated per
tenant by `CacheTenancyBootstrapper`.

Publish the vendor migrations into `database/migrations/` and then move them into
`database/migrations/tenant/`, renaming to keep the ordering above.

---

## Models to Write

### `app/Models/Page.php`

```php
class Page extends Model
{
    protected $fillable = ['slug', 'title', 'template', 'published', 'order'];
    protected $casts = ['published' => 'boolean'];

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('order');
    }

    public function sectionsByKey(): Collection
    {
        return $this->sections->keyBy('key');
    }
}
```

### `app/Models/Section.php`

```php
class Section extends Model
{
    protected $fillable = ['page_id', 'key', 'label', 'type', 'value', 'order'];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
```

### `app/Models/SiteConfig.php`

```php
class SiteConfig extends Model
{
    protected $fillable = ['key', 'label', 'type', 'value'];

    public static function get(string $key, string $default = ''): string
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    public static function set(string $key, string $value): void
    {
        static::where('key', $key)->update(['value' => $value]);
    }

    public static function allAsMap(): array
    {
        return static::all()->pluck('value', 'key')->toArray();
    }
}
```

---

## Routing Structure

### `routes/web.php` — central routes (your superadmin)

```php
// Superadmin routes — central context, not tenant-aware
Route::middleware(['auth', 'role:superadmin'])->prefix('superadmin')->group(function () {
    Route::get('/', [SuperAdminController::class, 'index']);
    Route::get('/tenants', [SuperAdminController::class, 'tenants']);
    Route::post('/tenants', [SuperAdminController::class, 'createTenant']);
    Route::delete('/tenants/{tenant}', [SuperAdminController::class, 'deleteTenant']);
});
```

### `routes/tenant.php` — tenant routes (auto-resolved per domain)

```php
// Public site — no auth
Route::get('/', [Frontend\PageController::class, 'home']);
Route::get('/{slug}', [Frontend\PageController::class, 'show']);

// Admin — requires auth + editor role
Route::middleware(['auth', 'role:editor|superadmin'])->prefix('admin')->group(function () {
    Route::get('/', [AdminController::class, 'index']);

    // Pages
    Route::get('/pages', [PageController::class, 'index']);
    Route::get('/pages/{page}', [PageController::class, 'edit']);
    Route::patch('/pages/{page}/sections/{section}', [SectionController::class, 'update']);

    // Settings
    Route::get('/settings', [SettingsController::class, 'index']);
    Route::patch('/settings', [SettingsController::class, 'update']);

    // Media
    Route::post('/media', [MediaController::class, 'store']);
    Route::delete('/media/{media}', [MediaController::class, 'destroy']);
});

// Auth routes (tenant-specific users)
Route::get('/admin/login', [AuthController::class, 'showLogin']);
Route::post('/admin/login', [AuthController::class, 'login']);
Route::post('/admin/logout', [AuthController::class, 'logout']);
```

---

## Controllers to Write

### `Frontend\PageController`

Controllers are namespaced by context: `App\Http\Controllers\Admin\*` and
`App\Http\Controllers\Frontend\*`.

Serves the public-facing site. Reads pages and sections from the tenant DB, returns Inertia response.

```php
public function home()
{
    $page = Page::where('slug', 'home')->where('published', true)->firstOrFail();
    $config = SiteConfig::allAsMap();

    return Inertia::render('Frontend/Page', [
        'page' => $page,
        'sections' => $page->sectionsByKey(),
        'config' => $config,
    ]);
}

public function show(string $slug)
{
    $page = Page::where('slug', $slug)->where('published', true)->firstOrFail();
    $config = SiteConfig::allAsMap();

    return Inertia::render('Frontend/Page', [
        'page' => $page,
        'sections' => $page->sectionsByKey(),
        'config' => $config,
    ]);
}
```

### `Admin\PageController`

Admin page management.

```php
public function index()
{
    return Inertia::render('Admin/Pages/Index', [
        'pages' => Page::orderBy('order')->get(['id', 'slug', 'title', 'published']),
    ]);
}

public function edit(Page $page)
{
    return Inertia::render('Admin/Pages/Edit', [
        'page' => $page,
        'sections' => $page->sections()->orderBy('order')->get(),
    ]);
}
```

### `SectionController`

```php
public function update(Request $request, Page $page, Section $section)
{
    $validated = $request->validate(['value' => 'nullable|string']);
    $section->update($validated);
    return back();
}
```

### `SettingsController`

```php
public function index()
{
    return Inertia::render('Admin/Settings', [
        'config' => SiteConfig::orderBy('id')->get(),
    ]);
}

public function update(Request $request)
{
    foreach ($request->validated()['settings'] as $key => $value) {
        SiteConfig::set($key, $value);
    }
    return back();
}
```

### `MediaController`

```php
public function store(Request $request)
{
    $request->validate(['file' => 'required|file|image|max:10240']);
    
    // Associate with a section or site_config via polymorphic media
    $section = Section::findOrFail($request->section_id);
    $media = $section->addMediaFromRequest('file')->toMediaCollection('content');

    return response()->json(['url' => $media->getUrl()]);
}
```

---

## Svelte Page Structure

```
resources/js/
├── app.js                          ← Inertia bootstrap
├── Pages/
│   ├── Frontend/
│   │   └── Page.svelte             ← Public site renderer, switches on page.template
│   ├── Admin/
│   │   ├── Layout.svelte           ← Admin shell (sidebar, nav, logout)
│   │   ├── Dashboard.svelte        ← Admin home after login
│   │   ├── Pages/
│   │   │   ├── Index.svelte        ← List of pages
│   │   │   └── Edit.svelte         ← Page editor (sections list)
│   │   └── Settings.svelte         ← Site config editor
│   └── Auth/
│       └── Login.svelte            ← Login form
├── Components/
│   ├── Admin/
│   │   ├── SectionField.svelte     ← Renders correct input per section.type
│   │   ├── TiptapEditor.svelte     ← Tiptap WYSIWYG wrapper
│   │   ├── ImageUpload.svelte      ← Image upload + preview
│   │   └── ConfigField.svelte      ← Renders correct input per config.type
│   └── Frontend/
│       ├── templates/
│       │   ├── Wysiwyg.svelte      ← Default single-content template
│       │   ├── HomeStandard.svelte ← Standard home page layout
│       │   └── HomeGallery.svelte  ← Home with gallery, etc.
│       ├── SiteHeader.svelte
│       └── SiteFooter.svelte
```

---

## Key Svelte Components

### `Frontend/Page.svelte`

Switches on `page.template` to pick the right layout component. This is the only public page component needed — templates handle the visual differences.

```svelte
<script>
  import Wysiwyg from '../Components/Frontend/templates/Wysiwyg.svelte'
  import HomeStandard from '../Components/Frontend/templates/HomeStandard.svelte'

  const templates = {
    wysiwyg: Wysiwyg,
    home_standard: HomeStandard,
  }

  let { page, sections, config } = $props()
  const Template = $derived(templates[page.template] ?? Wysiwyg)
</script>

<!-- Svelte 5: render the component variable directly; <svelte:component> is deprecated -->
<Template {page} {sections} {config} />
```

### `Admin/Pages/Edit.svelte`

The core client-facing editor. Loads whatever sections exist for the page and renders the appropriate input per type. The client never sees section keys or types — only labels.

```svelte
<script>
  import SectionField from '../../Components/Admin/SectionField.svelte'
  
  let { page, sections } = $props()
</script>

<h1>{page.title}</h1>

{#each sections as section (section.id)}
  <SectionField {section} />
{/each}
```

### `Admin/Components/SectionField.svelte`

Renders the correct input type per section. This is the key abstraction — adding a new section type means adding a case here.

```svelte
<script>
  import TiptapEditor from './TiptapEditor.svelte'
  import ImageUpload from './ImageUpload.svelte'
  import { router } from '@inertiajs/svelte'

  let { section } = $props()
  let value = $state(section.value)
  let saving = $state(false)
  let saved = $state(false)

  function save() {
    saving = true
    router.patch(`/admin/pages/${section.page_id}/sections/${section.id}`, 
      { value },
      { 
        preserveScroll: true,
        onSuccess: () => { saved = true; setTimeout(() => saved = false, 2000) },
        onFinish: () => saving = false
      }
    )
  }
</script>

<div class="section-field">
  <div class="field-header">
    <label>{section.label}</label>
    {#if saved}<span class="badge-saved">Saved</span>{/if}
  </div>

  {#if section.type === 'wysiwyg'}
    <TiptapEditor bind:value />
  {:else if section.type === 'image'}
    <ImageUpload bind:value sectionId={section.id} />
  {:else if section.type === 'textarea'}
    <textarea bind:value rows="5"></textarea>
  {:else if section.type === 'email'}
    <input type="email" bind:value />
  {:else if section.type === 'url'}
    <input type="url" bind:value />
  {:else}
    <input type="text" bind:value />
  {/if}

  <button onclick={save} disabled={saving}>
    {saving ? 'Saving…' : 'Save'}
  </button>
</div>
```

---

## Tenant Onboarding Command

Create an Artisan command `php artisan tenant:setup` that:

1. Creates a tenant record in the central DB
2. Creates the domain record
3. Runs tenant migrations (stancl/tenancy does this automatically on tenant creation)
4. Seeds standard pages and sections
5. Seeds default site_config keys
6. Creates the editor user account

### `app/Console/Commands/SetupTenant.php`

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use Illuminate\Support\Facades\Hash;

class SetupTenant extends Command
{
    protected $signature = 'tenant:setup 
        {domain : The client domain e.g. ngo-example.org}
        {--name= : Display name for the tenant}
        {--email= : Admin email for the client}
        {--template=default : Which page template set to seed}';

    protected $description = 'Create and configure a new tenant';

    public function handle()
    {
        $domain = $this->argument('domain');
        $name = $this->option('name') ?? $domain;
        $email = $this->option('email') ?? 'admin@' . $domain;
        $template = $this->option('template');

        $this->info("Creating tenant: {$name} ({$domain})");

        // Create tenant — stancl/tenancy runs migrations automatically
        $tenant = Tenant::create(['name' => $name]);
        $tenant->domains()->create(['domain' => $domain]);

        // Run seeding inside tenant context
        tenancy()->initialize($tenant);

        $this->seedPages($template);
        $this->seedSiteConfig($name);
        $this->createEditorUser($email);

        tenancy()->end();

        $password = 'changeme123'; // Output this to the console
        $this->info("Done. Login: {$email} / {$password}");
        $this->warn("Tell the client to change their password immediately.");
    }

    private function seedPages(string $template): void
    {
        $pages = [
            ['slug' => 'home',    'title' => 'Home',    'template' => "home_{$template}", 'order' => 1],
            ['slug' => 'about',   'title' => 'About',   'template' => 'wysiwyg',          'order' => 2],
            ['slug' => 'contact', 'title' => 'Contact', 'template' => 'wysiwyg',          'order' => 3],
        ];

        foreach ($pages as $pageData) {
            $page = \App\Models\Page::create($pageData);
            $this->seedSections($page);
        }
    }

    private function seedSections(\App\Models\Page $page): void
    {
        if ($page->slug === 'home') {
            $sections = [
                ['key' => 'hero_text',    'label' => 'Hero Headline',   'type' => 'text',     'order' => 1],
                ['key' => 'hero_subtext', 'label' => 'Hero Subtext',    'type' => 'text',     'order' => 2],
                ['key' => 'hero_image',   'label' => 'Hero Image',      'type' => 'image',    'order' => 3],
                ['key' => 'intro_title',  'label' => 'Intro Title',     'type' => 'text',     'order' => 4],
                ['key' => 'intro_body',   'label' => 'Intro Text',      'type' => 'textarea', 'order' => 5],
            ];
        } else {
            $sections = [
                ['key' => 'body', 'label' => 'Page Content', 'type' => 'wysiwyg', 'order' => 1],
            ];
        }

        foreach ($sections as $section) {
            $page->sections()->create(array_merge($section, ['value' => '']));
        }
    }

    private function seedSiteConfig(string $name): void
    {
        $config = [
            ['key' => 'site_name',     'label' => 'Site Name',      'type' => 'text',  'value' => $name],
            ['key' => 'color_primary', 'label' => 'Primary Colour', 'type' => 'color', 'value' => '#2563eb'],
            ['key' => 'color_accent',  'label' => 'Accent Colour',  'type' => 'color', 'value' => '#f59e0b'],
            ['key' => 'logo',          'label' => 'Logo',           'type' => 'image', 'value' => ''],
            ['key' => 'footer_text',   'label' => 'Footer Text',    'type' => 'text',  'value' => ''],
            ['key' => 'contact_email', 'label' => 'Contact Email',  'type' => 'email', 'value' => ''],
        ];

        foreach ($config as $item) {
            \App\Models\SiteConfig::create($item);
        }
    }

    private function createEditorUser(string $email): void
    {
        $user = \App\Models\User::create([
            'name'     => 'Site Editor',
            'email'    => $email,
            'password' => Hash::make('changeme123'),
        ]);

        $user->assignRole('editor');
    }
}
```

---

## Caddy Configuration

```
# Each client domain — Caddy handles HTTPS automatically via Let's Encrypt
ngo-example.org {
    reverse_proxy localhost:8000
}

another-nonprofit.de {
    reverse_proxy localhost:8000
}

# Add new client:
# 1. Add two lines above
# 2. Run: caddy reload
```

---

## Adding a New Client — Full Workflow

```bash
# 1. Register domain at registrar, point DNS A record to your server IP

# 2. Add domain to Caddyfile (two lines), then:
caddy reload

# 3. Run setup command
php artisan tenant:setup ngo-example.org \
  --name="Example NGO" \
  --email="contact@ngo-example.org" \
  --template=default

# 4. Hand over credentials to client
# Login URL: https://ngo-example.org/admin/login
# Email: contact@ngo-example.org
# Password: changeme123 (tell them to change it)

# Done. Total time: ~5 minutes.
```

---

## Backup Strategy

```bash
# Backup all tenant databases + central DB
# Configured via spatie/laravel-backup

# config/backup.php — include all SQLite files
'source' => [
    'databases' => ['sqlite'],
    'files' => [
        'include' => [
            database_path(),        // central.sqlite
            database_path('tenants'), // all tenant .sqlite files
            storage_path('app'),    // uploaded media
        ],
    ],
],

# Schedule in app/Console/Kernel.php
$schedule->command('backup:run')->daily()->at('02:00');
$schedule->command('backup:clean')->daily()->at('01:00');
```

---

## Development Workflow

```bash
# Start Laravel dev server
php artisan serve

# Start Vite (Svelte compilation + HMR)
npm run dev

# Run tenant migrations after changes
php artisan tenancy:migrate

# Create a test tenant locally
php artisan tenant:setup localhost --name="Test Client" --email="test@localhost"
```

---

## Security Considerations

- All admin routes are behind `auth` + role middleware
- Tenant isolation is at DB level — one tenant cannot query another's data
- Inertia CSRF protection is handled automatically
- Media uploads validated for file type and size in `MediaController`
- Passwords hashed with bcrypt via Laravel's `Hash` facade
- HTTPS enforced by Caddy for all domains
- `APP_ENV=production` and `APP_DEBUG=false` in production `.env`
- SQLite files stored outside the public web root

---

## Environment Variables (`.env`)

```env
APP_NAME=Platform
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourplatform.com

DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database/central.sqlite

TENANCY_DATABASE_AUTO_DELETE=false

# Mail (for password resets)
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=

# Backup destination
BACKUP_DISK=local
```

---

## What Claude Code Should Build — In Order

1. **Laravel project setup** — install all composer and npm packages, configure vite, bootstrap Inertia with Svelte adapter, create root blade template

2. **Tenancy configuration** — configure `config/tenancy.php`, create Tenant model, verify central migrations

3. **Tenant migrations** — write all tenant-side migration files in `database/migrations/tenant/`

4. **Models** — `Page`, `Section`, `SiteConfig`, update `User` model for tenancy compatibility, add HasRoles from spatie/permission

5. **Routes** — `routes/web.php` for central/superadmin, `routes/tenant.php` for tenant routes

6. **Controllers** — `Frontend\PageController`, `Admin\PageController`, `Admin\SectionController`, `Admin\SettingsController`, `Admin\MediaController`, `Admin\AuthController`

7. **Artisan command** — `SetupTenant` command

8. **Svelte pages** — in order: `Auth/Login`, `Admin/Layout`, `Admin/Pages/Index`, `Admin/Pages/Edit`, `Admin/Settings`, `Frontend/Page`

9. **Svelte components** — `SectionField`, `TiptapEditor`, `ImageUpload`, `ConfigField`

10. **Frontend templates** — `Wysiwyg`, `HomeStandard` (at minimum)

11. **Caddy config** — basic Caddyfile

12. **Test** — run `php artisan tenant:setup` with a test domain and verify the full flow: public page renders, admin login works, sections save correctly
