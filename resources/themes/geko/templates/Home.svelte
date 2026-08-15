<script lang="ts">
    import Header from '../components/Header.svelte';
    import Footer from '../components/Footer.svelte';
    import NewsCard from '../components/NewsCard.svelte';
    import EventRow from '../components/EventRow.svelte';
    import PlaceholderPhoto from '../components/PlaceholderPhoto.svelte';
    import { Link } from '@inertiajs/svelte';
    import SeoHead from '../../../js/Components/Frontend/SeoHead.svelte';
    import type { ThemeProps } from '../../../js/types';

    let { sections, config, nav, footerNav, url }: ThemeProps = $props();

    // Arabic-script greetings (Arabic and Farsi alike — the CI names both in
    // style.css) get lang="ar" so the theme's [lang='ar'], [lang='fa'] rule
    // switches them to Noto Sans Arabic; Inter carries no Arabic glyphs and
    // would render tofu. The lang attribute also makes the browser lay them
    // out RTL. Detected by script, since ar vs fa can't be told apart from
    // the Unicode block alone and the CSS treats them identically anyway.
    function greetingLang(text: string): string | undefined {
        return /[\u0600-\u06FF]/.test(text) ? 'ar' : undefined;
    }
    
    // Herzlich Willkommen Welcome!, ¡Bienvenidos!, Serdecznie witamy!, Добро пожаловать!, أهلاً وسهلاً!, Hoş geldiniz!, خوش آمدید!, Bi xêr hatî!, Bienvenue!, Dobrodošli!, Ласкаво просимо!
    // Rendered as a wrapped "welcome wall" of badges (see below), not scattered
    // ghost text — a fixed-height band can't drift into the cards/news/footer
    // as the page grows, unlike absolute-positioned text pinned by page %.
    const greetings = [
        'Herzlich Willkommen!',
        'Welcome!',
        '¡Bienvenidos!',
        'Serdecznie witamy!',
        'Добро пожаловать!',
        'أهلاً وسهلاً!',
        'Hoş geldiniz!',
        'خوش آمدید!',
        'Bi xêr hatî!',
        'Bienvenue!',
        'Dobrodošli!',
        'Ласкаво просимо!',
    ];

    // Slight per-badge tilt (deg) — a hand-placed, sticker-wall feel rather
    // than a perfectly aligned grid.
    const tilts = [-2, 1, -1, 2, 0, -2, 1, -1, 2, 0, -1, 1];

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

<div class="theme-geko relative min-h-screen bg-white">
    <Header {config} {nav} />

    <main id="main" class="mx-auto max-w-6xl px-6">
        <!-- Welcome wall: every greeting as its own tilted badge, alternating
             the two brand colours — reads as hand-placed multilingual welcome
             signage (the kind on a real community centre's door), not ambient
             background text. -->
        <section class="mt-6 flex flex-wrap gap-1.5" aria-label="Willkommen in vielen Sprachen">
            {#each greetings as g, i (i)}
                <span
                    lang={greetingLang(g)}
                    class="inline-block rounded-full px-2.5 py-1 text-xs font-medium sm:text-sm {i % 2 === 0
                        ? 'bg-[var(--geko-violet-tint)] text-[var(--geko-violet)]'
                        : 'bg-[#fff3e0] text-[#b5790a]'}"
                    style:transform={`rotate(${tilts[i]}deg)`}
                >
                    {g}
                </span>
            {/each}
        </section>

        <!-- Hero: headline left, photo right — matches the brand concept's
             asymmetric layout, not a centered hero. -->
        <section class="mt-6 flex gap-4 max-lg:flex-col">
            <div class="w-2/3 max-lg:w-full flex flex-col items-start justify-end relative">
                <h1
                    class="w-full text-4xl font-light leading-[1.1] tracking-tight text-neutral-900 sm:w-11/12 sm:text-5xl sm:leading-[1.1] lg:text-6xl lg:leading-18"
                >
                    {sections.hero_text?.value || 'Stadtteilgesundheitszentrum in der Überseestadt'}
                </h1>
                <Link
                    href="/ueber-uns"
                    class="mt-4 self-end rounded-l-full bg-[var(--geko-violet)] px-4 py-2 text-xs font-medium text-white shadow-md transition-opacity hover:opacity-90 sm:text-sm lg:absolute lg:right-0 lg:mt-0 lg:self-auto"
                >
                    Mehr Infos zu unserem Projekt
                </Link>
            </div>

            <div class="w-1/3 max-lg:mt-4 max-lg:w-full">
                <div class="aspect-[4/3] overflow-hidden rounded-l-4xl bg-[var(--geko-violet-tint)] max-lg:aspect-[16/9] max-lg:rounded-4xl">
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
                <h2 class="text-3xl font-light sm:text-4xl">Nächster Termin</h2>
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
                <h2 class="text-3xl font-light text-[var(--geko-indigo)] sm:text-4xl">Unser Kollektiv</h2>
                <div class="my-4 h-[2px] w-11/12 bg-[var(--geko-violet)]/40"></div>
                {#if sections.intro_body?.value}
                    <p class="mt-2 whitespace-pre-line text-sm font-light text-neutral-700">{sections.intro_body.value}</p>
                {/if}
                <div class="mt-4 flex flex-wrap gap-1 w-full justify-end">
                    <span class="rounded-l-full bg-[var(--geko-violet)] px-3 py-[6px] text-sm font-light text-white">Mehrspraching</span>
                    <span class="rounded-l-full bg-[var(--geko-violet)] px-3 py-[6px] text-sm font-light text-white">Kostenlos</span>
                    <span class="rounded-l-full bg-[var(--geko-violet)] px-3 py-[6px] text-sm font-light text-white">Nachbarschaftlich</span>
                </div>
            </div>
        </section>

        {#if newsPosts.length > 0}
            <section class="mt-8">
                <h2 class="text-3xl font-light text-neutral-900 sm:text-4xl">News</h2>
                <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-3">
                    {#each newsPosts as post (post.id)}
                        <NewsCard {post} />
                    {/each}
                </div>
            </section>
        {/if}

        {#if eventPosts.length > 0}
            <section class="mt-8">
                <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                    <h2 class="text-3xl font-light text-neutral-900 sm:text-4xl">Aktuelle Termine</h2>
                    <Link href="/aktuelles" class="text-sm font-medium whitespace-nowrap text-[var(--geko-violet)] hover:underline">
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

        <section class="my-8 grid grid-cols-2 gap-3 md:grid-cols-4 md:gap-4">
            {#each values as v (v)}
                <div class="flex min-h-[4.5rem] min-w-0 items-center rounded-r-4xl bg-[var(--geko-violet)] p-3 sm:min-h-[7rem] sm:p-6">
                    <span class="break-words font-light text-white text-sm sm:text-2xl">{v}</span>
                </div>
            {/each}
        </section>
    </main>

    <Footer {config} {footerNav} />
</div>
