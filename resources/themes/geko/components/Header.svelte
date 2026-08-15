<script lang="ts">
    import { Link, page as inertiaPage } from '@inertiajs/svelte';
    import logoUrl from '../assets/images/logo.svg';
    import type { ThemeProps } from '../../../js/types';

    let { config, nav = [] }: Pick<ThemeProps, 'config' | 'nav'> = $props();

    // Nav is admin-editable (Admin/Navigation), so it can outgrow "fits in
    // one row next to the logo" — collapse behind a hamburger below md
    // instead of letting it wrap under the logo indefinitely.
    let open = $state(false);

    // Header is sticky at all times (logo + burger stay reachable while
    // scrolling); the border/shadow only kick in once the page has actually
    // scrolled, so it doesn't draw a stray line under the header at rest.
    let scrolled = $state(false);
    function onScroll() {
        scrolled = window.scrollY > 4;
    }
</script>

<svelte:window onscroll={onScroll} />

<header
    class="sticky top-0 z-50 border-b bg-white/95 backdrop-blur-sm transition-shadow {scrolled
        ? 'border-neutral-200 shadow-sm'
        : 'border-transparent'}"
>
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-6">
        <a href="/" title="Zur Startseite" class="flex-shrink-0">
            <img src={logoUrl} alt={config.site_name ?? 'Gesundheitskollektiv Bremen'} class="h-14 w-auto" />
        </a>

        <nav aria-label="Hauptnavigation" class="hidden md:block">
            <ul class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm font-light text-neutral-800">
                {#each nav as item (item.href)}
                    <li>
                        <Link
                            href={item.href}
                            class="rounded-sm text-lg hover:text-[var(--geko-violet)] {inertiaPage.url === item.href
                                ? 'text-[var(--geko-violet)]'
                                : ''}"
                            aria-current={inertiaPage.url === item.href ? 'page' : undefined}
                        >
                            {item.label}
                        </Link>
                    </li>
                {/each}
            </ul>
        </nav>

        <button
            type="button"
            class="flex h-10 w-10 flex-shrink-0 flex-col items-center justify-center gap-1.5 rounded-sm md:hidden"
            aria-expanded={open}
            aria-controls="mobile-nav"
            aria-label={open ? 'Menü schließen' : 'Menü öffnen'}
            onclick={() => (open = !open)}
        >
            <span class="block h-0.5 w-6 bg-neutral-900 transition-transform {open ? 'translate-y-2 rotate-45' : ''}"></span>
            <span class="block h-0.5 w-6 bg-neutral-900 transition-opacity {open ? 'opacity-0' : ''}"></span>
            <span class="block h-0.5 w-6 bg-neutral-900 transition-transform {open ? '-translate-y-2 -rotate-45' : ''}"></span>
        </button>
    </div>

    {#if open}
        <nav id="mobile-nav" aria-label="Hauptnavigation" class="border-t border-neutral-200 px-6 py-4 md:hidden">
            <ul class="flex flex-col gap-4 text-lg font-light text-neutral-800">
                {#each nav as item (item.href)}
                    <li>
                        <Link
                            href={item.href}
                            class="rounded-sm hover:text-[var(--geko-violet)] {inertiaPage.url === item.href ? 'text-[var(--geko-violet)]' : ''}"
                            aria-current={inertiaPage.url === item.href ? 'page' : undefined}
                            onclick={() => (open = false)}
                        >
                            {item.label}
                        </Link>
                    </li>
                {/each}
            </ul>
        </nav>
    {/if}
</header>
