<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import Layout from '../../../Components/Superadmin/Layout.svelte';
    import ConfirmDialog from '../../../Components/Admin/ConfirmDialog.svelte';
    import type { Auth } from '../../../types';

    interface TenantRow {
        id: string;
        name: string;
        theme: string;
        domains: string[];
        created_at: string;
        dev_login_url: string | null;
    }

    interface Props {
        auth?: Auth;
        tenants: TenantRow[];
        availableThemes: Record<string, string>;
        flash?: { success?: string | null; error?: string | null };
        errors?: { domain?: string };
    }

    let { auth, tenants, availableThemes, flash, errors = {} }: Props = $props();

    let showForm = $state(false);
    let domain = $state('');
    let name = $state('');
    let email = $state('');
    let editorName = $state('');
    let theme = $state(untrack(() => Object.keys(availableThemes)[0] ?? 'default'));
    let creating = $state(false);
    let confirmDeleteOpen = $state(false);
    let tenantToDelete = $state<{ id: string; name: string } | null>(null);

    function createTenant(event: SubmitEvent): void {
        event.preventDefault();
        creating = true;

        router.post(
            '/superadmin/tenants',
            { domain, name, email, editor_name: editorName, theme },
            {
                onSuccess: () => {
                    showForm = false;
                    domain = '';
                    name = '';
                    email = '';
                    editorName = '';
                },
                onFinish: () => (creating = false),
            },
        );
    }

    function askDeleteTenant(id: string, name: string): void {
        tenantToDelete = { id, name };
        confirmDeleteOpen = true;
    }

    function confirmDeleteTenant(): void {
        if (tenantToDelete) {
            router.delete(`/superadmin/tenants/${tenantToDelete.id}`);
        }
    }
</script>

<svelte:head>
    <title>Tenants</title>
</svelte:head>

<Layout {auth}>
    {#snippet children()}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-admin-text">Tenants</h1>
                <p class="mt-1 text-sm text-admin-text-secondary">Alle gehosteten Client-Websites.</p>
            </div>
            <button
                onclick={() => (showForm = !showForm)}
                class="cursor-pointer rounded-lg bg-admin-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-admin-primary-hover"
            >
                {showForm ? 'Abbrechen' : 'Neuer Tenant'}
            </button>
        </div>

        {#if flash?.success}
            <p class="mt-4 rounded-lg bg-admin-success/10 px-4 py-3 text-sm text-admin-success">{flash.success}</p>
        {/if}

        {#if showForm}
            <form onsubmit={createTenant} class="mt-6 space-y-4 rounded-admin-card border border-admin-border bg-admin-card p-6 shadow-admin-card">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="domain" class="mb-1.5 block text-sm font-medium text-admin-text-secondary">Domain(s)</label>
                        <input
                            id="domain"
                            type="text"
                            bind:value={domain}
                            placeholder="ngo-example.org, ngo-example.de"
                            required
                            class="w-full rounded-lg border border-admin-border px-3.5 py-2.5 text-sm text-admin-text shadow-sm focus:border-admin-primary focus:outline-none focus:ring-1 focus:ring-admin-primary"
                        />
                        {#if errors.domain}
                            <p class="mt-1 text-sm text-admin-error">{errors.domain}</p>
                        {/if}
                    </div>
                    <div>
                        <label for="name" class="mb-1.5 block text-sm font-medium text-admin-text-secondary">Name</label>
                        <input
                            id="name"
                            type="text"
                            bind:value={name}
                            placeholder="Example NGO"
                            required
                            class="w-full rounded-lg border border-admin-border px-3.5 py-2.5 text-sm text-admin-text shadow-sm focus:border-admin-primary focus:outline-none focus:ring-1 focus:ring-admin-primary"
                        />
                    </div>
                    <div>
                        <label for="email" class="mb-1.5 block text-sm font-medium text-admin-text-secondary">Kontakt-E-Mail</label>
                        <input
                            id="email"
                            type="email"
                            bind:value={email}
                            placeholder="contact@ngo-example.org"
                            required
                            class="w-full rounded-lg border border-admin-border px-3.5 py-2.5 text-sm text-admin-text shadow-sm focus:border-admin-primary focus:outline-none focus:ring-1 focus:ring-admin-primary"
                        />
                    </div>
                    <div>
                        <label for="editor_name" class="mb-1.5 block text-sm font-medium text-admin-text-secondary">Redakteur-Name</label>
                        <input
                            id="editor_name"
                            type="text"
                            bind:value={editorName}
                            placeholder="Max Mustermann"
                            required
                            class="w-full rounded-lg border border-admin-border px-3.5 py-2.5 text-sm text-admin-text shadow-sm focus:border-admin-primary focus:outline-none focus:ring-1 focus:ring-admin-primary"
                        />
                    </div>
                    <div>
                        <label for="theme" class="mb-1.5 block text-sm font-medium text-admin-text-secondary">Theme</label>
                        <select
                            id="theme"
                            bind:value={theme}
                            class="w-full rounded-lg border border-admin-border px-3.5 py-2.5 text-sm text-admin-text shadow-sm focus:border-admin-primary focus:outline-none focus:ring-1 focus:ring-admin-primary"
                        >
                            {#each Object.entries(availableThemes) as [value, label]}
                                <option {value}>{label}</option>
                            {/each}
                        </select>
                    </div>
                </div>

                <button
                    type="submit"
                    disabled={creating}
                    class="flex items-center gap-2 rounded-lg bg-admin-primary px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-admin-primary-hover disabled:opacity-50 cursor-pointer"
                >
                    {#if creating}
                        <span class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                    {/if}
                    {creating ? 'Wird erstellt…' : 'Tenant erstellen'}
                </button>
            </form>
        {/if}

        <div class="mt-6 overflow-hidden rounded-admin-card border border-admin-border bg-admin-card shadow-admin-card">
            {#each tenants as t, i (t.id)}
                <div class="flex items-center justify-between px-5 py-4 {i > 0 ? 'border-t border-admin-border' : ''}">
                    <div>
                        <p class="text-sm font-medium text-admin-text">{t.name}</p>
                        <p class="text-sm text-admin-text-secondary">
                            {t.domains.join(', ') || 'keine Domain'} · Theme: {availableThemes[t.theme] ?? t.theme}
                        </p>
                    </div>
                    <div class="flex items-center gap-4">
                        {#if t.dev_login_url}
                            <a
                                href={t.dev_login_url}
                                target="_blank"
                                rel="noopener"
                                class="text-sm font-medium text-admin-primary hover:text-admin-primary-hover"
                            >
                                Dev-Login
                            </a>
                        {/if}
                        <button
                            onclick={() => askDeleteTenant(t.id, t.name)}
                            class="cursor-pointer text-sm text-admin-text-muted hover:text-admin-error"
                        >
                            Löschen
                        </button>
                    </div>
                </div>
            {:else}
                <p class="px-5 py-8 text-center text-sm text-admin-text-secondary">Noch keine Tenants vorhanden.</p>
            {/each}
        </div>

        <ConfirmDialog
            bind:open={confirmDeleteOpen}
            title="Tenant löschen"
            message={`„${tenantToDelete?.name}" wirklich löschen? Die Website und alle Inhalte werden unwiderruflich entfernt.`}
            onConfirm={confirmDeleteTenant}
        />
    {/snippet}
</Layout>
