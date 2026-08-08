<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Frontend\Concerns\ResolvesThemeComponent;
use App\Models\NavItem;
use App\Models\Post;
use App\Models\SiteConfig;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    use ResolvesThemeComponent;

    /**
     * A post's detail page reuses the theme's generic content-page
     * component — see App\Models\Post's doc comment: a post with a body is
     * structurally the same thing as any other wysiwyg content page, so it
     * gets no separate rendering pipeline of its own.
     */
    public function show(string $slug): Response
    {
        $post = Post::where('slug', $slug)->first();

        if (! $post || ! $post->isDetailed()) {
            abort(404);
        }

        return Inertia::render('Frontend/Page', [
            'page' => ['title' => $post->title, 'template' => 'wysiwyg'],
            'sections' => ['body' => ['value' => $post->body]],
            'config' => SiteConfig::allAsMap(),
            'nav' => NavItem::with('page:id,slug')->orderBy('order')->get()
                ->map(fn (NavItem $item) => ['label' => $item->label, 'href' => $item->href()]),
            'themeComponent' => $this->themeComponent('wysiwyg'),
        ]);
    }
}
