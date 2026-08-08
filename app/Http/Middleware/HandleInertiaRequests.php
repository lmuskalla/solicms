<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            // The domain actually being visited — shown in the admin sidebar
            // instead of a generic label, since one Laravel install serves
            // every tenant's site under its own domain.
            'domain' => $request->getHost(),
            // Some themes (e.g. a single-page site with no inter-page links)
            // have no use for the NavItem-based "Navigation" admin screen —
            // see config/themes.php's 'uses_page_nav'. Hiding the sidebar
            // link for those avoids a screen that visibly does nothing.
            'usesPageNav' => (bool) config('themes.'.(tenant('theme') ?? 'default').'.uses_page_nav', true),
            'auth' => [
                'user' => $request->user()
                    ? $request->user()->only(['id', 'name', 'email', 'role'])
                    : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
