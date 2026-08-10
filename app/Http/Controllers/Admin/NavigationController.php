<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NavItem;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NavigationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Navigation/Index', [
            'headerItems' => $this->itemList('header'),
            'footerItems' => $this->itemList('footer'),
            'pages' => Page::orderBy('order')->get(['id', 'slug', 'title']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['page', 'link'])],
            'page_id' => ['required_if:type,page', 'nullable', 'exists:pages,id'],
            'url' => ['required_if:type,link', 'nullable', 'string', 'max:2048'],
            'label' => ['required', 'string', 'max:255'],
            'menu' => ['nullable', Rule::in(NavItem::MENUS)],
        ]);

        $menu = $validated['menu'] ?? 'header';

        NavItem::create([
            ...$validated,
            'menu' => $menu,
            // Order is scoped per menu — a global max would append into the
            // other menu's ordering space.
            'order' => (NavItem::where('menu', $menu)->max('order') ?? 0) + 1,
        ]);

        return back();
    }

    public function update(Request $request, NavItem $navItem): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'url' => [$navItem->type === 'link' ? 'required' : 'nullable', 'string', 'max:2048'],
            'menu' => ['nullable', Rule::in(NavItem::MENUS)],
        ]);

        // Moving an item to the other menu appends it at that menu's end —
        // its order was scoped to its old menu, so keeping it could collide
        // with items already ordered there.
        if (isset($validated['menu']) && $validated['menu'] !== $navItem->menu) {
            $validated['order'] = (NavItem::where('menu', $validated['menu'])->max('order') ?? 0) + 1;
        }

        $navItem->update($validated);

        return back();
    }

    public function move(Request $request, NavItem $navItem): RedirectResponse
    {
        $validated = $request->validate([
            'direction' => ['required', Rule::in(['up', 'down'])],
        ]);

        // Neighbor lookup is scoped to the item's own menu — a global
        // order-neighbor could swap an item into the other menu's sequence.
        $neighbor = $validated['direction'] === 'up'
            ? NavItem::where('menu', $navItem->menu)->where('order', '<', $navItem->order)->orderByDesc('order')->first()
            : NavItem::where('menu', $navItem->menu)->where('order', '>', $navItem->order)->orderBy('order')->first();

        if ($neighbor) {
            [$navItem->order, $neighbor->order] = [$neighbor->order, $navItem->order];
            $navItem->save();
            $neighbor->save();
        }

        return back();
    }

    public function destroy(NavItem $navItem): RedirectResponse
    {
        $navItem->delete();

        return back()->with('success', 'Aus dem Menü entfernt.');
    }

    /**
     * One menu's items in display order. Which of the two menus ('header' or
     * 'footer' — see NavItem::MENUS) is passed decides the list; the admin
     * screen renders the two stacked, one beneath the other.
     *
     * @return list<array{id: int, type: string, label: string, url: ?string, page: ?array{id: int, title: string}}>
     */
    private function itemList(string $menu): array
    {
        return NavItem::with('page:id,slug,title')
            ->where('menu', $menu)
            ->orderBy('order')
            ->get()
            ->map(fn (NavItem $item) => [
                'id' => $item->id,
                'type' => $item->type,
                'label' => $item->label,
                'url' => $item->url,
                'page' => $item->page ? ['id' => $item->page->id, 'title' => $item->page->title] : null,
            ])
            ->all();
    }
}
