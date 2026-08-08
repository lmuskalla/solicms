<?php

namespace App\Providers;

use Illuminate\Foundation\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Without this, Vite's @vite() directive resolves build asset URLs via
        // the global asset() helper, which tenancy.filesystem.asset_helper_tenancy
        // rewrites to /tenancy/assets/... on tenant domains (see
        // FilesystemTenancyBootstrapper) — a route meant for per-tenant storage
        // files, not the Vite build. That 404s as HTML, and browsers refuse to
        // execute a script served with an HTML MIME type. global_asset() is
        // stancl/tenancy's own escape hatch back to the untenanted asset root.
        $this->app->make(Vite::class)->createAssetPathsUsing(
            fn (string $path, ?bool $secure = null) => global_asset($path)
        );
    }
}
