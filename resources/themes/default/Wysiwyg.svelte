<script lang="ts">
    import SiteHeader from '../../js/Components/Frontend/SiteHeader.svelte';
    import SiteFooter from '../../js/Components/Frontend/SiteFooter.svelte';
    import SeoHead from '../../js/Components/Frontend/SeoHead.svelte';
    import { sanitizeHtml } from '../../js/lib/sanitizeHtml';

    interface Props {
        page: { title: string };
        sections: Record<string, { value: string; type?: string }>;
        config: Record<string, string>;
        nav: Array<{ label: string; href: string }>;
        url?: string;
    }

    let { page, sections, config, nav, url }: Props = $props();
</script>

<SeoHead title={`${page.title} — ${config.site_name ?? ''}`} {sections} {url} />

<div class="min-h-screen bg-white">
    <SiteHeader {config} {nav} />

    <article class="mx-auto max-w-3xl px-6 py-16">
        <h1 class="text-3xl font-semibold tracking-tight text-gray-900">{page.title}</h1>

        {#if sections.body?.value}
            <!-- eslint-disable svelte/no-at-html-tags -->
            <div class="prose prose-gray mt-8 max-w-none">{@html sanitizeHtml(sections.body.value)}</div>
        {/if}
    </article>

    <SiteFooter {config} />
</div>
