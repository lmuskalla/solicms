<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import iconUrl from '../assets/images/icon.svg';
    import type { ThemeProps } from '../../../js/types';

    let { config, nav = [] }: Pick<ThemeProps, 'config' | 'nav'> = $props();
</script>

<footer class="bg-[var(--geko-indigo)] text-white">
    <div class="mx-auto flex max-w-6xl flex-col gap-8 px-6 py-14 md:flex-row md:justify-between">
        <div class="max-w-sm">
            <a href="/" title="Zur Startseite" class="inline-flex items-center gap-3">
                <img src={iconUrl} alt="" aria-hidden="true" class="h-10 w-auto" />
                <span class="font-['Inter_Tight'] text-xl font-normal leading-tight">
                    Gesundheitskollektiv<br />Bremen e.V.
                </span>
            </a>
            {#if config.footer_text}
                <p class="mt-4 text-base text-white/70">{config.footer_text}</p>
            {/if}
        </div>

        <nav aria-label="Fußzeilennavigation">
            <ul class="flex flex-col gap-2 text-base text-white/80">
                {#each nav as item (item.href)}
                    <li><Link href={item.href} class="hover:text-white">{item.label}</Link></li>
                {/each}
                {#if config.contact_email}
                    <li><a href={`mailto:${config.contact_email}`} class="hover:text-white">{config.contact_email}</a></li>
                {/if}
            </ul>
        </nav>
    </div>

    <div class="border-t border-white/10 px-6 py-5 text-center text-xs text-white/50">
        © {new Date().getFullYear()} Gesundheitskollektiv Bremen e.V.
    </div>
</footer>
