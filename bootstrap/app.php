<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Caddy reverse-proxies HTTPS to this app over plain HTTP — without
        // trusting it, Laravel reads the scheme of that internal hop (http)
        // instead of X-Forwarded-Proto, and generates http:// asset/route
        // URLs on an https:// page (mixed-content errors). Safe to trust '*'
        // here since the app's port is never reachable except through Caddy.
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        // Two separate login pages share the 'auth'/'guest' middleware: tenant
        // routes never initialize tenancy for a central-domain request, so
        // tenant() being null here means "this is the central context".
        $middleware->redirectGuestsTo(
            fn () => tenant() ? route('admin.login') : route('superadmin.login')
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // An unrecognized domain (e.g. a deleted tenant's old domain still
        // getting hits) is a 404, not a server error — and without this it
        // renders as a 500 with a full stack trace whenever APP_DEBUG is on.
        $exceptions->render(function (\Stancl\Tenancy\Contracts\TenantCouldNotBeIdentifiedException $e) {
            abort(404);
        });
    })->create();
