<script lang="ts">
    import { Link } from '@inertiajs/svelte';

    interface NavItem {
        label: string;
        href: string;
    }

    interface Props {
        config: Record<string, string>;
        footerNav?: NavItem[];
    }

    let { config, footerNav = [] }: Props = $props();
</script>

<footer class="border-t border-gray-100 bg-gray-50">
    <div class="mx-auto max-w-5xl px-6 py-10 text-sm text-gray-500">
        {#if footerNav.length > 0}
            <nav class="mb-4 flex flex-wrap gap-x-6 gap-y-2">
                {#each footerNav as item (item.href)}
                    <Link href={item.href} class="hover:text-gray-900">{item.label}</Link>
                {/each}
            </nav>
        {/if}
        {#if config.footer_text}
            <p>{config.footer_text}</p>
        {/if}
        {#if config.contact_email}
            <p class="mt-1">
                <a href={`mailto:${config.contact_email}`} class="hover:text-gray-900">{config.contact_email}</a>
            </p>
        {/if}
    </div>
</footer>
