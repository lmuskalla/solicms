<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A single entry inside a 'posts'- or 'events'-type Section — e.g. one
 * "News" card or one "Aktuelle Termine" row. There is deliberately no
 * `type` column of its own: what a post *is* mostly follows from which
 * fields are filled in, not a label the editor has to understand —
 *
 *   - `starts_at` — always null on a 'posts' section's entries, always set
 *                    on an 'events' section's (see isEvent()); the owning
 *                    Section's type decides this, not a per-post choice —
 *                    enforced in Admin\PostController, not just by which
 *                    fields PostEditor.svelte happens to show.
 *   - `image` set      → renders as a photo card.
 *   - `body` set       → gets a real detail page at /aktuelles/{slug}
 *                         (see isDetailed(), Frontend\PostController).
 *   - `auto_delete`    → only meaningful alongside `starts_at`; opts this
 *                         post into being removed once it's in the past —
 *                         see App\Jobs\PruneExpiredPosts.
 *
 * `image`/`body` can be combined freely regardless of section type.
 */
#[Fillable(['section_id', 'title', 'excerpt', 'image', 'starts_at', 'auto_delete', 'body', 'order'])]
class Post extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $casts = [
        'starts_at' => 'datetime',
        'auto_delete' => 'boolean',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')->singleFile();

        // Inline images embedded inside `body` via the Tiptap editor —
        // mirrors Section::registerMediaCollections()'s 'attachments'.
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

    public function isEvent(): bool
    {
        return $this->starts_at !== null;
    }

    public function isDetailed(): bool
    {
        return filled($this->body);
    }

    /**
     * Only set (and kept in sync) once a post has a body — see the `slug`
     * column comment in the posts migration. Called from PostController on
     * every save so a title edit after the fact keeps the URL current.
     */
    public function syncSlug(): void
    {
        $this->slug = $this->isDetailed()
            ? Str::slug($this->title).'-'.$this->id
            : null;
    }
}
