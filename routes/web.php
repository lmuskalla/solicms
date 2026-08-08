<?php

use App\Http\Controllers\Superadmin\AuthController;
use App\Http\Controllers\Superadmin\TenantController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central Routes
|--------------------------------------------------------------------------
|
| These run in the central context against the central database — no tenant
| is initialized here. Only platform-level (superadmin) concerns belong in
| this file; everything client-facing lives in routes/tenant.php.
|
| URIs must not collide with tenant routes. Tenant routes are registered
| after these and would silently override an identical method + URI, so
| central routes are namespaced under /superadmin.
|
*/

Route::prefix('superadmin')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'showLogin'])->name('superadmin.login');
        Route::post('/login', [AuthController::class, 'login']);
    });

    Route::middleware('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('superadmin.logout');

        Route::get('/tenants', [TenantController::class, 'index'])->name('superadmin.tenants.index');
        Route::post('/tenants', [TenantController::class, 'store'])->name('superadmin.tenants.store');
        Route::delete('/tenants/{tenant}', [TenantController::class, 'destroy'])->name('superadmin.tenants.destroy');

        Route::redirect('/', '/superadmin/tenants');
    });
});
