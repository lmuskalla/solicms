<script lang="ts">
    import Header from '../components/Header.svelte';
    import Footer from '../components/Footer.svelte';
    import NewsCard from '../components/NewsCard.svelte';
    import EventRow from '../components/EventRow.svelte';
    import PlaceholderPhoto from '../components/PlaceholderPhoto.svelte';
    import { Link } from '@inertiajs/svelte';
    import SeoHead from '../../../js/Components/Frontend/SeoHead.svelte';
    import type { ThemeProps } from '../../../js/types';

    let { sections, config, nav, url }: ThemeProps = $props();

    // Brand-vision tags, straight from the CI's own wording ("solidarischen,
    // multiprofessionellen, queerfeministischen Gesundheitsversorgung") —
    // fixed, not per-post editor content.
    const values = ['Solidarisch', 'Queerfeministisch', 'Multiprofessionell', 'Gemeinschaftlich'];

    // The newest 4 of Aktuelles' own posts, not a separate list — see
    // 'news_preview' in theme.php (type: 'posts_ref'). Every post, not
    // only ones with a photo yet — a post without one still shows as a
    // card with PlaceholderPhoto (see below), it doesn't just vanish.
    const newsPosts = $derived(sections.news_preview?.posts ?? []);
    const eventPosts = $derived(
        (sections.termine?.posts ?? []).slice().sort((a, b) => (a.starts_at ?? '').localeCompare(b.starts_at ?? '')),
    );
    const nextEvent = $derived(eventPosts.find((p) => p.starts_at && new Date(p.starts_at) >= new Date()) ?? eventPosts[0]);
</script>

<SeoHead title={config.site_name ?? 'Gesundheitskollektiv Bremen'} {sections} {url} />

<div class="theme-geko min-h-screen bg-white">
    <Header {config} {nav} />

    <main id="main" class="mx-auto max-w-6xl px-6">
        <!-- Hero: headline left, photo right — matches the brand concept's
             asymmetric layout, not a centered hero. -->
        <section class="flex gap-4 max-lg:flex-col">
            <div class="w-2/3 flex flex-col items-start justify-end relative">
                <h1 class="text-6xl font-light leading-18 tracking-tight text-neutral-900 sm:text-5xl lg:text-6xl w-11/12">
                    {sections.hero_text?.value || 'Stadtteilgesundheitszentrum in der Überseestadt'}
                </h1>
                <Link
                    href="/ueber-uns"
                    class="absolute right-0 rounded-l-full bg-[var(--geko-violet)] px-4 py-2 text-xs font-medium text-white shadow-md transition-opacity hover:opacity-90 sm:text-sm"
                >
                    Mehr Infos zu unserem Projekt
                </Link>
            </div>

            <div class="w-1/3">
                <div class="aspect-[4/3] overflow-hidden rounded-l-4xl bg-[var(--geko-violet-tint)]">
                    {#if sections.hero_image?.value}
                        <img src={sections.hero_image.value} alt={sections.hero_image.alt || ''} class="h-full w-full object-cover" />
                    {:else}
                        <PlaceholderPhoto />
                    {/if}
                </div>
            </div>
        </section>

        <!-- Two-card row: solid "Nächster Termin" + light-tint "Unser Kollektiv".
             Directly under the hero, no subtext/intervening content — matches
             the concept screenshots' tight rhythm. hero_subtext is still a
             real, editable section (Admin/Pages/Edit); this template just
             doesn't have a slot for it — see conversation with Leo. -->
        <section class="mt-10 grid gap-3 sm:grid-cols-[minmax(0,1fr)_2fr]">
            <div class="rounded-r-4xl bg-[var(--geko-violet)] px-5 py-6 text-white">
                <h2 class="text-4xl font-light">Nächster Termin</h2>
                <div class="my-4 h-[2px] w-11/12 bg-white/40"></div>
                {#if nextEvent}
                    <p class="text-2xl font-light leading-10">
                        {new Date(nextEvent.starts_at ?? '').toLocaleDateString('de-DE', { weekday: 'long' })}<br />
                        {new Date(nextEvent.starts_at ?? '').toLocaleDateString('de-DE', {
                            day: '2-digit',
                            month: '2-digit',
                            year: 'numeric',
                        })}<br />
                        {new Date(nextEvent.starts_at ?? '').toLocaleTimeString('de-DE', {
                            hour: '2-digit',
                            minute: '2-digit',
                        })} Uhr
                    </p>
                {:else}
                    <p class="text-sm font-light text-white/80">Demnächst mehr.</p>
                {/if}
            </div>

            <div class="rounded-l-4xl bg-[var(--geko-violet-tint)] px-5 py-6">
                <h2 class="text-4xl font-light text-[var(--geko-indigo)]">Unser Kollektiv</h2>
                <div class="my-4 h-[2px] w-11/12 bg-[var(--geko-violet)]/40"></div>
                {#if sections.intro_body?.value}
                    <p class="mt-2 whitespace-pre-line text-sm font-light text-neutral-700">{sections.intro_body.value}</p>
                {/if}
                <div class="mt-4 flex flex-wrap gap-1 w-full justify-end">
                    <span class="rounded-l-full bg-[var(--geko-violet)] px-3 py-[6px] text-sm font-light text-white">Gemeinschaft</span>
                    <span class="rounded-l-full bg-[var(--geko-violet)] px-3 py-[6px] text-sm font-light text-white">Gesundheit</span>
                </div>
            </div>
        </section>

        {#if newsPosts.length > 0}
            <section class="mt-8">
                <h2 class="text-4xl font-light text-neutral-900">News</h2>
                <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-3">
                    {#each newsPosts as post (post.id)}
                        <NewsCard {post} />
                    {/each}
                </div>
            </section>
        {/if}

        {#if eventPosts.length > 0}
            <section class="mt-8">
                <div class="flex items-center justify-between">
                    <h2 class="text-4xl font-light text-neutral-900">Aktuelle Termine</h2>
                    <Link href="/aktuelles" class="text-sm font-medium text-[var(--geko-violet)] hover:underline">
                        Alle Termine ansehen →
                    </Link>
                </div>
                <div class="mt-4 border-t border-black">
                    {#each eventPosts as post (post.id)}
                        <EventRow {post} />
                    {/each}
                </div>
            </section>
        {/if}

        <section class="my-8 grid grid-cols-2 gap-4 sm:grid-cols-4">
            {#each values as v (v)}
                <div class="flex min-h-[7rem] items-center rounded-r-4xl bg-[var(--geko-violet)] p-6">
                    <span class="font-light text-white text-2xl">{v}</span>
                </div>
            {/each}
        </section>
    </main>

    <Footer {config} {nav} />
</div>
