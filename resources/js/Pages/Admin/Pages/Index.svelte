<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import Layout from '../../../Components/Admin/Layout.svelte';
    import type { Auth } from '../../../types';

    interface Page {
        id: number;
        title: string;
        published: boolean;
    }

    interface Props {
        auth?: Auth;
        pages: Page[];
        availableTemplates: Record<string, string>;
        flash?: { success?: string | null };
        errors?: { title?: string; slug?: string; template?: string };
    }

    let { auth, pages, availableTemplates, flash, errors = {} }: Props = $props();

    let showForm = $state(false);
    let title = $state('');
    let slug = $state('');
    let slugTouchedByUser = $state(false);
    let template = $state(untrack(() => Object.keys(availableTemplates)[0] ?? ''));
    let creating = $state(false);

    function slugify(value: string): string {
        return value
            .toLowerCase()
            .normalize('NFKD')
            .replace(/[\u0300-\u036f]/g, '') // strip combining accents (ä -> a, etc.)
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    function onTitleInput(): void {
        if (!slugTouchedByUser) {
            slug = slugify(title);
        }
    }

    function onSlugInput(): void {
        slugTouchedByUser = true;
    }

    function createPage(event: SubmitEvent): void {
        event.preventDefault();
        creating = true;

        router.post(
            '/admin/pages',
            { title, slug, template },
            { onFinish: () => (creating = false) },
        );
    }
</script>

<svelte:head>
    <title>Seiten</title>
</svelte:head>

<Layout {auth}>
    {#snippet children()}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-admin-text">Seiten</h1>
                <p class="mt-1 text-sm text-admin-text-secondary">Wählen Sie eine Seite aus, um deren Inhalte zu bearbeiten.</p>
            </div>
            <button
                onclick={() => (showForm = !showForm)}
                class="cursor-pointer rounded-lg bg-admin-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-admin-primary-hover"
            >
                {showForm ? 'Abbrechen' : 'Neue Seite'}
            </button>
        </div>

        {#if flash?.success}
            <p class="mt-4 rounded-lg bg-admin-success/10 px-4 py-3 text-sm text-admin-success">{flash.success}</p>
        {/if}

        {#if showForm}
            <form onsubmit={createPage} class="mt-6 space-y-4 rounded-admin-card border border-admin-border bg-admin-card p-6 shadow-admin-card">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="title" class="mb-1.5 block text-sm font-medium text-admin-text-secondary">Titel</label>
                        <input
                            id="title"
                            type="text"
                            bind:value={title}
                            oninput={onTitleInput}
                            placeholder="Über uns"
                            required
                            class="w-full rounded-lg border border-admin-border px-3.5 py-2.5 text-sm text-admin-text shadow-sm focus:border-admin-primary focus:outline-none focus:ring-1 focus:ring-admin-primary"
                        />
                        {#if errors.title}
                            <p class="mt-1 text-sm text-admin-error">{errors.title}</p>
                        {/if}
                    </div>
                    <div>
                        <label for="slug" class="mb-1.5 block text-sm font-medium text-admin-text-secondary">Adresse (URL)</label>
                        <input
                            id="slug"
                            type="text"
                            bind:value={slug}
                            oninput={onSlugInput}
                            placeholder="ueber-uns"
                            required
                            class="w-full rounded-lg border border-admin-border px-3.5 py-2.5 text-sm text-admin-text shadow-sm focus:border-admin-primary focus:outline-none focus:ring-1 focus:ring-admin-primary"
                        />
                        {#if errors.slug}
                            <p class="mt-1 text-sm text-admin-error">{errors.slug}</p>
                        {/if}
                    </div>
                </div>

                <div>
                    <label for="template" class="mb-1.5 block text-sm font-medium text-admin-text-secondary">Layout</label>
                    <select
                        id="template"
                        bind:value={template}
                        class="w-full rounded-lg border border-admin-border px-3.5 py-2.5 text-sm text-admin-text shadow-sm focus:border-admin-primary focus:outline-none focus:ring-1 focus:ring-admin-primary"
                    >
                        {#each Object.entries(availableTemplates) as [value, label]}
                            <option {value}>{label}</option>
                        {/each}
                    </select>
                </div>

                <button
                    type="submit"
                    disabled={creating}
                    class="flex items-center gap-2 rounded-lg bg-admin-primary px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-admin-primary-hover disabled:opacity-50 cursor-pointer"
                >
                    {#if creating}
                        <span class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                    {/if}
                    {creating ? 'Wird erstellt…' : 'Seite erstellen'}
                </button>
            </form>
        {/if}

        <div class="mt-6 overflow-hidden rounded-admin-card border border-admin-border bg-admin-card shadow-admin-card">
            {#each pages as page, i (page.id)}
                <Link
                    href={`/admin/pages/${page.id}`}
                    class="flex items-center justify-between px-5 py-4 transition-colors hover:bg-admin-bg {i > 0 ? 'border-t border-admin-border' : ''}"
                >
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-admin-primary/10 text-admin-primary">
                            <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                                <path d="M4 4a2 2 0 0 1 2-2h5.379a2 2 0 0 1 1.414.586l2.621 2.621A2 2 0 0 1 16 6.621V16a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4Z" />
                            </svg>
                        </div>
                        <span class="text-sm font-medium text-admin-text">{page.title}</span>
                        {#if !page.published}
                            <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">
                                Unveröffentlicht
                            </span>
                        {/if}
                    </div>

                    <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 shrink-0 text-admin-text-muted">
                        <path
                            fill-rule="evenodd"
                            d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </Link>
            {:else}
                <p class="px-5 py-8 text-center text-sm text-admin-text-secondary">Es sind noch keine Seiten vorhanden.</p>
            {/each}
        </div>
    {/snippet}
</Layout>
