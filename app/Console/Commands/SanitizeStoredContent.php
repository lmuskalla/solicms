<?php

namespace App\Console\Commands;

use App\Models\Page;
use App\Models\Tenant;
use App\Services\WysiwygSanitizer;
use Illuminate\Console\Command;

/**
 * One-off backfill for docs/tasks/2026-08-08_security-review-findings.md
 * TASK-4: every 'wysiwyg'-type Section.value and every Post.body saved
 * before the sanitizer existed (Admin\SectionController::update() /
 * Admin\PostController::update() — see App\Services\WysiwygSanitizer) may
 * already carry unsanitized HTML from a benign editor, or worse.
 *
 * High risk — mutates real, already-live content across every tenant's
 * database in one pass. Always run with --dry-run first, and only run for
 * real against a confirmed backup (spatie/laravel-backup). Don't run this
 * opportunistically alongside unrelated feature/SEO work.
 */
class SanitizeStoredContent extends Command
{
    protected $signature = 'content:sanitize
        {--dry-run : Report what would change without saving anything}
        {--tenant= : Only run against a single tenant, by id}';

    protected $description = 'Sanitize already-persisted wysiwyg Section values and Post bodies across tenant(s)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $tenantId = $this->option('tenant');

        $tenants = $tenantId
            ? Tenant::where('id', $tenantId)->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->error('No matching tenant(s) found.');

            return self::FAILURE;
        }

        if (! $dryRun) {
            $this->warn('Running for real — this will overwrite Section.value and Post.body wherever sanitization changes them.');
            $this->warn('Make sure a confirmed backup exists before continuing (see spatie/laravel-backup).');

            if (! $this->confirm('Continue?')) {
                return self::SUCCESS;
            }
        }

        $totals = ['sections' => 0, 'posts' => 0];

        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);

            try {
                [$sections, $posts] = $this->sanitizeTenant($tenant, $dryRun);
                $totals['sections'] += $sections;
                $totals['posts'] += $posts;
            } finally {
                tenancy()->end();
            }
        }

        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info("{$prefix}Done. Sections changed: {$totals['sections']}, Posts changed: {$totals['posts']}.");

        return self::SUCCESS;
    }

    /**
     * @return array{0: int, 1: int} [sections changed, posts changed]
     */
    private function sanitizeTenant(Tenant $tenant, bool $dryRun): array
    {
        $sectionsChanged = 0;
        $postsChanged = 0;

        foreach (Page::with('sections.posts')->get() as $page) {
            foreach ($page->sections as $section) {
                if ($section->type === 'wysiwyg' && filled($section->value)) {
                    $clean = WysiwygSanitizer::clean($section->value);

                    if ($clean !== $section->value) {
                        $sectionsChanged++;
                        $this->line("[{$tenant->name}] Section #{$section->id} ({$page->slug}.{$section->key}) would change.");

                        if (! $dryRun) {
                            $section->update(['value' => $clean]);
                        }
                    }
                }

                foreach ($section->posts as $post) {
                    if (filled($post->body)) {
                        $clean = WysiwygSanitizer::clean($post->body);

                        if ($clean !== $post->body) {
                            $postsChanged++;
                            $this->line("[{$tenant->name}] Post #{$post->id} ({$post->title}) body would change.");

                            if (! $dryRun) {
                                $post->update(['body' => $clean]);
                            }
                        }
                    }
                }
            }
        }

        return [$sectionsChanged, $postsChanged];
    }
}
