<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in a tenant's navigation. Navigation is editor-built, not
 * auto-derived from the page list — a page can exist without being in any
 * menu, and a nav item's label can differ from its page's title.
 *
 * An item belongs to exactly one `menu` — see MENUS — which decides whether
 * it renders in the header or the footer of the public site. The admin
 * navigation screen (Admin\NavigationController) validates against MENUS and
 * the frontend controllers split their queries on it.
 */
#[Fillable(['type', 'page_id', 'label', 'url', 'order', 'menu'])]
class NavItem extends Model
{
    /**
     * The menus a nav item can belong to. Single source of truth — the admin
     * controller validates against this and the frontend controllers split
     * their queries on it.
     *
     * @var list<string>
     */
    public const MENUS = ['header', 'footer'];

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
