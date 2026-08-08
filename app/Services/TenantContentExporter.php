<?php

namespace App\Services;

use App\Models\NavItem;
use App\Models\Page;
use App\Models\SiteConfig;
use App\Models\Tenant;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use ZipArchive;

/**
 * Dumps a tenant's content — pages, sections, posts, nav items, site config,
 * and the media files they reference — into a single portable .zip. Pairs
 * with TenantContentImporter, which loads one of these archives back into a
 * (possibly different) tenant.
 *
 * Deliberately excludes users, sessions, permissions, and activity_log —
 * those are per-environment, not part of "the site's content".
 *
 * Archive layout:
 *   manifest.json        — see buildManifest() for the exact shape
 *   media/{export_id}_{file_name} — one copy per media item, original file only
 *                                    (conversions are cheap to regenerate on
 *                                    import and would otherwise double the
 *                                    archive for no benefit)
 *
 * Pages/sections/posts/media are each assigned a sequential "export_id" —
 * a plain incrementing integer, unrelated to their real database id — so the
 * importer can rebuild internal references (nav_items -> pages, embedded
 * media URLs) without caring what the new database ids turn out to be.
 */
class TenantContentExporter
{
    private const SCHEMA_VERSION = 1;

    /**
     * @return array{pages: int, sections: int, posts: int, nav_items: int, media: int}
     */
    public function export(Tenant $tenant, string $destinationZipPath): array
    {
        tenancy()->initialize($tenant);

        $workDir = sys_get_temp_dir().'/tenant-export-'.Str::uuid();
        File::ensureDirectoryExists($workDir.'/media');

        try {
            [$manifest, $counts] = $this->buildManifest($tenant, $workDir);

            File::put(
                $workDir.'/manifest.json',
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            );

            $this->zip($workDir, $destinationZipPath);

            return $counts;
        } finally {
            tenancy()->end();
            File::deleteDirectory($workDir);
        }
    }

    private function buildManifest(Tenant $tenant, string $workDir): array
    {
        $counts = ['pages' => 0, 'sections' => 0, 'posts' => 0, 'nav_items' => 0, 'media' => 0];
        $mediaExportId = 0;
        $pageExportIdByRealId = [];

        $pages = [];

        foreach (Page::orderBy('order')->get() as $page) {
            $pageExportId = count($pages) + 1;
            $pageExportIdByRealId[$page->id] = $pageExportId;
            $counts['pages']++;

            $sections = [];

            // label/type/order are computed (see App\Models\Section), not
            // DB columns, so they're sorted in PHP rather than via orderBy().
            foreach ($page->sections()->get()->sortBy('order') as $section) {
                $counts['sections']++;

                $posts = [];

                foreach ($section->posts()->orderBy('order')->get() as $post) {
                    $counts['posts']++;

                    $posts[] = [
                        'title' => $post->title,
                        'excerpt' => $post->excerpt,
                        'starts_at' => $post->starts_at?->toIso8601String(),
                        'body' => $post->body,
                        'image' => $post->image,
                        'order' => $post->order,
                        'media' => $this->exportMedia($post, $workDir, $mediaExportId, $counts),
                    ];
                }

                $sections[] = [
                    'key' => $section->key,
                    'label' => $section->label,
                    'type' => $section->type,
                    'value' => $section->value,
                    'alt' => $section->alt,
                    'order' => $section->order,
                    'media' => $this->exportMedia($section, $workDir, $mediaExportId, $counts),
                    'posts' => $posts,
                ];
            }

            $pages[] = [
                'export_id' => $pageExportId,
                'slug' => $page->slug,
                'title' => $page->title,
                'template' => $page->template,
                'published' => $page->published,
                'order' => $page->order,
                'sections' => $sections,
            ];
        }

        $navItems = [];

        foreach (NavItem::orderBy('order')->get() as $nav) {
            $counts['nav_items']++;

            $navItems[] = [
                'type' => $nav->type,
                'page_export_id' => $nav->page_id ? ($pageExportIdByRealId[$nav->page_id] ?? null) : null,
                'label' => $nav->label,
                'url' => $nav->url,
                'order' => $nav->order,
            ];
        }

        $siteConfig = SiteConfig::orderBy('id')->get()->map(fn (SiteConfig $c) => [
            'key' => $c->key,
            'label' => $c->label,
            'type' => $c->type,
            'value' => $c->value,
        ])->all();

        $manifest = [
            'schema_version' => self::SCHEMA_VERSION,
            'exported_at' => now()->toIso8601String(),
            'tenant_name' => $tenant->name,
            'pages' => $pages,
            'nav_items' => $navItems,
            'site_config' => $siteConfig,
        ];

        return [$manifest, $counts];
    }

    /**
     * Copies the original file of each of $model's media items into the
     * archive's media/ directory and returns their manifest entries.
     * 'source_id' is the real (pre-export) media id — it's what's literally
     * embedded in section.value / post.body / post.image as
     * "/media/{id}/{filename}", which is how the importer knows what to
     * rewrite those references to once the media exists again with a new id.
     */
    private function exportMedia(HasMedia $model, string $workDir, int &$mediaExportId, array &$counts): array
    {
        $entries = [];

        foreach ($model->media()->orderBy('order_column')->get() as $media) {
            $mediaExportId++;
            $counts['media']++;

            $archiveFile = "{$mediaExportId}_{$media->file_name}";
            File::copy($media->getPath(), $workDir.'/media/'.$archiveFile);

            $entries[] = [
                'source_id' => $media->id,
                'collection_name' => $media->collection_name,
                'file_name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'archive_file' => $archiveFile,
            ];
        }

        return $entries;
    }

    private function zip(string $workDir, string $destinationZipPath): void
    {
        File::ensureDirectoryExists(dirname($destinationZipPath));

        if (File::exists($destinationZipPath)) {
            File::delete($destinationZipPath);
        }

        $zip = new ZipArchive;
        $zip->open($destinationZipPath, ZipArchive::CREATE);

        foreach (File::allFiles($workDir) as $file) {
            $zip->addFile($file->getPathname(), $file->getRelativePathname());
        }

        $zip->close();
    }
}
