<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Frontend\Concerns\ResolvesThemeComponent;
use App\Models\NavItem;
use App\Models\Page;
use App\Models\Section;
use App\Models\SiteConfig;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    use ResolvesThemeComponent;

    public function home(): Response
    {
        return $this->render('home');
    }

    public function show(string $slug): Response
    {
        return $this->render($slug);
    }

    private function render(string $slug): Response
    {
        $page = Page::where('slug', $slug)->where('published', true)->firstOrFail();
        // Cheap even when no section is type 'posts' — see the identical
        // comment on Admin\PageController::edit().
        $page->load('sections.posts');

        // Editor-built, not auto-derived from the page list — see
        // App\Models\NavItem. A page can exist without being in any menu.
        // Split per menu ('header'/'footer') — values() re-indexes after the
        // collection filter drops keys.
        $navItems = NavItem::with('page:id,slug')->orderBy('order')->get();

        return Inertia::render('Frontend/Page', [
            'page' => $page,
            'sections' => $this->withPostsRefs($page),
            'config' => SiteConfig::allAsMap(),
            'nav' => $navItems->where('menu', 'header')->values()
                ->map(fn (NavItem $item) => ['label' => $item->label, 'href' => $item->href()]),
            'footerNav' => $navItems->where('menu', 'footer')->values()
                ->map(fn (NavItem $item) => ['label' => $item->label, 'href' => $item->href()]),
            // resources/themes/<theme>/<component>.svelte, resolved from
            // config/themes.php. Page.svelte loads whichever file this points
            // to via import.meta.glob — it never needs updating when a theme
            // or template is added.
            'themeComponent' => $this->themeComponent($page->template),
            // Used for og:url and <link rel="canonical"> (Components/Frontend
            // /SeoHead.svelte) — always the tenant's primary domain, so a
            // tenant with more than one domain attached (see
            // TenantProvisioner::provision()'s $domains) doesn't get indexed
            // as duplicate content under each one. See
            // docs/tasks/2026-08-08_seo-findings.md TASK-11.
            'url' => $this->canonicalUrl(),
        ]);
    }

    /**
     * The current request's URL, rewritten onto this tenant's primary domain
     * — defined here, pragmatically, as whichever of the tenant's domains
     * was registered first (lowest id), matching the order
     * TenantProvisioner::provision() creates them in (an operator lists the
     * real/production domain first, aliases after). Not a modeled "primary"
     * flag — see docs/tasks/2026-08-08_seo-findings.md TASK-11's doc comment
     * on why that's a reasonable, zero-migration starting point. Falls back
     * to the domain actually being visited if, somehow, a tenant has none.
     */
    private function canonicalUrl(): string
    {
        $primaryDomain = tenant()->domains()->orderBy('id')->first()?->domain ?? request()->getHost();

        return request()->getScheme().'://'.$primaryDomain.request()->getRequestUri();
    }

    /**
     * A 'posts_ref' schema entry has no Section row of its own (see
     * Admin\PageController::provisionSections()) — its content is another
     * section's `posts`, sliced to `limit`. `source` is assumed unique
     * tenant-wide, not just on this page — see THEMES.md §9 before adding a
     * second one. Resolved here rather than cached anywhere: it's one extra
     * indexed query per posts_ref section, on a page render that's already
     * doing several.
     */
    private function withPostsRefs(Page $page): Collection
    {
        $sections = $page->sectionsByKey();
        $theme = tenant('theme') ?? 'default';
        $schema = config("themes.{$theme}.templates.{$page->template}.sections", []);

        foreach ($schema as $def) {
            if ($def['type'] !== 'posts_ref') {
                continue;
            }

            $source = Section::where('key', $def['source'])->with('posts')->first();

            $sections->put($def['key'], [
                'key' => $def['key'],
                'label' => $def['label'],
                'type' => $source?->type,
                'value' => null,
                'posts' => $source?->posts->take($def['limit']) ?? [],
            ]);
        }

        return $sections;
    }
}
