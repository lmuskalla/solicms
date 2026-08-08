<script lang="ts">
    import Layout from '../../Components/Admin/Layout.svelte';
    import ConfigField from '../../Components/Admin/ConfigField.svelte';
    import type { Auth } from '../../types';

    interface SiteConfig {
        id: number;
        key: string;
        label: string;
        type: string;
        value: string | null;
    }

    interface Props {
        auth?: Auth;
        configs: SiteConfig[];
    }

    let { auth, configs }: Props = $props();
</script>

<svelte:head>
    <title>Einstellungen</title>
</svelte:head>

<Layout {auth}>
    {#snippet children()}
        <h1 class="text-2xl font-semibold tracking-tight text-admin-text">Einstellungen</h1>
        <p class="mt-1 text-sm text-admin-text-secondary">Grundlegende Angaben zu Ihrer Website.</p>

        <div class="mt-6 space-y-4">
            {#each configs as config (config.id)}
                <ConfigField {config} />
            {/each}
        </div>
    {/snippet}
</Layout>
