<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['type', 'page_id', 'label', 'url', 'order'])]
class NavItem extends Model
{
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * Where this item actually points. Home is '/' rather than '/home' —
     * everything else is exactly what the editor set.
     */
    public function href(): string
    {
        if ($this->type === 'link') {
            return $this->url ?? '#';
        }

        return $this->page && $this->page->slug !== 'home'
            ? "/{$this->page->slug}"
            : '/';
    }
}
