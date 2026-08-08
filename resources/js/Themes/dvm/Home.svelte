<script lang="ts">
    import Header from './Header.svelte'
    import Footer from './Footer.svelte'

    let { sections, config, nav } = $props();

    const logoUrl = 'https://www.xn--dvm-bndnis-eeb.de/wp-content/themes/dvm/dist/assets/6.png';

    // Three numbered narrative blocks — background classes and order mirror the
    // original theme's template-homepage.php (bg-baseAccent, bg-white, bg-logoBeige).
    // Each has a short teaser and an optional expandable "read more" block, exactly
    // like the source theme's content_N / content_N_readmore ACF field pairs.
    const blocks = $derived(
        [
            {
                num: '01',
                bg: 'bg-baseAccent',
                teaser: sections.intro_body?.value,
                readmore: sections.intro_readmore?.value,
                html: false,
            },
            {
                num: '02',
                bg: 'bg-white',
                teaser: sections.section_2_body?.value,
                readmore: sections.section_2_readmore?.value,
                html: true,
            },
            {
                num: '03',
                bg: 'bg-logoBeige',
                teaser: sections.section_3_body?.value,
                readmore: sections.section_3_readmore?.value,
                html: true,
            },
        ].filter((b) => b.teaser),
    );

    let expanded = $state({});
    function toggle(num) {
        expanded[num] = !expanded[num];
    }
</script>

<svelte:head>
    <title>{sections.hero_text?.value ?? config.site_name} — {config.site_name}</title>
</svelte:head>

<div class="dvm-theme">
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
                <a href="mailto:{config.contact_email}" class="button-primary mt-12 inline-block">
                    Jetzt beitreten!
                </a>
            </div>
        </section>

        {#each blocks as block (block.num)}
            <section class="{block.bg} relative overflow-hidden">
                <span class="section-num" aria-hidden="true">{block.num}</span>
                <div class="teaser">
                    {#if block.html}
                        <!-- eslint-disable svelte/no-at-html-tags -->
                        <div class="dvm-prose">{@html block.teaser}</div>
                    {:else}
                        <p>{block.teaser}</p>
                    {/if}

                    {#if block.readmore}
                        <button type="button" class="button-secondary mt-8" onclick={() => toggle(block.num)}>
                            {expanded[block.num] ? 'Weniger anzeigen' : 'Mehr lesen'}
                        </button>
                    {/if}
                </div>

                {#if block.readmore}
                    <div class="read-more dvm-prose" class:hidden={!expanded[block.num]}>
                        <!-- eslint-disable svelte/no-at-html-tags -->
                        {@html block.readmore}
                        <button type="button" class="button-secondary mt-6" onclick={() => toggle(block.num)}>
                            Weniger anzeigen
                        </button>
                    </div>
                {/if}
            </section>
        {/each}
    </article>

    <Footer {config} {nav} />
</div>
