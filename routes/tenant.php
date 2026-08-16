<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\NavigationController;
use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Http\Controllers\Admin\PasswordResetController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\SectionController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Frontend\PageController;
use App\Http\Controllers\Frontend\FaviconController;
use App\Http\Controllers\Frontend\PostController as FrontendPostController;
use App\Http\Controllers\Frontend\SitemapController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Loaded by TenancyServiceProvider. The tenant is resolved from the Host
| header, which switches the default DB connection to that tenant's SQLite
| file. Requests arriving on a central domain are rejected.
|
| Anything touching pages, sections, site config or tenant users belongs
| here — not in web.php.
|
*/

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    // Public site. /{slug} is registered last so literal routes (/admin/...)
    // always win the match first.
    Route::get('/', [PageController::class, 'home']);

    // Media is public (unauthenticated) — uploaded images appear on public
    // pages, not just in the admin. See MediaController::show for why this
    // exists instead of a plain public/storage symlink.
    Route::get('/media/{media}/{filename}', [MediaController::class, 'show'])->name('media.show');

    // Registered before the /{slug} catch-all below, same as /aktuelles/{slug}
    // — otherwise "sitemap.xml" would be swallowed as a literal page slug.
    Route::get('/sitemap.xml', [SitemapController::class, 'index']);

    // Theme favicon — /favicon/{theme} (two segments, no static file to
    // shadow it, unlike a plain /favicon.ico which php artisan serve serves
    // straight from public/). Registered here, before the /{slug} catch-all,
    // so "favicon" can never be swallowed as a literal page slug. The URL is
    // immutable-cached per theme slug — see FaviconController.
    Route::get('/favicon/{theme}', [FaviconController::class, 'show']);

    Route::prefix('admin')->group(function () {
        // Not behind 'guest' — it should work regardless of current auth
        // state. AuthController::devLogin() re-checks the environment
        // itself (a stale route:cache carried into a non-local deploy would
        // still hit that guard) — this is additional, belt-and-suspenders
        // hardening on top of an already-sound check: outside local, the
        // route isn't registered at all. See
        // docs/tasks/2026-08-08_security-review-findings.md TASK-9.
        if (app()->environment('local')) {
            Route::get('/dev-login', [AuthController::class, 'devLogin'])->name('admin.dev-login');
        }

        Route::middleware('guest')->group(function () {
            Route::get('/login', [AuthController::class, 'showLogin'])->name('admin.login');
            Route::post('/login', [AuthController::class, 'login']);

            Route::get('/forgot-password', [PasswordResetController::class, 'showRequestForm'])
                ->name('admin.password.request');
            Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])
                ->name('admin.password.email');
            // Route name 'password.reset' is required by Laravel's default
            // ResetPassword notification, which hardcodes it when building
            // the emailed link.
            Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])
                ->name('password.reset');
            Route::post('/reset-password', [PasswordResetController::class, 'reset'])
                ->name('admin.password.update');
        });

        Route::middleware(['auth', 'role:editor|superadmin'])->group(function () {
            Route::get('/', [AdminController::class, 'index'])->name('admin.index');
            Route::post('/logout', [AuthController::class, 'logout'])->name('admin.logout');

            Route::get('/pages', [AdminPageController::class, 'index'])->name('admin.pages.index');
            Route::post('/pages', [AdminPageController::class, 'store'])->name('admin.pages.store');
            Route::get('/pages/{page}', [AdminPageController::class, 'edit'])->name('admin.pages.edit');
            Route::patch('/pages/{page}', [AdminPageController::class, 'update'])->name('admin.pages.update');
            Route::delete('/pages/{page}', [AdminPageController::class, 'destroy'])->name('admin.pages.destroy');
            Route::patch('/pages/{page}/sections/{section}', [SectionController::class, 'update'])
                ->name('admin.pages.sections.update');

            Route::post('/sections/{section}/posts', [PostController::class, 'store'])->name('admin.posts.store');
            Route::patch('/posts/{post}', [PostController::class, 'update'])->name('admin.posts.update');
            Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('admin.posts.destroy');
            Route::post('/posts/{post}/move', [PostController::class, 'move'])->name('admin.posts.move');

            Route::get('/settings', [SettingsController::class, 'index'])->name('admin.settings');
            Route::patch('/settings/{siteConfig}', [SettingsController::class, 'update'])
                ->name('admin.settings.update');

            Route::get('/navigation', [NavigationController::class, 'index'])->name('admin.navigation.index');
            Route::post('/navigation', [NavigationController::class, 'store'])->name('admin.navigation.store');
            Route::patch('/navigation/{navItem}', [NavigationController::class, 'update'])->name('admin.navigation.update');
            Route::post('/navigation/{navItem}/move', [NavigationController::class, 'move'])->name('admin.navigation.move');
            Route::delete('/navigation/{navItem}', [NavigationController::class, 'destroy'])->name('admin.navigation.destroy');

            Route::post('/media', [MediaController::class, 'store'])->name('admin.media.store');
        });
    });

    // Only a post with a body (isDetailed()) resolves here — see
    // Frontend\PostController. Two segments, so it never collides with the
    // single-segment /{slug} catch-all below regardless of route order.
    Route::get('/aktuelles/{slug}', [FrontendPostController::class, 'show']);

    Route::get('/{slug}', [PageController::class, 'show']);
});
