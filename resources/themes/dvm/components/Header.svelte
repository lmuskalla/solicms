<script lang="ts">
    import { onMount } from 'svelte';
    import { Link, page as inertiaPage } from '@inertiajs/svelte';
    import logoUrl from '../assets/images/logo.png';

    interface NavItem {
        label: string;
        href: string;
    }

    interface Props {
        config: Record<string, string>;
        nav?: NavItem[];
    }

    let { config, nav = [] }: Props = $props();

    let scrolledOut = $state(false);
    let menuOpen = $state(false);
    let headerEl: HTMLElement;

    onMount(() => {
        const onScroll = () => {
            scrolledOut = window.scrollY > (headerEl?.offsetHeight ?? 0);
        }
        window.addEventListener('scroll', onScroll);
        return () => window.removeEventListener('scroll', onScroll);
    })

</script>

<header bind:this={headerEl} id="header" class:scrolled-out={scrolledOut}>
    <div class="flex min-h-[64px] w-full items-center justify-between">
        <a href="/" title="Zur Startseite" class="flex-shrink-0 pl-4 md:pl-8">
            <img src={logoUrl} alt={config.site_name} class="h-12 w-auto" loading="eager" />
        </a>

        <button
            class="hamburger-btn"
            class:open={menuOpen}
            aria-label="Menü öffnen"
            aria-expanded={menuOpen}
            onclick={() => (menuOpen = !menuOpen)}
        >
            <span></span>
            <span></span>
            <span></span>
        </button>

        <nav class="header-menu" class:open={menuOpen}>
            <ul class="header-menu-list">
                {#each nav as item (item.href)}
                    <li class:current-menu-item={inertiaPage.url === item.href}>
                        <Link href={item.href} onclick={() => (menuOpen = false)}>{item.label}</Link>
                    </li>
                {/each}
            </ul>
        </nav>
    </div>
</header>
