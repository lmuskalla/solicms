<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';

    interface Props {
        config: {
            id: number;
            key: string;
            label: string;
            type: string;
            value: string | null;
        };
    }

    let { config }: Props = $props();
    // Deliberately captured once: this component is remounted per config.id
    // ({#each ... (config.id)}), so the initial value is all it needs.
    let value = $state(untrack(() => config.value ?? ''));
    let saving = $state(false);
    let saved = $state(false);

    const inputClasses =
        'w-full rounded-lg border border-admin-border px-3.5 py-2.5 text-sm text-admin-text shadow-sm transition-colors focus:border-admin-primary focus:outline-none focus:ring-1 focus:ring-admin-primary';

    function speichern(): void {
        saving = true;
        saved = false;

        router.patch(
            `/admin/settings/${config.id}`,
            { value },
            {
                preserveScroll: true,
                onSuccess: () => {
                    saved = true;
                    setTimeout(() => (saved = false), 2000);
                },
                onFinish: () => (saving = false),
            },
        );
    }
</script>

<div class="rounded-admin-card border border-admin-border bg-admin-card p-6 shadow-admin-card">
    <div class="mb-3 flex items-center justify-between">
        <label for={`config-${config.id}`} class="text-sm font-medium text-admin-text-secondary">{config.label}</label>
        {#if saved}
            <span class="flex items-center gap-1 text-xs font-medium text-admin-success">
                <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                    <path
                        fill-rule="evenodd"
                        d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z"
                        clip-rule="evenodd"
                    />
                </svg>
                Gespeichert
            </span>
        {/if}
    </div>

    {#if config.type === 'email'}
        <input id={`config-${config.id}`} type="email" bind:value class={inputClasses} />
    {:else}
        <input id={`config-${config.id}`} type="text" bind:value class={inputClasses} />
    {/if}

    <button
        onclick={speichern}
        disabled={saving}
        class="mt-4 flex items-center gap-2 rounded-lg bg-admin-primary px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-admin-primary-hover disabled:opacity-50 cursor-pointer"
    >
        {#if saving}
            <span class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
        {/if}
        {saving ? 'Speichern…' : 'Speichern'}
    </button>
</div>
