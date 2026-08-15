<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import Layout from '../../../Components/Admin/Layout.svelte';
    import SectionField from '../../../Components/Admin/SectionField.svelte';
    import ConfirmDialog from '../../../Components/Admin/ConfirmDialog.svelte';
    import type { Auth } from '../../../types';

    interface Section {
        id: number;
        page_id: number;
        key: string;
        label: string;
        type: string;
        value: string | null;
        alt?: string | null;
    }

    interface Props {
        auth?: Auth;
        page: { id: number; title: string; template: string; published: boolean };
        sections: Section[];
        availableTemplates: Record<string, string>;
    }

    let { auth, page, sections, availableTemplates }: Props = $props();

    // Deliberately captured once: Inertia mounts a fresh instance of this page
    // component per visit (different URL, no persistent layout), so the
    // initial value is always this page's own.
    let title = $state(untrack(() => page.title));
    let template = $state(untrack(() => page.template));
    let published = $state(untrack(() => page.published));
    let titleSaved = $state(false);
    let saved = $state(false);
    let publishSaved = $state(false);
    let confirmDeleteOpen = $state(false);

    function saveTitle(): void {
        if (title === page.title) {
            return;
        }

        router.patch(
            `/admin/pages/${page.id}`,
            { title },
            {
                preserveScroll: true,
                onSuccess: () => {
                    titleSaved = true;
                    setTimeout(() => (titleSaved = false), 2000);
                },
            },
        );
    }

    function saveTemplate(): void {
        router.patch(
            `/admin/pages/${page.id}`,
            { template },
            {
                preserveScroll: true,
                onSuccess: () => {
                    saved = true;
                    setTimeout(() => (saved = false), 2000);
                },
            },
        );
    }

    function savePublished(): void {
        router.patch(
            `/admin/pages/${page.id}`,
            { published },
            {
                preserveScroll: true,
                onSuccess: () => {
                    publishSaved = true;
                    setTimeout(() => (publishSaved = false), 2000);
                },
            },
        );
    }

    function deletePage(): void {
        router.delete(`/admin/pages/${page.id}`);
    }
</script>

<svelte:head>
    <title>{page.title}</title>
</svelte:head>

<Layout {auth}>
    {#snippet children()}
        <div class="flex items-center justify-between">
            <Link href="/admin/pages" class="inline-flex items-center gap-1 text-sm font-medium text-admin-text-secondary hover:text-admin-text">
                <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                    <path
                        fill-rule="evenodd"
                        d="M12.79 5.23a.75.75 0 0 1-.02 1.06L8.832 10l3.938 3.71a.75.75 0 1 1-1.04 1.08l-4.5-4.25a.75.75 0 0 1 0-1.08l4.5-4.25a.75.75 0 0 1 1.06.02Z"
                        clip-rule="evenodd"
                    />
                </svg>
                Alle Seiten
            </Link>

            <button
                onclick={() => (confirmDeleteOpen = true)}
                class="cursor-pointer text-sm text-admin-text-muted hover:text-admin-error"
            >
                Seite löschen
            </button>
        </div>

        <div class="mt-3 flex items-center justify-between gap-3">
            <input
                id="title"
                bind:value={title}
                onblur={saveTitle}
                onkeydown={(e) => {
                    if (e.key === 'Enter') (e.currentTarget as HTMLInputElement).blur();
                }}
                aria-label="Seitentitel"
                class="w-full max-w-xl rounded-lg border border-transparent bg-transparent px-1 -mx-1 text-2xl font-semibold tracking-tight text-admin-text hover:border-admin-border focus:border-admin-primary focus:outline-none focus:ring-1 focus:ring-admin-primary"
            />
            {#if titleSaved}
                <span class="text-xs font-medium text-admin-success">Gespeichert</span>
            {/if}
        </div>

        <div class="mt-6 rounded-admin-card border border-admin-border bg-admin-card p-6 shadow-admin-card">
            <div class="mb-2 flex items-center justify-between">
                <label for="template" class="text-sm font-medium text-admin-text-secondary">Layout dieser Seite</label>
                {#if saved}
                    <span class="text-xs font-medium text-admin-success">Gespeichert</span>
                {/if}
            </div>
            <select
                id="template"
                bind:value={template}
                onchange={saveTemplate}
                class="w-full rounded-lg border border-admin-border px-3.5 py-2.5 text-sm text-admin-text shadow-sm focus:border-admin-primary focus:outline-none focus:ring-1 focus:ring-admin-primary"
            >
                {#each Object.entries(availableTemplates) as [value, label]}
                    <option {value}>{label}</option>
                {/each}
            </select>
        </div>

        <div class="mt-4 rounded-admin-card border border-admin-border bg-admin-card p-6 shadow-admin-card">
            <div class="flex items-center justify-between">
                <div>
                    <label for="published" class="text-sm font-medium text-admin-text-secondary">Status</label>
                    <p class="mt-0.5 text-xs text-admin-text-muted">
                        {published ? 'Diese Seite ist öffentlich sichtbar.' : 'Diese Seite ist ein Entwurf und für Besucher nicht sichtbar.'}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    {#if publishSaved}
                        <span class="text-xs font-medium text-admin-success">Gespeichert</span>
                    {/if}
                    <button
                        id="published"
                        type="button"
                        role="switch"
                        aria-checked={published}
                        aria-label="Veröffentlicht"
                        onclick={() => {
                            published = !published;
                            savePublished();
                        }}
                        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full transition-colors {published
                            ? 'bg-admin-primary'
                            : 'bg-admin-border'}"
                    >
                        <span
                            class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform {published
                                ? 'translate-x-6'
                                : 'translate-x-1'}"
                        ></span>
                    </button>
                    <span class="w-24 text-sm font-medium text-admin-text">{published ? 'Veröffentlicht' : 'Entwurf'}</span>
                </div>
            </div>
        </div>

        <div class="mt-4 space-y-4">
            {#each sections as section (section.id)}
                <SectionField {section} />
            {:else}
                <p class="rounded-xl border border-dashed border-admin-border px-5 py-8 text-center text-sm text-admin-text-secondary">
                    Diese Seite hat noch keine Inhaltsbereiche.
                </p>
            {/each}
        </div>

        <ConfirmDialog
            bind:open={confirmDeleteOpen}
            title="Seite löschen"
            message={`„${page.title}" wirklich löschen? Alle Inhalte dieser Seite gehen unwiderruflich verloren.`}
            onConfirm={deletePage}
        />
    {/snippet}
</Layout>
