<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Section;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Pages/Index', [
            'pages' => Page::orderBy('order')->get(['id', 'slug', 'title', 'published']),
            'availableTemplates' => array_map(
                fn (array $template) => $template['label'],
                $this->themeTemplates(),
            ),
        ]);
    }

    public function edit(Page $page): Response
    {
        // Self-heals on every visit, not just on create/template-switch —
        // otherwise a key added to the schema after this page was
        // provisioned wouldn't show up until one of those ran again.
        $this->provisionSections($page, $page->template);

        $schema = $this->themeTemplates()[$page->template]['sections'] ?? [];
        // 'posts' relation is empty for every non-'posts' section type, so
        // eager-loading it unconditionally is cheap and keeps this query
        // generic rather than branching per section type.
        $byKey = $page->sections()->with('posts')->get()->keyBy('key');

        return Inertia::render('Admin/Pages/Edit', [
            'page' => $page,
            // Built from the template's current schema, not the raw rows —
            // a key the schema no longer declares (renamed/removed from the
            // theme) simply isn't shown here, though its row and value are
            // left untouched; see App\Models\Section's doc comment.
            'sections' => collect($schema)
                ->map(fn (array $def) => $def['type'] === 'posts_ref'
                    ? $this->postsRefCard($def)
                    : $byKey->get($def['key']))
                ->filter()
                ->values(),
            'availableTemplates' => array_map(
                fn (array $template) => $template['label'],
                $this->themeTemplates(),
            ),
        ]);
    }

    /**
     * A 'posts_ref' section is still fully editable here — just not as its
     * own copy. Points straight at the real, source section's id and full
     * `posts` list (not sliced to `limit`; that cap only applies to the
     * public frontend, editing should see and manage everything), so
     * PostsManager writes through to the exact same rows an editor would
     * reach from the source section's own page — one "News", edited once,
     * regardless of which page's editor screen it's opened from.
     */
    private function postsRefCard(array $def): ?array
    {
        $source = Section::where('key', $def['source'])->with(['posts', 'page'])->first();

        if (! $source) {
            return null;
        }

        return [
            'id' => $source->id,
            'key' => $def['key'],
            'label' => $def['label'],
            // The source's own real type ('posts' or 'events'), not a
            // separate 'posts_ref' type — the editor needs the exact same
            // date-field behavior here as on the source section's own page.
            'type' => $source->type,
            'value' => null,
            'posts' => $source->posts,
            'note' => "Diese Einträge gehören eigentlich zu „{$source->page->title}\". Auf dieser Seite werden nur die neuesten {$def['limit']} angezeigt — Bearbeiten hier wirkt sich überall aus.",
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255',
                'alpha_dash', // ASCII letters, numbers, dashes, underscores — keeps URLs clean
                Rule::unique('pages', 'slug'),
            ],
            'template' => ['required', Rule::in(array_keys($this->themeTemplates()))],
        ]);

        $page = Page::create([
            ...$validated,
            'order' => (Page::max('order') ?? 0) + 1,
            // Defaults new, editor-created pages to draft rather than the
            // `published` column's own DB-level default of true — a page
            // freshly created here starts with blank sections (see
            // provisionSections() below) and would otherwise go instantly
            // live/indexable at /{slug} with no content. The editor
            // publishes explicitly once it's ready (see Pages/Edit.svelte).
            // Doesn't affect TenantProvisioner's seeded starter pages, which
            // still rely on the column default and are published
            // immediately. See docs/tasks/2026-08-08_feature-improvements.md
            // TASK-3.
            'published' => false,
        ]);

        // The chosen template's declared schema, not a hardcoded guess — see
        // config/themes.php's doc comment on `sections`. Whatever a fresh
        // page ends up with must be identical to what any other page using
        // this same template has, regardless of which of the two creation
        // paths (this, or TenantProvisioner) made it.
        $this->provisionSections($page, $validated['template']);

        return redirect()->route('admin.pages.edit', $page);
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'template' => ['sometimes', 'required', Rule::in(array_keys($this->themeTemplates()))],
            'published' => ['sometimes', 'boolean'],
        ]);

        $page->update($validated);

        if (! array_key_exists('template', $validated)) {
            return back();
        }

        // Switching template can only ever add sections the new template
        // declares and this page doesn't have yet — never removes existing
        // ones, since a section can carry real editor-written content/media
        // that a template switch shouldn't silently destroy. This can leave
        // a page with sections its current template doesn't use if it's
        // switched more than once; that's a smaller, visible problem (an
        // unused field in the editor) rather than silent data loss.
        $this->provisionSections($page, $validated['template']);

        return back();
    }

    /**
     * Creates whichever of the template's declared sections this page
     * doesn't already have (matched by key) — idempotent, so it's safe to
     * call on both creation and every subsequent template change. A
     * 'posts_ref' entry never gets a row at all — it has no value of its
     * own, only a `source` key and `limit` declared in theme.php, resolved
     * live by Frontend\PageController — so there's nothing for the editor
     * to fill in and nothing here to provision.
     */
    private function provisionSections(Page $page, string $template): void
    {
        $existingKeys = $page->sections()->pluck('key')->all();
        $schema = $this->themeTemplates()[$template]['sections'] ?? [];

        foreach ($schema as $section) {
            if ($section['type'] === 'posts_ref' || in_array($section['key'], $existingKeys, true)) {
                continue;
            }

            $page->sections()->create(['key' => $section['key'], 'value' => '']);
        }
    }

    public function destroy(Page $page): RedirectResponse
    {
        // Deleted one by one (not a bulk query) so each Section's own
        // `deleting` hook fires and its uploaded media file is actually
        // removed from disk — a raw DB cascade wouldn't trigger that.
        $page->sections->each->delete();
        $page->delete();

        return redirect()->route('admin.pages.index')->with('success', 'Seite gelöscht.');
    }

    /**
     * template key => ['label' => ..., 'component' => ...], for whichever
     * theme this tenant is assigned in config/themes.php. Falls back to
     * 'default' for tenants that predate the theme column.
     */
    private function themeTemplates(): array
    {
        $theme = tenant('theme') ?? 'default';

        return config("themes.{$theme}.templates", config('themes.default.templates'));
    }
}
