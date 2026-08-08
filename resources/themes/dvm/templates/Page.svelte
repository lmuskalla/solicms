<script lang="ts">
    import { onMount } from 'svelte';
    import Header from '../components/Header.svelte';
    import Footer from '../components/Footer.svelte';
    import HomeBubbles from '../components/HomeBubbles.svelte';
    import logoUrl from '../assets/images/logo.png';
    import SeoHead from '../../../js/Components/Frontend/SeoHead.svelte';
    import { sanitizeHtml } from '../../../js/lib/sanitizeHtml';

    interface Props {
        page: { title: string };
        sections: Record<string, { value: string; type?: string }>;
        config: Record<string, string>;
        nav: Array<{ label: string; href: string }>;
        url?: string;
    }

    let { page, sections, config, nav, url }: Props = $props();

    // Mirrors header.php's own scroll listener, which also toggles
    // .scrolled-out on #page so content isn't hidden under the fixed header.
    let scrolledOut = $state(false);

    onMount(() => {
        const onScroll = () => {
            scrolledOut = window.scrollY > (document.getElementById('header')?.offsetHeight ?? 0);
        }
        window.addEventListener('scroll', onScroll);
        return () => window.removeEventListener('scroll', onScroll);
    })
</script>

<SeoHead title={`${page.title} — ${config.site_name}`} {sections} {url} />

<div class="relative">
    <Header {config} {nav} />
    <HomeBubbles />

    <img
        src={logoUrl}
        alt=""
        aria-hidden="true"
        class="absolute bottom-[-200px] right-[-300px] opacity-[0.12]"
        style="width: 35vw; height: 35vw;"
    />

    <div class="page relative" id="page" class:scrolled-out={scrolledOut}>
        <div class="absolute h-[2px] w-full bg-baseAccent top-12 left-40"></div>

        <article class="max-w-screen-lg mx-auto mt-20 px-4">
            <div class="relative">
                <h1 class="text-3xl font-semibold">{page.title}</h1>
                <div class="mt-4 relative py-4">
                    {#if sections.body?.value}
                        <!-- eslint-disable svelte/no-at-html-tags -->
                        <div class="dvm-prose entry-content">{@html sanitizeHtml(sections.body.value)}</div>
                    {/if}
                </div>
            </div>
        </article>
    </div>

    <Footer {config} {nav} />
</div>
