<script lang="ts">
    import SiteHeader from '../../js/Components/Frontend/SiteHeader.svelte';
    import SiteFooter from '../../js/Components/Frontend/SiteFooter.svelte';
    import SeoHead from '../../js/Components/Frontend/SeoHead.svelte';

    interface Props {
        page: { title: string };
        sections: Record<string, { value: string; type?: string; alt?: string | null }>;
        config: Record<string, string>;
        nav: Array<{ label: string; href: string }>;
        url?: string;
    }

    let { page, sections, config, nav, url }: Props = $props();

    // Hard-defined for this theme, not tenant-configurable — see SetupTenant's
    // comment on seedSiteConfig().
    const accent = '#2563eb';
</script>

<SeoHead title={`${page.title} — ${config.site_name ?? ''}`} {sections} {url} />

<div class="min-h-screen bg-white">
    <SiteHeader {config} {nav} />

    <section class="relative overflow-hidden">
        {#if sections.hero_image?.value}
            <img
                src={sections.hero_image.value}
                alt={sections.hero_image.alt || ''}
                class="absolute inset-0 h-full w-full object-cover opacity-10"
            />
        {/if}

        <div class="relative mx-auto max-w-5xl px-6 py-24 text-center sm:py-32">
            {#if sections.hero_text?.value}
                <h1 class="text-4xl font-semibold tracking-tight text-gray-900 sm:text-5xl">
                    {sections.hero_text.value}
                </h1>
            {/if}
            {#if sections.hero_subtext?.value}
                <p class="mx-auto mt-6 max-w-2xl text-lg text-gray-600">
                    {sections.hero_subtext.value}
                </p>
            {/if}
        </div>
    </section>

    {#if sections.intro_title?.value || sections.intro_body?.value}
        <section class="mx-auto max-w-3xl px-6 pb-24">
            {#if sections.intro_title?.value}
                <h2 class="text-2xl font-semibold tracking-tight text-gray-900" style={`color: ${accent}`}>
                    {sections.intro_title.value}
                </h2>
            {/if}
            {#if sections.intro_body?.value}
                <p class="mt-4 whitespace-pre-line text-gray-600">{sections.intro_body.value}</p>
            {/if}
        </section>
    {/if}

    <SiteFooter {config} />
</div>
