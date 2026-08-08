<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Section;
use App\Services\WysiwygSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PostController extends Controller
{
    public function store(Request $request, Section $section): RedirectResponse
    {
        // A 'posts' section's entries never have a date; an 'events'
        // section's always do — see THEMES.md §8. Enforced here too, not
        // just by which fields PostEditor.svelte happens to show.
        $hasDates = $section->type === 'events';

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'starts_at' => $hasDates ? ['required', 'date'] : ['prohibited'],
        ]);

        $section->posts()->create([
            'title' => $validated['title'],
            'starts_at' => $validated['starts_at'] ?? null,
            'order' => ($section->posts()->max('order') ?? 0) + 1,
        ]);

        return back();
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $hasDates = $post->section->type === 'events';

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'image' => ['nullable', 'string', 'max:2048'],
            'starts_at' => $hasDates ? ['required', 'date'] : ['prohibited'],
            'auto_delete' => $hasDates ? ['boolean'] : ['prohibited'],
            'body' => ['nullable', 'string'],
        ]);

        $validated['auto_delete'] ??= false;

        // A post's `body` is always rich text when present (see Post's doc
        // comment) — unlike Section::value, there's no plain-text variant to
        // branch around. See docs/tasks/2026-08-08_security-review-findings.md
        // TASK-3.
        if (array_key_exists('body', $validated)) {
            $validated['body'] = WysiwygSanitizer::clean($validated['body']);
        }

        $post->fill($validated);
        // Re-derives from the (possibly just-changed) title whenever the
        // post has a body — see Post::syncSlug()'s doc comment.
        $post->syncSlug();
        $post->save();

        return back();
    }

    public function destroy(Post $post): RedirectResponse
    {
        $post->delete();

        return back();
    }

    public function move(Request $request, Post $post): RedirectResponse
    {
        $validated = $request->validate([
            'direction' => ['required', Rule::in(['up', 'down'])],
        ]);

        $neighbor = $validated['direction'] === 'up'
            ? Post::where('section_id', $post->section_id)->where('order', '<', $post->order)->orderByDesc('order')->first()
            : Post::where('section_id', $post->section_id)->where('order', '>', $post->order)->orderBy('order')->first();

        if ($neighbor) {
            [$post->order, $neighbor->order] = [$neighbor->order, $post->order];
            $post->save();
            $neighbor->save();
        }

        return back();
    }
}
