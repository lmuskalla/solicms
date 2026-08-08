<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Response;

/**
 * A minimal per-tenant sitemap.xml listing every published page — see
 * docs/tasks/2026-08-08_seo-findings.md TASK-9. Low priority per the report
 * ("worth a line"), so deliberately simple: no lastmod/priority/changefreq,
 * no posts/aktuelles detail pages, just the published Page list.
 */
class SitemapController extends Controller
{
    public function index(): Response
    {
        $urls = Page::where('published', true)
            ->orderBy('order')
            ->get()
            ->map(fn (Page $page) => $this->pageUrl($page));

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
            .$urls->map(fn (string $url) => '  <url><loc>'.e($url).'</loc></url>')->implode("\n")
            ."\n</urlset>\n";

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    /**
     * Same "primary domain" convention as Frontend\PageController::canonicalUrl()
     * — a tenant with more than one domain should have its sitemap point at
     * the same domain its pages' own canonical tags do.
     */
    private function pageUrl(Page $page): string
    {
        $primaryDomain = tenant()->domains()->orderBy('id')->first()?->domain ?? request()->getHost();
        $path = $page->slug === 'home' ? '' : '/'.$page->slug;

        return request()->getScheme().'://'.$primaryDomain.$path;
    }
}
