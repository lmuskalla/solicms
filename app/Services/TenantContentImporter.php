<?php

namespace App\Services;

use App\Models\NavItem;
use App\Models\Page;
use App\Models\Post;
use App\Models\Section;
use App\Models\SiteConfig;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\MediaLibrary\HasMedia;
use ZipArchive;

/**
 * Loads a TenantContentExporter archive into a tenant, replacing its
 * existing content entirely — this is a restore, not a merge. Existing
 * pages/sections/posts/nav_items/media are deleted (individually, so
 * spatie/medialibrary's delete hooks actually remove the old files — see
 * wipe()) and the archive's content is recreated with fresh ids.
 *
 * Because ids are regenerated, any URL baked into section.value / post.body
 * / post.image that points at a media file (e.g. "/media/17/photo.jpg") is
 * rewritten to the file's new id after import — see rewriteMediaReferences().
 * Post slugs are likewise NOT restored verbatim: Post::syncSlug() derives
 * them from the title plus the (new) id, matching how the app creates them
 * everywhere else. A post's public URL can therefore change on import.
 */
class TenantContentImporter
{
    private const SUPPORTED_SCHEMA_VERSION = 1;

    /**
     * Reads an archive's manifest without touching any tenant — used to show
     * the operator what they're about to load before they confirm.
     *
     * @return array{tenant_name: ?string, exported_at: ?string, pages: int, sections: int, posts: int, nav_items: int, media: int}
     */
    public function inspect(string $archivePath): array
    {
        $extractDir = $this->extract($archivePath);

        try {
            $manifest = $this->readManifest($extractDir);

            $sections = collect($manifest['pages'])->flatMap(fn (array $p) => $p['sections']);

            return [
                'tenant_name' => $manifest['tenant_name'] ?? null,
                'exported_at' => $manifest['exported_at'] ?? null,
                'pages' => count($manifest['pages']),
                'sections' => $sections->count(),
                'posts' => $sections->sum(fn (array $s) => count($s['posts'])),
                'nav_items' => count($manifest['nav_items']),
                'media' => $sections->sum(fn (array $s) => count($s['media']))
                    + $sections->flatMap(fn (array $s) => $s['posts'])->sum(fn (array $p) => count($p['media'])),
            ];
        } finally {
            File::deleteDirectory($extractDir);
        }
    }

    /**
     * @return array{pages: int, sections: int, posts: int, nav_items: int, media: int}
     */
    public function import(Tenant $tenant, string $archivePath): array
    {
        $extractDir = $this->extract($archivePath);

        try {
            $manifest = $this->readManifest($extractDir);

            tenancy()->initialize($tenant);

            try {
                // Wrapped in a transaction so a failure partway through
                // (e.g. a corrupt media file) rolls the DB back to the
                // pre-import state instead of leaving the tenant half-wiped.
                // Media files already written to disk during a failed
                // attempt aren't part of that rollback and may need manual
                // cleanup — an acceptable gap for how rarely this fails.
                return DB::transaction(function () use ($manifest, $extractDir) {
                    $this->wipe();

                    return $this->recreate($manifest, $extractDir);
                });
            } finally {
                tenancy()->end();
            }
        } finally {
            File::deleteDirectory($extractDir);
        }
    }

    private function extract(string $archivePath): string
    {
        if (! File::exists($archivePath)) {
            throw new InvalidArgumentException("Archive not found: {$archivePath}");
        }

        $extractDir = sys_get_temp_dir().'/tenant-import-'.Str::uuid();
        File::ensureDirectoryExists($extractDir);

        $zip = new ZipArchive;

        if ($zip->open($archivePath) !== true) {
            throw new InvalidArgumentException("Could not open archive: {$archivePath}");
        }

        $this->assertNoUnsafeEntries($zip);

        $zip->extractTo($extractDir);
        $zip->close();

        return $extractDir;
    }

