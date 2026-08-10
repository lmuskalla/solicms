<script lang="ts">
    import Layout from '../../../Components/Admin/Layout.svelte';
    import NavList from './NavList.svelte';
    import type { Auth } from '../../../types';

    interface NavItemRow {
        id: number;
        type: 'page' | 'link';
        label: string;
        url: string | null;
        page: { id: number; title: string } | null;
    }

    interface PageOption {
        id: number;
        slug: string;
        title: string;
    }

    interface Props {
        auth?: Auth;
        headerItems: NavItemRow[];
        footerItems: NavItemRow[];
        pages: PageOption[];
        flash?: { success?: string | null };
    }

    let { auth, headerItems, footerItems, pages, flash }: Props = $props();
</script>

<svelte:head>
    <title>Navigation</title>
</svelte:head>

<Layout {auth}>
    {#snippet children()}
        <h1 class="text-2xl font-semibold tracking-tight text-admin-text">Navigation</h1>
        <p class="mt-1 text-sm text-admin-text-secondary">
            Stellen Sie zusammen, was in der Kopf- und Fußzeile Ihrer Website erscheint — Seiten und externe Links, in
            beliebiger Reihenfolge.
        </p>

        {#if flash?.success}
            <p class="mt-4 rounded-lg bg-admin-success/10 px-4 py-3 text-sm text-admin-success">{flash.success}</p>
        {/if}

        <div class="mt-6 space-y-6">
            <NavList title="Kopfzeilen-Navigation" menu="header" items={headerItems} {pages} />
            <NavList title="Fußzeilen-Navigation" menu="footer" items={footerItems} {pages} />
        </div>
    {/snippet}
</Layout>
