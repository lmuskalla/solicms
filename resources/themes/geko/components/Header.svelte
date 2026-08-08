<script lang="ts">
    import { Link, page as inertiaPage } from '@inertiajs/svelte';
    import logoUrl from '../assets/images/logo.svg';
    import type { ThemeProps } from '../../../js/types';

    let { config, nav = [] }: Pick<ThemeProps, 'config' | 'nav'> = $props();
</script>

<header>
    <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-6 py-6">
        <a href="/" title="Zur Startseite" class="flex-shrink-0">
            <img src={logoUrl} alt={config.site_name ?? 'Gesundheitskollektiv Bremen'} class="h-14 w-auto" />
        </a>

        <nav aria-label="Hauptnavigation">
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
    </div>
</header>
