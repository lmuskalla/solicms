<script lang="ts">
    import { Link, page as inertiaPage } from '@inertiajs/svelte';

    interface NavItem {
        label: string;
        href: string;
    }

    interface Props {
        config: Record<string, string>;
        nav?: NavItem[];
    }

    let { config, nav = [] }: Props = $props();
</script>

<header class="border-b border-gray-100 bg-white/80 backdrop-blur">
    <div class="mx-auto flex max-w-5xl items-center justify-between px-6 py-5">
        <Link href="/" class="flex items-center gap-3">
            <span class="text-lg font-semibold tracking-tight text-gray-900">{config.site_name}</span>
        </Link>

        {#if nav.length > 0}
            <nav class="flex items-center gap-6">
                {#each nav as item (item.href)}
                    {@const active = inertiaPage.url === item.href}
                    <Link
                        href={item.href}
                        class="text-sm font-medium transition-colors {active
                            ? 'text-gray-900'
                            : 'text-gray-500 hover:text-gray-900'}"
                    >
                        {item.label}
                    </Link>
                {/each}
            </nav>
        {/if}
    </div>
</header>
