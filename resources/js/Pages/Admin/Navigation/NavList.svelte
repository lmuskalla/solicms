<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import ConfirmDialog from '../../../Components/Admin/ConfirmDialog.svelte';

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
        title: string;
        menu: 'header' | 'footer';
        items: NavItemRow[];
        pages: PageOption[];
    }

    let { title, menu, items, pages }: Props = $props();

    const otherMenuLabel = $derived(menu === 'header' ? 'In Fußzeile verschieben' : 'In Kopfzeile verschieben');

    // --- Add a page (menu is implied by which list the form lives in) ---
    let selectedPageId = $state(untrack(() => pages[0]?.id ?? null));
    let addingPage = $state(false);

    function addPage(event: SubmitEvent): void {
        event.preventDefault();
        const page = pages.find((p) => p.id === selectedPageId);
        if (!page) return;

        addingPage = true;
        router.post(
            '/admin/navigation',
            { type: 'page', page_id: page.id, label: page.title, menu },
            { onFinish: () => (addingPage = false) },
        );
    }

    // --- Add a link ---
    let linkLabel = $state('');
    let linkUrl = $state('');
    let addingLink = $state(false);

    function addLink(event: SubmitEvent): void {
        event.preventDefault();
        addingLink = true;

        router.post(
            '/admin/navigation',
            { type: 'link', label: linkLabel, url: linkUrl, menu },
            {
                onSuccess: () => {
                    linkLabel = '';
                    linkUrl = '';
                },
                onFinish: () => (addingLink = false),
            },
        );
    }

    // --- Reorder (within this menu — the server scopes the neighbor lookup) ---
    function move(item: NavItemRow, direction: 'up' | 'down'): void {
        router.post(`/admin/navigation/${item.id}/move`, { direction }, { preserveScroll: true });
    }

    // --- Move to the other menu (appends at its end server-side) ---
    function moveToOtherMenu(item: NavItemRow): void {
        router.patch(
            `/admin/navigation/${item.id}`,
            {
                label: item.label,
                url: item.url ?? '',
                menu: menu === 'header' ? 'footer' : 'header',
            },
            { preserveScroll: true },
        );
    }

    // --- Inline edit ---
    let editingId = $state<number | null>(null);
    let editLabel = $state('');
    let editUrl = $state('');

    function startEdit(item: NavItemRow): void {
        editingId = item.id;
        editLabel = item.label;
        editUrl = item.url ?? '';
    }

    function saveEdit(item: NavItemRow): void {
        router.patch(
            `/admin/navigation/${item.id}`,
            { label: editLabel, url: editUrl },
            { preserveScroll: true, onSuccess: () => (editingId = null) },
        );
    }

    // --- Delete ---
    let confirmDeleteOpen = $state(false);
    let itemToDelete = $state<NavItemRow | null>(null);

    function askDelete(item: NavItemRow): void {
        itemToDelete = item;
        confirmDeleteOpen = true;
    }

    function confirmDelete(): void {
        if (itemToDelete) {
            router.delete(`/admin/navigation/${itemToDelete.id}`, { preserveScroll: true });
        }
    }
</script>

