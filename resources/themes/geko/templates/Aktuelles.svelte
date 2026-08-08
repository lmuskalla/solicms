<script lang="ts">
    import Header from '../components/Header.svelte';
    import Footer from '../components/Footer.svelte';
    import NewsCard from '../components/NewsCard.svelte';
    import EventRow from '../components/EventRow.svelte';
    import SeoHead from '../../../js/Components/Frontend/SeoHead.svelte';
    import { sanitizeHtml } from '../../../js/lib/sanitizeHtml';
    import type { ThemeProps } from '../../../js/types';

    let { page, sections, config, nav, url }: ThemeProps = $props();

    // Fixed keys, not a scan across sections for type === 'posts'/'events'
    // — see THEMES.md §8. This is the one page whose 'aktuelles' template
    // declares 'news'/'termine' (theme.php); no other template does, so no
    // other page can end up rendering this by accident. 'termine' entries
    // always have starts_at (enforced in Admin\PostController), so no
    // filter is needed here anymore — just a sort.
    const newsPosts = $derived(sections.news?.posts ?? []);
    const eventPosts = $derived(
        (sections.termine?.posts ?? []).slice().sort((a, b) => (a.starts_at ?? '').localeCompare(b.starts_at ?? '')),
    );
</script>

<SeoHead title={`${page?.title} — ${config.site_name ?? 'Gesundheitskollektiv Bremen'}`} {sections} {url} />

<div class="theme-geko min-h-screen bg-white">
    <Header {config} {nav} />

    <main id="main" class="mx-auto max-w-6xl px-6 py-10 sm:py-14">
        <h1 class="text-5xl font-light tracking-tight text-[var(--geko-indigo)] sm:text-6xl">{page?.title}</h1>

        {#if sections.body?.value}
            <!-- eslint-disable svelte/no-at-html-tags -->
            <div class="rich-text mt-6 max-w-3xl">{@html sanitizeHtml(sections.body.value)}</div>
        {/if}

        {#if newsPosts.length > 0}
            <section class="mt-12">
                <h2 class="text-4xl font-light text-neutral-900">News</h2>
                <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    {#each newsPosts as post (post.id)}
                        <NewsCard {post} />
                    {/each}
                </div>
            </section>
        {/if}

        {#if eventPosts.length > 0}
            <section class="mt-12">
                <h2 class="text-4xl font-light text-neutral-900">Termine</h2>
                <div class="mt-4 border-t border-black">
                    {#each eventPosts as post (post.id)}
                        <EventRow {post} />
                    {/each}
                </div>
            </section>
        {/if}

        {#if newsPosts.length === 0 && eventPosts.length === 0}
            <p class="mt-12 text-lg font-light text-neutral-500">Demnächst mehr.</p>
        {/if}
    </main>

    <Footer {config} {nav} />
</div>
