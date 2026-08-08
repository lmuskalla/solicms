<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import PostRow from './PostRow.svelte';
    import PostEditor from './PostEditor.svelte';
    import type { PostRecord } from '../../types';

    interface Props {
        open: boolean;
        sectionId: number;
        posts: PostRecord[];
        hasDates: boolean;
    }

    let { open = $bindable(false), sectionId, posts, hasDates }: Props = $props();

    let newTitle = $state('');
    let newStartsAt = $state('');
    let adding = $state(false);
    let editingPostId = $state<number | null>(null);

    // Derived, not just held in state, so a delete or a background reload
    // that removes this post from `posts` falls back to the list on its
    // own — no separate "did my post disappear" check needed.
    let editingPost = $derived(posts.find((p) => p.id === editingPostId) ?? null);

    function close(): void {
        open = false;
        editingPostId = null;
    }

    function onWindowKeydown(event: KeyboardEvent): void {
        if (open && event.key === 'Escape') close();
    }

    function onOverlayClick(event: MouseEvent): void {
        if (event.target === event.currentTarget) close();
    }

    function addPost(event: SubmitEvent): void {
        event.preventDefault();
        if (!newTitle.trim() || (hasDates && !newStartsAt)) return;

        adding = true;
        router.post(
            `/admin/sections/${sectionId}/posts`,
            { title: newTitle, starts_at: hasDates ? newStartsAt : null },
            {
                preserveScroll: true,
                onSuccess: () => {
                    newTitle = '';
                    newStartsAt = '';
                },
                onFinish: () => (adding = false),
            },
        );
    }
</script>

<svelte:window onkeydown={onWindowKeydown} />

{#if open}
    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-admin-text/40 p-4"
        onclick={onOverlayClick}
        role="presentation"
    >
        <div class="relative w-full max-w-2xl">
            <!-- Clear of the card's own border on purpose — sits just above
                 it with a visible gap, not touching, so it reads as its own
                 thing rather than part of the card's chrome, while still
                 obviously "this modal's close button." Present in both the
                 list and the single-post editor, which has its own
                 back/delete header with no room for a close button. -->
            <button
                type="button"
                onclick={close}
                aria-label="Schließen"
                class="absolute top-[-40px] right-[-40px] cursor-pointer rounded-full border border-admin-border bg-admin-card p-2 text-admin-text-muted shadow-admin-card hover:text-admin-text"
            >
                <svg viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                    <path
                        d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"
                    />
                </svg>
            </button>

            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="posts-manager-title"
                class="flex max-h-[85vh] w-full flex-col rounded-admin-card border border-admin-border bg-admin-card shadow-lg"
            >
                {#if editingPost}
                    {#key editingPost.id}
                        <PostEditor post={editingPost} {hasDates} onBack={() => (editingPostId = null)} />
                    {/key}
                {:else}
                    <div class="border-b border-admin-border px-6 py-4">
                        <h2 id="posts-manager-title" class="text-base font-semibold text-admin-text">Einträge verwalten</h2>
                    </div>

                    <div class="flex-1 space-y-3 overflow-y-auto px-6 py-4">
                        {#each posts as post, i (post.id)}
                            <PostRow {post} isFirst={i === 0} isLast={i === posts.length - 1} onEdit={() => (editingPostId = post.id)} />
                        {:else}
                            <p class="rounded-xl border border-dashed border-admin-border px-5 py-8 text-center text-sm text-admin-text-secondary">
                                Noch keine Einträge.
                            </p>
                        {/each}
                    </div>

                    <form onsubmit={addPost} class="flex items-center gap-3 border-t border-admin-border px-6 py-4">
                        <input
                            type="text"
                            bind:value={newTitle}
                            placeholder="Titel des neuen Eintrags"
                            class="flex-1 rounded-lg border border-admin-border px-3.5 py-2.5 text-sm text-admin-text shadow-sm focus:border-admin-primary focus:outline-none focus:ring-1 focus:ring-admin-primary"
                        />
                        {#if hasDates}
                            <input
                                type="datetime-local"
                                bind:value={newStartsAt}
                                required
                                class="rounded-lg border border-admin-border px-3.5 py-2.5 text-sm text-admin-text shadow-sm focus:border-admin-primary focus:outline-none focus:ring-1 focus:ring-admin-primary"
                            />
                        {/if}
                        <button
                            type="submit"
                            disabled={adding || !newTitle.trim() || (hasDates && !newStartsAt)}
                            class="cursor-pointer rounded-lg bg-admin-primary px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-admin-primary-hover disabled:opacity-50"
                        >
                            {adding ? 'Wird hinzugefügt…' : 'Hinzufügen'}
                        </button>
                    </form>
                {/if}
            </div>
        </div>
    </div>
{/if}