<section class="rounded-admin-card border border-admin-border bg-admin-card shadow-admin-card">
    <header class="border-b border-admin-border px-5 py-4">
        <h2 class="text-base font-semibold text-admin-text">{title}</h2>
    </header>

    <div>
        {#each items as item, i (item.id)}
            <div class="px-5 py-4 {i > 0 ? 'border-t border-admin-border' : ''}">
                {#if editingId === item.id}
                    <div class="space-y-2">
                        <input
                            type="text"
                            bind:value={editLabel}
                            placeholder="Beschriftung"
                            class="w-full rounded-lg border border-admin-border px-3 py-2 text-sm text-admin-text shadow-sm focus:border-admin-primary focus:outline-none focus:ring-1 focus:ring-admin-primary"
                        />
                        {#if item.type === 'link'}
                            <input
                                type="text"
                                bind:value={editUrl}
                                placeholder="https://…"
                                class="w-full rounded-lg border border-admin-border px-3 py-2 text-sm text-admin-text shadow-sm focus:border-admin-primary focus:outline-none focus:ring-1 focus:ring-admin-primary"
                            />
                        {/if}
                        <div class="flex gap-2">
                            <button
                                onclick={() => saveEdit(item)}
                                class="cursor-pointer rounded-lg bg-admin-primary px-3 py-1.5 text-sm font-semibold text-white hover:bg-admin-primary-hover"
                            >
                                Speichern
                            </button>
                            <button
                                onclick={() => (editingId = null)}
                                class="cursor-pointer rounded-lg border border-admin-border px-3 py-1.5 text-sm text-admin-text-secondary hover:bg-admin-bg"
                            >
                                Abbrechen
                            </button>
                        </div>
                    </div>
                {:else}
                    <div class="flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-admin-text">{item.label}</p>
                            <p class="truncate text-xs text-admin-text-muted">
                                {item.type === 'page' ? `Seite: ${item.page?.title ?? '(gelöscht)'}` : item.url}
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            <button
                                onclick={() => move(item, 'up')}
                                disabled={i === 0}
                                aria-label="Nach oben verschieben"
                                class="cursor-pointer rounded p-1.5 text-admin-text-muted hover:bg-admin-bg hover:text-admin-text disabled:cursor-default disabled:opacity-30"
                            >
                                <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                                    <path fill-rule="evenodd" d="M10 3a.75.75 0 0 1 .55.24l4.5 4.83a.75.75 0 1 1-1.1 1.02L10.75 5.9v9.85a.75.75 0 0 1-1.5 0V5.9L6.05 9.09a.75.75 0 1 1-1.1-1.02l4.5-4.83A.75.75 0 0 1 10 3Z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <button
                                onclick={() => move(item, 'down')}
                                disabled={i === items.length - 1}
                                aria-label="Nach unten verschieben"
                                class="cursor-pointer rounded p-1.5 text-admin-text-muted hover:bg-admin-bg hover:text-admin-text disabled:cursor-default disabled:opacity-30"
                            >
                                <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                                    <path fill-rule="evenodd" d="M10 17a.75.75 0 0 1-.55-.24l-4.5-4.83a.75.75 0 1 1 1.1-1.02l3.2 3.44V4.5a.75.75 0 0 1 1.5 0v9.85l3.2-3.44a.75.75 0 1 1 1.1 1.02l-4.5 4.83A.75.75 0 0 1 10 17Z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <button
                                onclick={() => moveToOtherMenu(item)}
                                title={otherMenuLabel}
                                class="cursor-pointer px-2 py-1.5 text-sm text-admin-text-secondary hover:text-admin-text"
                            >
                                {otherMenuLabel}
                            </button>
                            <button
                                onclick={() => startEdit(item)}
                                class="cursor-pointer px-2 py-1.5 text-sm text-admin-text-secondary hover:text-admin-text"
                            >
                                Bearbeiten
                            </button>
                            <button
                                onclick={() => askDelete(item)}
                                class="cursor-pointer px-2 py-1.5 text-sm text-admin-text-muted hover:text-admin-error"
                            >
                                Entfernen
                            </button>
                        </div>
                    </div>
                {/if}
            </div>
        {:else}
            <p class="px-5 py-8 text-center text-sm text-admin-text-secondary">Dieses Menü ist noch leer.</p>
        {/each}
    </div>

    <div class="grid gap-4 border-t border-admin-border p-5 sm:grid-cols-2">
        <form onsubmit={addPage}>
            <h3 class="text-sm font-semibold text-admin-text">Seite hinzufügen</h3>
            <div class="mt-3 flex gap-2">
                <select
                    bind:value={selectedPageId}
                    class="w-full rounded-lg border border-admin-border px-3 py-2 text-sm text-admin-text shadow-sm focus:border-admin-primary focus:outline-none focus:ring-1 focus:ring-admin-primary"
                >
                    {#each pages as page (page.id)}
                        <option value={page.id}>{page.title}</option>
                    {/each}
                </select>
                <button
                    type="submit"
                    disabled={addingPage || pages.length === 0}
                    class="shrink-0 cursor-pointer rounded-lg bg-admin-primary px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-admin-primary-hover disabled:opacity-50"
                >
                    Hinzufügen
                </button>
            </div>
        </form>

        <form onsubmit={addLink}>
            <h3 class="text-sm font-semibold text-admin-text">Link hinzufügen</h3>
            <div class="mt-3 space-y-2">
                <input
                    type="text"
                    bind:value={linkLabel}
                    placeholder="Beschriftung"
                    required
                    class="w-full rounded-lg border border-admin-border px-3 py-2 text-sm text-admin-text shadow-sm focus:border-admin-primary focus:outline-none focus:ring-1 focus:ring-admin-primary"
                />
                <div class="flex gap-2">
                    <input
                        type="text"
                        bind:value={linkUrl}
                        placeholder="https://…"
                        required
                        class="w-full rounded-lg border border-admin-border px-3 py-2 text-sm text-admin-text shadow-sm focus:border-admin-primary focus:outline-none focus:ring-1 focus:ring-admin-primary"
                    />
                    <button
                        type="submit"
                        disabled={addingLink}
                        class="shrink-0 cursor-pointer rounded-lg bg-admin-primary px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-admin-primary-hover disabled:opacity-50"
                    >
                        Hinzufügen
                    </button>
                </div>
            </div>
        </form>
    </div>

    <ConfirmDialog
        bind:open={confirmDeleteOpen}
        title="Aus dem Menü entfernen"
        message={`„${itemToDelete?.label}" aus der Navigation entfernen? Die Seite selbst bleibt erhalten.`}
        confirmLabel="Entfernen"
        onConfirm={confirmDelete}
    />
</section>
