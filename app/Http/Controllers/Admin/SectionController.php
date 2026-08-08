<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Section;
use App\Services\WysiwygSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SectionController extends Controller
{
    public function update(Request $request, Page $page, Section $section): RedirectResponse
    {
        if ($section->page_id !== $page->id) {
            throw new NotFoundHttpException;
        }

        $validated = $request->validate([
            'value' => ['nullable', 'string'],
            'alt' => ['nullable', 'string', 'max:255'],
        ]);

        // Only a 'wysiwyg' section's value can ever legitimately contain
        // HTML — every other type (text/textarea/email/url/image) is stored
        // and rendered as plain text, so running it through the sanitizer
        // would be pointless at best and could mangle it (e.g. stripping a
        // literal "<" a client typed) at worst. See
        // docs/tasks/2026-08-08_security-review-findings.md TASK-2.
        if ($section->type === 'wysiwyg') {
            $validated['value'] = WysiwygSanitizer::clean($validated['value'] ?? null);
        }

        // 'alt' only ever means anything on an 'image' section — dropped
        // for every other type rather than persisted, even if it were ever
        // sent for one.
        if ($section->type !== 'image') {
            unset($validated['alt']);
        }

        $section->update($validated);

        return back();
    }
}
