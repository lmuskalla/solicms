<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Authentication for a client site's own admin.
 *
 * Users live in the tenant database, so these credentials are only ever valid
 * on the domain they were created for.
 */
class AuthController extends Controller
{
    /**
     * Failed attempts allowed per email + IP before lockout.
     */
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function showLogin(Request $request): Response|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('admin.index');
        }

        return Inertia::render('Admin/Login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => $seconds]),
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            // Deliberately vague: don't reveal whether the address exists.
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($throttleKey);

        // Prevents session fixation.
        $request->session()->regenerate();

        return redirect()->intended(route('admin.index'));
    }

    /**
     * Local dev only, re-checked here rather than trusting the button's own
     * visibility (Superadmin\TenantController::devLoginUrl()) — the route
     * itself must be a dead end regardless of how it was reached, including
     * a cached route list accidentally carried into a non-local deploy.
     */
    public function devLogin(Request $request): RedirectResponse
    {
        abort_unless(app()->environment('local'), 404);

        $user = User::where('role', 'editor')->first() ?? User::first();

        abort_if(! $user, 404, 'Diesem Tenant fehlt noch ein Benutzer.');

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('admin.index');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    private function throttleKey(Request $request): string
    {
        return Str::transliterate(
            Str::lower($request->string('email')->value()).'|'.$request->ip()
        );
    }
}
