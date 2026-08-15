<script lang="ts">
    import Header from '../components/Header.svelte';
    import Footer from '../components/Footer.svelte';
    import SeoHead from '../../../js/Components/Frontend/SeoHead.svelte';
    import { sanitizeHtml } from '../../../js/lib/sanitizeHtml';
    import type { ThemeProps } from '../../../js/types';

    let { page, sections, config, nav, footerNav, url }: ThemeProps = $props();

    function slugify(text: string): string {
        return text
            .toLowerCase()
            .replace(/[äöüß]/g, (c) => ({ ä: 'ae', ö: 'oe', ü: 'ue', ß: 'ss' })[c] ?? c)
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    // Tags every h2/h3 in the editor's HTML with an id (deduped) and collects
    // them into a table of contents — long pages like "Über uns" and "Vision"
    // are real multi-section documents, not a single block of text, so an
    // in-page nav reflects structure that's actually there.
    const processed = $derived.by(() => {
        const raw = sections.body?.value ?? '';
        const seen = new Map<string, number>();
        const toc: { id: string; text: string; level: number }[] = [];

        const html = raw.replace(/<h([23])((?:\s[^>]*)?)>(.*?)<\/h\1>/gis, (match, level, attrs, inner) => {
            const text = inner.replace(/<[^>]+>/g, '').trim();
            if (!text) return match;

            let id = slugify(text) || 'abschnitt';
            const count = seen.get(id) ?? 0;
            seen.set(id, count + 1);
            if (count > 0) id = `${id}-${count + 1}`;

            toc.push({ id, text, level: Number(level) });
            return `<h${level}${attrs} id="${id}">${inner}</h${level}>`;
        });

        return { html: sanitizeHtml(html), toc };
    });
</script>

<SeoHead title={`${page?.title} — ${config.site_name ?? 'Gesundheitskollektiv Bremen'}`} {sections} {url} />

<div class="theme-geko min-h-screen bg-white">
    <Header {config} {nav} />

    <main id="main">
        <div class="mx-auto grid max-w-6xl gap-10 px-6 pt-14 pb-10 sm:pt-16 sm:pb-12 lg:grid-cols-[minmax(0,1fr)_16rem] lg:items-start">
            <div class="min-w-0">
                <h1 class="max-w-3xl text-4xl font-light leading-[1.1] tracking-tight text-[var(--geko-indigo)] sm:text-5xl">
                    {page?.title}
                </h1>
                <div class="mt-5 h-[3px] w-16 bg-[var(--geko-violet)]"></div>

                {#if processed.html}
                    <!-- eslint-disable svelte/no-at-html-tags -->
                    <div class="rich-text mt-8 max-w-[68ch]">{@html processed.html}</div>
                {/if}
            </div>

            {#if processed.toc.length >= 2}
                <!-- Reflowing this to the end on mobile (grid puts it after the
                     whole article there) would land it below the footer link —
                     useless for jumping around a page you've already scrolled
                     past. Desktop-only sidebar instead. -->
                <aside class="hidden lg:sticky lg:top-28 lg:block">
                    <nav aria-label="Auf dieser Seite" class="rounded-l-4xl bg-[var(--geko-violet-tint)] px-5 py-6">
                        <h2 class="text-xs font-medium tracking-wide text-[var(--geko-indigo)] uppercase">Auf dieser Seite</h2>
                        <div class="my-3 h-[2px] w-11/12 bg-[var(--geko-violet)]/40"></div>
                        <ul class="space-y-2.5 text-sm font-light text-neutral-700">
                            {#each processed.toc as item (item.id)}
                                <li class={item.level === 3 ? 'pl-3' : ''}>
                                    <a href={`#${item.id}`} class="hover:text-[var(--geko-violet)]">{item.text}</a>
                                </li>
                            {/each}
                        </ul>
                    </nav>
                </aside>
            {/if}
        </div>
    </main>

    <Footer {config} {footerNav} />
</div>
