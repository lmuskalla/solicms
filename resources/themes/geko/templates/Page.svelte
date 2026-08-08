<script lang="ts">
    import Header from '../components/Header.svelte';
    import Footer from '../components/Footer.svelte';
    import SeoHead from '../../../js/Components/Frontend/SeoHead.svelte';
    import { sanitizeHtml } from '../../../js/lib/sanitizeHtml';
    import type { ThemeProps } from '../../../js/types';

    let { page, sections, config, nav, url }: ThemeProps = $props();
</script>

<SeoHead title={`${page?.title} — ${config.site_name ?? 'Gesundheitskollektiv Bremen'}`} {sections} {url} />

<div class="theme-geko min-h-screen bg-white">
    <Header {config} {nav} />

    <main id="main">
        <article class="mx-auto max-w-6xl px-6 py-16">
            <h1 class="text-4xl font-light tracking-tight text-[var(--geko-indigo)] sm:text-4xl">{page?.title}</h1>

            {#if sections.body?.value}
                <!-- eslint-disable svelte/no-at-html-tags -->
                <div class="rich-text mt-8">{@html sanitizeHtml(sections.body.value)}</div>
            {/if}
        </article>
    </main>

    <Footer {config} {nav} />
</div>
