<script lang="ts">
    import Header from '../components/Header.svelte';
    import Footer from '../components/Footer.svelte';
    import { router } from '@inertiajs/svelte';
    import logoUrl from '../assets/images/logo.png';
    import SeoHead from '../../../js/Components/Frontend/SeoHead.svelte';
    import { sanitizeHtml } from '../../../js/lib/sanitizeHtml';

    interface Props {
        sections: Record<string, { value: string; type?: string }>;
        config: Record<string, string>;
        nav: Array<{ label: string; href: string }>;
        footerNav: Array<{ label: string; href: string }>;
        url?: string;
    }

    let { sections, config, nav, footerNav, url }: Props = $props();

    // Three numbered narrative blocks — background classes and order mirror the
    // original theme's template-homepage.php (bg-baseAccent, bg-white, bg-logoBeige).
    // Each has a short teaser and an optional expandable "read more" block, exactly
    // like the source theme's content_N / content_N_readmore ACF field pairs.
    const blocks = $derived(
        [
            {
                num: '01',
                bg: 'bg-baseAccent',
                teaser: sections.section_1_body?.value,
                readmore: sections.section_1_readmore?.value,
            },
            {
                num: '02',
                bg: 'bg-white',
                teaser: sections.section_2_body?.value,
                readmore: sections.section_2_readmore?.value,
            },
            {
                num: '03',
                bg: 'bg-logoBeige',
                teaser: sections.section_3_body?.value,
                readmore: sections.section_3_readmore?.value,
            },
        ].filter((b) => b.teaser),
    );

    let expanded = $state<Record<string, boolean>>({});
    function toggle(num: string): void {
        expanded[num] = !expanded[num];
    }
</script>

<SeoHead title={config.site_name ?? ''} {sections} {url} />

<div>
    <Header {config} {nav} />

    <article class="homepage">
        <section class="hero-section relative overflow-hidden">
            <div class="bubble bg-blueAccent animate-float hero-bubble-1"><span>Menschenrechte</span></div>
            <div class="bubble bg-redAccent animate-float-delayed hero-bubble-2"><span>Demokratie</span></div>
            <div class="bubble bg-greenAccent animate-float-slow hero-bubble-3"><span>Vielfalt</span></div>
            <img src={logoUrl} alt="" aria-hidden="true" class="hero-bubble-outline absolute opacity-[0.12]" />

            <div class="banner relative z-10 my-16 px-4 md:px-8 lg:px-32">
                {#if sections.hero_text?.value}
                    <h1>{sections.hero_text.value}</h1>
                {/if}
                {#if sections.hero_subtext?.value}
                    <p>{sections.hero_subtext.value}</p>
                {/if}
                <button
                    type="button"
                    class="button-primary mt-12 inline-block"
                    onclick={() => router.visit('/jetzt-beitreten')}
                >
                    Jetzt beitreten!
                </button>
            </div>
        </section>

        {#each blocks as block (block.num)}
            <section class="{block.bg} relative overflow-hidden">
                <span class="section-num" aria-hidden="true">{block.num}</span>
                <div class="teaser">
                    <!-- eslint-disable svelte/no-at-html-tags -->
                    <div class="dvm-prose">{@html sanitizeHtml(block.teaser)}</div>

                    {#if block.readmore}
                        <button type="button" class="button-secondary mt-8" onclick={() => toggle(block.num)}>
                            {expanded[block.num] ? 'Weniger anzeigen' : 'Mehr lesen'}
                        </button>
                    {/if}
                </div>

                {#if block.readmore}
                    <div class="read-more dvm-prose" class:hidden={!expanded[block.num]}>
                        <!-- eslint-disable svelte/no-at-html-tags -->
                        {@html sanitizeHtml(block.readmore)}
                        <button type="button" class="button-secondary mt-6" onclick={() => toggle(block.num)}>
                            Weniger anzeigen
                        </button>
                    </div>
                {/if}
            </section>
        {/each}
    </article>

    <Footer {config} {footerNav} />
</div>
