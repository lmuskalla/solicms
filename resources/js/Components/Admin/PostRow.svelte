<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import ConfirmDialog from './ConfirmDialog.svelte';
    import type { PostRecord } from '../../types';

    interface Props {
        post: PostRecord;
        isFirst: boolean;
        isLast: boolean;
        onEdit: () => void;
    }

    let { post, isFirst, isLast, onEdit }: Props = $props();

    let confirmDeleteOpen = $state(false);

    function destroy(): void {
        router.delete(`/admin/posts/${post.id}`, { preserveScroll: true });
    }

    function move(direction: 'up' | 'down'): void {
        router.post(`/admin/posts/${post.id}/move`, { direction }, { preserveScroll: true });
    }
</script>

<div class="flex items-center gap-3 rounded-lg border border-admin-border px-4 py-3">
    <div class="flex flex-col">
        <button
            type="button"
            onclick={() => move('up')}
            disabled={isFirst}
            aria-label="Nach oben verschieben"
            class="cursor-pointer text-admin-text-muted hover:text-admin-text disabled:cursor-not-allowed disabled:opacity-30"
        >
            <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path d="M10 6l-5 5h10l-5-5z" /></svg>
        </button>
        <button
            type="button"
            onclick={() => move('down')}
            disabled={isLast}
            aria-label="Nach unten verschieben"
            class="cursor-pointer text-admin-text-muted hover:text-admin-text disabled:cursor-not-allowed disabled:opacity-30"
        >
            <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path d="M10 14l5-5H5l5 5z" /></svg>
        </button>
    </div>

    {#if post.image}
        <img src={post.image} alt="" class="h-10 w-10 shrink-0 rounded-md object-cover" />
    {/if}

    <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-medium text-admin-text">{post.title}</p>
        <p class="text-xs text-admin-text-muted">
            {#if post.starts_at}{new Date(post.starts_at).toLocaleString('de-DE', { dateStyle: 'medium', timeStyle: 'short' })}{/if}
            {#if post.starts_at && post.slug} · {/if}
            {#if post.slug}Eigene Seite{/if}
            {#if !post.starts_at && !post.slug}—{/if}
        </p>
    </div>

    <button
        type="button"
        onclick={onEdit}
        class="cursor-pointer rounded-lg border border-admin-border bg-admin-card px-3 py-1.5 text-xs font-medium text-admin-text-secondary shadow-sm hover:bg-admin-bg"
    >
        Bearbeiten
    </button>
    <button
        type="button"
        onclick={() => (confirmDeleteOpen = true)}
        aria-label="Eintrag löschen"
        class="cursor-pointer text-admin-text-muted hover:text-admin-error"
    >
        <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
            <path
                fill-rule="evenodd"
                d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z"
                clip-rule="evenodd"
            />
        </svg>
    </button>
</div>

<ConfirmDialog
    bind:open={confirmDeleteOpen}
    title="Eintrag löschen"
    message={`„${post.title}" wirklich löschen?`}
    onConfirm={destroy}
/>