    /**
     * Rejects a zip-slip / path traversal archive before anything is written
     * to disk — an entry name containing "..", or an absolute path, could
     * otherwise make ZipArchive::extractTo() write outside $extractDir. This
     * is CLI/operator-only (an operator choosing to import an untrusted
     * archive), but cheap and self-contained to guard regardless. See
     * docs/tasks/2026-08-08_security-review-findings.md TASK-7.
     */
    private function assertNoUnsafeEntries(ZipArchive $zip): void
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if ($name === false) {
                continue;
            }

            // Normalize Windows-style separators too, since the archive
            // isn't guaranteed to have been built on this OS.
            $normalized = str_replace('\\', '/', $name);

            if (
                str_starts_with($normalized, '/')
                || preg_match('#^[A-Za-z]:#', $normalized) === 1
                || str_contains($normalized, '../')
                || str_ends_with($normalized, '/..')
                || $normalized === '..'
            ) {
                throw new InvalidArgumentException("Archive contains an unsafe path and was rejected: {$name}");
            }
        }
    }

    private function readManifest(string $extractDir): array
    {
        $manifestPath = $extractDir.'/manifest.json';

        if (! File::exists($manifestPath)) {
            throw new InvalidArgumentException('Archive does not contain a manifest.json — is this a tenant export?');
        }

        $manifest = json_decode(File::get($manifestPath), true);

        if (($manifest['schema_version'] ?? null) !== self::SUPPORTED_SCHEMA_VERSION) {
            $found = $manifest['schema_version'] ?? 'none';

            throw new InvalidArgumentException(
                "Unsupported archive schema version [{$found}] — expected [".self::SUPPORTED_SCHEMA_VERSION.'].',
            );
        }

        return $manifest;
    }

    /**
     * Deletes existing content one model at a time (never a bulk query
     * delete) so spatie/medialibrary's deleting-model hook fires and removes
     * the associated media files — a bulk delete would leave them orphaned
     * on disk. Posts before sections before pages, since a section/page bulk
     * FK cascade would otherwise remove a post's row without ever firing its
     * own deleting hook.
     */
    private function wipe(): void
    {
        Post::query()->get()->each->delete();
        Section::query()->get()->each->delete();
        Page::query()->get()->each->delete();
        NavItem::query()->delete();
    }

    /**
     * @return array{pages: int, sections: int, posts: int, nav_items: int, media: int}
     */
    private function recreate(array $manifest, string $extractDir): array
    {
        $counts = ['pages' => 0, 'sections' => 0, 'posts' => 0, 'nav_items' => 0, 'media' => 0];
        $pageIdByExportId = [];
        $mediaIdBySourceId = [];
        $rewriteTargets = [];

        foreach ($manifest['pages'] as $pageData) {
            $page = Page::create([
                'slug' => $pageData['slug'],
                'title' => $pageData['title'],
                'template' => $pageData['template'],
                'published' => $pageData['published'],
                'order' => $pageData['order'],
            ]);
            $pageIdByExportId[$pageData['export_id']] = $page->id;
            $counts['pages']++;

            foreach ($pageData['sections'] as $sectionData) {
                // label/type/order aren't stored — see App\Models\Section —
                // so the imported row picks them up live from whatever the
                // target tenant's current schema says for this key, not
                // whatever the exporting tenant's schema said at export time.
                $section = $page->sections()->create([
                    'key' => $sectionData['key'],
                    'value' => $sectionData['value'],
                    'alt' => $sectionData['alt'] ?? null,
                ]);

                // An import bypasses the controllers entirely, so it must
                // run through the same sanitizer they do — an export/import
                // round trip (or a hostile/compromised archive) must not be
                // able to reintroduce the stored-XSS hole those close. See
                // docs/tasks/2026-08-08_security-review-findings.md TASK-6.
                if ($section->type === 'wysiwyg' && filled($section->value)) {
                    $section->update(['value' => WysiwygSanitizer::clean($section->value)]);
                }
                $counts['sections']++;

                if (filled($section->value)) {
                    $rewriteTargets[] = [$section, 'value'];
                }

                $this->recreateMedia($section, $sectionData['media'], $extractDir, $mediaIdBySourceId);
                $counts['media'] += count($sectionData['media']);

                foreach ($sectionData['posts'] as $postData) {
                    $post = $section->posts()->create([
                        'title' => $postData['title'],
                        'excerpt' => $postData['excerpt'],
                        'starts_at' => $postData['starts_at'],
                        'body' => $postData['body'],
                        'image' => $postData['image'],
                        'order' => $postData['order'],
                    ]);
                    // Not mass-assignable — see Post::syncSlug()'s doc comment.
                    // Same "fill, then derive the slug" sequence PostController uses.
                    $post->syncSlug();
                    // A post's body is always rich text when present — same
                    // sanitization Admin\PostController::update() applies.
                    // See docs/tasks/2026-08-08_security-review-findings.md
                    // TASK-6.
                    $post->body = WysiwygSanitizer::clean($post->body);
                    $post->save();
                    $counts['posts']++;

                    foreach (['body', 'image', 'excerpt'] as $field) {
                        if (filled($post->{$field})) {
                            $rewriteTargets[] = [$post, $field];
                        }
                    }

                    $this->recreateMedia($post, $postData['media'], $extractDir, $mediaIdBySourceId);
                    $counts['media'] += count($postData['media']);
                }
            }
        }

        foreach ($manifest['nav_items'] as $navData) {
            NavItem::create([
                'type' => $navData['type'],
                'page_id' => $navData['page_export_id'] ? ($pageIdByExportId[$navData['page_export_id']] ?? null) : null,
                'label' => $navData['label'],
                'url' => $navData['url'],
                'order' => $navData['order'],
            ]);
            $counts['nav_items']++;
        }

        foreach ($manifest['site_config'] as $configData) {
            SiteConfig::updateOrCreate(['key' => $configData['key']], [
                'label' => $configData['label'],
                'type' => $configData['type'],
                'value' => $configData['value'],
            ]);
        }

        $this->rewriteMediaReferences($rewriteTargets, $mediaIdBySourceId);

        return $counts;
    }

    private function recreateMedia(HasMedia $model, array $mediaItems, string $extractDir, array &$mediaIdBySourceId): void
    {
        foreach ($mediaItems as $item) {
            // manifest.json is attacker-controllable (it travels inside the
            // same archive an operator is importing) — basename() collapses
            // any directory traversal in $item['archive_file'] down to a
            // bare filename before it's used to build a filesystem path, so
            // a crafted manifest can't read/write outside $extractDir/media.
            // See docs/tasks/2026-08-08_security-review-findings.md TASK-8.
            $archiveFile = basename((string) $item['archive_file']);

            $newMedia = $model->addMedia($extractDir.'/media/'.$archiveFile)
                ->preservingOriginal()
                ->usingFileName($item['file_name'])
                ->toMediaCollection($item['collection_name']);

            $mediaIdBySourceId[$item['source_id']] = $newMedia->id;
        }
    }

    /**
     * Rewrites "/media/{old id}/{filename}" (with or without a scheme+host
     * prefix — an old export may have been taken on a different domain) to
     * the same file's new id, as a host-relative path. Anything not found in
     * $mediaIdBySourceId is left untouched rather than guessed at.
     */
    private function rewriteMediaReferences(array $rewriteTargets, array $mediaIdBySourceId): void
    {
        if ($mediaIdBySourceId === []) {
            return;
        }

        $pattern = '#(?:https?://[^/\s"\']+)?/media/(\d+)/([^\s"\'<>]+)#';

        foreach ($rewriteTargets as [$model, $field]) {
            $original = $model->{$field};

            $rewritten = preg_replace_callback($pattern, function (array $m) use ($mediaIdBySourceId) {
                $newId = $mediaIdBySourceId[(int) $m[1]] ?? null;

                return $newId ? "/media/{$newId}/{$m[2]}" : $m[0];
            }, $original);

            if ($rewritten !== $original) {
                $model->{$field} = $rewritten;
                $model->save();
            }
        }
    }
}
