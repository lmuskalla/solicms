<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * label/type/order are never stored — they're resolved live from this
 * section's template's schema (config/themes.php), keyed by `key`, so
 * rewording/retyping/reordering a template's fields in config takes effect
 * everywhere immediately. Only `key` and `value` are real, persisted data.
 * A key the current schema no longer declares (a field renamed or removed
 * from the theme) simply has no label/type/order — see schemaEntry().
 *
 * `alt` is likewise real, persisted data — but only ever meaningful when
 * `type` resolves to 'image' (see docs/tasks/2026-08-08_feature-improvements.md
 * TASK-4/5); every other type leaves it null.
 */
#[Fillable(['page_id', 'key', 'value', 'alt'])]
class Section extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $appends = ['label', 'type', 'order'];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    protected function label(): Attribute
    {
        return Attribute::get(fn () => $this->schemaEntry()['label'] ?? null);
    }

    protected function type(): Attribute
    {
        return Attribute::get(fn () => $this->schemaEntry()['type'] ?? null);
    }

    protected function order(): Attribute
    {
        return Attribute::get(function () {
            $index = collect($this->schema())->search(fn (array $s) => $s['key'] === $this->key);

            return $index === false ? null : $index + 1;
        });
    }

    /**
     * Null for an orphaned key — one the current schema no longer declares.
     */
    private function schemaEntry(): ?array
    {
        return collect($this->schema())->firstWhere('key', $this->key);
    }

    private function schema(): array
    {
        $theme = tenant('theme') ?? 'default';

        return config("themes.{$theme}.templates.{$this->page->template}.sections", []);
    }

    /**
     * Only meaningful when type === 'posts' — see the sections migration's
     * comment on the `type` column.
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class)->orderBy('order');
    }

    public function registerMediaCollections(): void
    {
        // One image per section — a new upload replaces the previous one.
        // Used by the 'image' section type (e.g. a hero image field).
        $this->addMediaCollection('content')->singleFile();

        // Unlimited, non-replacing — inline images and file attachments
        // embedded inside a 'wysiwyg' section's body via the Tiptap editor.
        // A body can reference several of these; uploading one must never
        // remove another that's still referenced elsewhere in the text.
        $this->addMediaCollection('attachments');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(400)
            ->height(400)
            ->sharpen(10)
            ->nonQueued();
    }
}
