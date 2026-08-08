<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\TenantProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use RuntimeException;

class TenantController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Superadmin/Tenants/Index', [
            'tenants' => Tenant::with('domains')->latest()->get()->map(fn (Tenant $tenant) => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'theme' => $tenant->theme,
                'domains' => $tenant->domains->pluck('domain'),
                'created_at' => $tenant->created_at,
                'dev_login_url' => $this->devLoginUrl($tenant, $request),
            ]),
            'availableThemes' => collect(config('themes'))->map(fn (array $t) => $t['label'])->all(),
        ]);
    }

    /**
     * Only meaningful in local dev, where credentials are throwaway and
     * remembering them for every tenant is pure friction — see
     * Admin\AuthController::devLogin(), which actually performs the login
     * and independently re-checks the environment before doing anything.
     * Null hides the button entirely rather than rendering a dead link.
     */
    private function devLoginUrl(Tenant $tenant, Request $request): ?string
    {
        if (! app()->environment('local')) {
            return null;
        }

        $domain = $tenant->domains->first(fn ($d) => str_contains($d->domain, 'localhost'))
            ?? $tenant->domains->first();

        if (! $domain) {
            return null;
        }

        $port = $request->getPort();
        $portSuffix = in_array($port, [80, 443], true) ? '' : ":{$port}";

        return "{$request->getScheme()}://{$domain->domain}{$portSuffix}/admin/dev-login";
    }

    public function store(Request $request, TenantProvisioner $provisioner): RedirectResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'editor_name' => ['required', 'string', 'max:255'],
            'theme' => ['required', 'string', Rule::in(array_keys(config('themes')))],
        ]);

        // The form field is a single input — comma-separated so one tenant can
        // be registered on more than one domain (e.g. a client with both a
        // current and a legacy domain) without a repeatable-field UI.
        $domains = array_values(array_filter(array_map('trim', explode(',', $validated['domain']))));

        try {
            $result = $provisioner->provision(
                $domains,
                $validated['name'],
                $validated['email'],
                $validated['editor_name'],
                'default',
                $validated['theme'],
            );
        } catch (RuntimeException|InvalidArgumentException $e) {
            return back()->withErrors(['domain' => $e->getMessage()])->withInput();
        }

        return redirect()->route('superadmin.tenants.index')->with(
            'success',
            "Tenant erstellt. Passwort für {$validated['email']}: {$result['password']} (wird nicht gespeichert — jetzt notieren).",
        );
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        // Fires TenantDeleted, which synchronously runs DeleteDatabase
        // (see TenancyServiceProvider::events()) — the tenant's SQLite file
        // is removed along with the central record.
        $tenant->delete();

        return redirect()->route('superadmin.tenants.index')->with('success', 'Tenant gelöscht.');
    }
}
