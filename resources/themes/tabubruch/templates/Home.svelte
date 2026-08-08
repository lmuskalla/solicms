<script lang="ts">
    import { onMount } from 'svelte';
    import Hero from '../components/Hero.svelte';
    import SectionNav from '../components/SectionNav.svelte';
    import SeoHead from '../../../js/Components/Frontend/SeoHead.svelte';
    import { sanitizeHtml } from '../../../js/lib/sanitizeHtml';
    import type { ThemeProps } from '../../../js/types';

    let { sections, config, url }: ThemeProps = $props();

    // Fixed, developer-defined order matching config/themes.php's declared
    // schema for this template — not an editor-addable/removable collection.
    // The design hardcodes a color per position (see style.css's
    // `#content section:nth-of-type(N)`), so this can only ever be this
    // exact list; see the conversation this theme was built from for why
    // that ruled out reusing the Post/'posts'-section mechanism.
    const contentSections = [
        { key: 'ueber_mich', label: 'Über mich' },
        { key: 'konfliktmoderation', label: 'Konfliktmoderation' },
        { key: 'diskriminierungssensibles_coaching', label: 'Diskriminierungssensibles Coaching' },
        { key: 'rassismuskritische_beratung', label: 'Rassismuskritische Beratung' },
        { key: 'fragen_und_antworten', label: 'Fragen und Antworten' },
        { key: 'impressum', label: 'Impressum' },
    ];

    let shrunk = $state(false);
    let logoWidth = $state(80);
    let subtitleVisible = $state(false);
    let subtitleFast = $state(false);
    let mobileMenuOpen = $state(false);
    let navFixed = $state(false);
    let selectedKey = $state('');

    let contentEl: HTMLDivElement | undefined = $state();
    const sectionEls: Record<string, HTMLElement> = {};

    function registerSection(node: HTMLElement, key: string): { destroy: () => void } {
        sectionEls[key] = node;
        return { destroy: () => delete sectionEls[key] };
    }

    function toggleMobileMenu(): void {
        mobileMenuOpen = !mobileMenuOpen;
    }

    // Subtitle fades in 2.5s after load, then its own transition speeds up
    // a second later — matches the source's identical two-stage timeout.
    onMount(() => {
        const revealTimer = setTimeout(() => {
            subtitleVisible = true;
            setTimeout(() => (subtitleFast = true), 1000);
        }, 2500);

        return () => clearTimeout(revealTimer);
    });
</script>

{#snippet contentBlock(section: { key: string; label: string }, isFirst: boolean)}
    <section id={section.key} use:registerSection={section.key}>
        {#if isFirst && sections.ueber_mich_portrait?.value}
            <div class="portrait">
                <img src={sections.ueber_mich_portrait.value} alt={sections.ueber_mich_portrait.alt || ''} />
            </div>
        {/if}
        <h2>{section.label}</h2>
        {#if sections[section.key]?.value}
            <!-- eslint-disable svelte/no-at-html-tags -->
            <div class="rich-text">{@html sanitizeHtml(sections[section.key].value)}</div>
        {/if}
    </section>
{/snippet}

<SeoHead title={config.site_name ?? 'Tabubruch Beratung'} {sections} {url} />

<svelte:window
    onscroll={() => {
        const scrollY = window.scrollY;

        if (scrollY > 0) {
            logoWidth = Math.max(80 - scrollY * 0.19, 20);
            shrunk = logoWidth <= 20;
        } else {
            logoWidth = 80;
            shrunk = false;
        }

        if (contentEl) {
            navFixed = contentEl.getBoundingClientRect().top < 100;
        }

        for (const { key } of contentSections) {
            const el = sectionEls[key];
            if (!el) continue;
            const rect = el.getBoundingClientRect();
            if (rect.top < window.innerHeight && rect.bottom > 0) {
                selectedKey = key;
            }
        }
    }}
/>

<div class="theme-tabubruch">
    <a href="#ueber_mich" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-white focus:px-4 focus:py-2">
        Zum Inhalt springen
    </a>

    <Hero
        tagline={sections.tagline?.value ?? ''}
        {shrunk}
        {subtitleVisible}
        {subtitleFast}
        {mobileMenuOpen}
        sections={contentSections}
        contactEmail={config.contact_email ?? ''}
        {logoWidth}
        onToggleMenu={toggleMobileMenu}
    />

    <main id="primary">
        <SectionNav sections={contentSections} {selectedKey} fixed={navFixed} />

        <div id="content" bind:this={contentEl}>
            {#each contentSections as section, i (section.key)}
                {@render contentBlock(section, i === 0)}
            {/each}
        </div>
    </main>
</div>
