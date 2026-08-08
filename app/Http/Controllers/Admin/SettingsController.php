<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Settings', [
            'configs' => SiteConfig::orderBy('id')->get(),
        ]);
    }

    public function update(Request $request, SiteConfig $siteConfig): RedirectResponse
    {
        $validated = $request->validate([
            'value' => ['nullable', 'string'],
        ]);

        $siteConfig->update($validated);

        return back();
    }
}
