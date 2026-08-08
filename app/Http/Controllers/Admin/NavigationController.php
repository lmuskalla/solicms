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
            'items' => NavItem::with('page:id,slug,title')->orderBy('order')->get()->map(fn (NavItem $item) => [
                'id' => $item->id,
                'type' => $item->type,
                'label' => $item->label,
                'url' => $item->url,
                'page' => $item->page ? ['id' => $item->page->id, 'title' => $item->page->title] : null,
            ]),
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
        ]);

        NavItem::create([
            ...$validated,
            'order' => (NavItem::max('order') ?? 0) + 1,
        ]);

        return back();
    }

    public function update(Request $request, NavItem $navItem): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'url' => [$navItem->type === 'link' ? 'required' : 'nullable', 'string', 'max:2048'],
        ]);

        $navItem->update($validated);

        return back();
    }

    public function move(Request $request, NavItem $navItem): RedirectResponse
    {
        $validated = $request->validate([
            'direction' => ['required', Rule::in(['up', 'down'])],
        ]);

        $neighbor = $validated['direction'] === 'up'
            ? NavItem::where('order', '<', $navItem->order)->orderByDesc('order')->first()
            : NavItem::where('order', '>', $navItem->order)->orderBy('order')->first();

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
}
