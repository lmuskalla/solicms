<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import { showToast } from '../../lib/toast.svelte';
    import TiptapEditor from './TiptapEditor.svelte';
    import ImageUpload from './ImageUpload.svelte';
    import ConfirmDialog from './ConfirmDialog.svelte';
    import type { PostRecord } from '../../types';

    interface Props {
        post: PostRecord;
        hasDates: boolean;
        onBack: () => void;
    }

    let { post, hasDates, onBack }: Props = $props();

    let confirmDeleteOpen = $state(false);
    let saving = $state(false);
    let saved = $state(false);

    // Captured once per post.id — this component is remounted whenever
    // PostsManager switches which post.id it's editing (see its {#key}),
    // so the initial value is always this post's own.
    let title = $state(untrack(() => post.title));
    let excerpt = $state(untrack(() => post.excerpt ?? ''));
    let image = $state(untrack(() => post.image ?? ''));
    let hasDetailPage = $state(untrack(() => post.body !== null && post.body !== ''));
    let body = $state(untrack(() => post.body ?? ''));
    // <input type="datetime-local"> wants "YYYY-MM-DDTHH:mm", not the
    // server's ISO string with seconds/timezone. Only ever shown/sent at
    // all when hasDates — see THEMES.md §8, this is the section's own
    // type, not a per-post choice, so there's no checkbox here anymore.
    let startsAt = $state(untrack(() => (post.starts_at ? post.starts_at.slice(0, 16) : '')));
    let autoDelete = $state(untrack(() => post.auto_delete ?? false));

    const inputClasses =
        'w-full rounded-lg border border-admin-border px-3.5 py-2.5 text-sm text-admin-text shadow-sm transition-colors focus:border-admin-primary focus:outline-none focus:ring-1 focus:ring-admin-primary';

    function save(): void {
        saving = true;
        saved = false;

        router.patch(
            `/admin/posts/${post.id}`,
            {
                title,
                excerpt: excerpt || null,
                image: image || null,
                starts_at: hasDates ? startsAt : null,
                auto_delete: hasDates ? autoDelete : false,
                body: hasDetailPage ? body : null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    saved = true;
                    setTimeout(() => (saved = false), 2000);
                    showToast('Gespeichert');
                },
                onFinish: () => (saving = false),
            },
        );
    }

    function destroy(): void {
        router.delete(`/admin/posts/${post.id}`, { preserveScroll: true, onSuccess: onBack });
    }
</script>

<div class="flex items-center gap-2 border-b border-admin-border px-6 py-4">
    <button
        type="button"
        onclick={onBack}
        aria-label="Zurück zur Übersicht"
        class="cursor-pointer rounded-lg p-1 text-admin-text-muted hover:bg-admin-bg hover:text-admin-text"
    >
        <svg viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
            <path
                fill-rule="evenodd"
                d="M12.79 5.23a.75.75 0 0 1-.02 1.06L8.832 10l3.938 3.71a.75.75 0 1 1-1.04 1.08l-4.5-4.25a.75.75 0 0 1 0-1.08l4.5-4.25a.75.75 0 0 1 1.06.02Z"
                clip-rule="evenodd"
            />
        </svg>
    </button>
    <h3 class="min-w-0 flex-1 truncate text-base font-semibold text-admin-text">{post.title}</h3>
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

<div class="flex-1 space-y-4 overflow-y-auto px-6 py-4">
    <div>
        <label for={`post-${post.id}-title`} class="mb-1.5 block text-sm font-medium text-admin-text-secondary">Titel</label>
        <input id={`post-${post.id}-title`} type="text" bind:value={title} class={inputClasses} />
    </div>

    <div>
        <span class="mb-1.5 block text-sm font-medium text-admin-text-secondary">Bild</span>
        <ImageUpload bind:value={image} modelType="post" modelId={post.id} collection="image" />
    </div>

    <div>
        <label for={`post-${post.id}-excerpt`} class="mb-1.5 block text-sm font-medium text-admin-text-secondary">
            Kurztext
        </label>
        <textarea id={`post-${post.id}-excerpt`} bind:value={excerpt} rows="2" class={inputClasses}></textarea>
    </div>

    {#if hasDates}
        <div>
            <label for={`post-${post.id}-starts-at`} class="mb-1.5 block text-sm font-medium text-admin-text-secondary">
                Datum
            </label>
            <input id={`post-${post.id}-starts-at`} type="datetime-local" bind:value={startsAt} required class={inputClasses} />
        </div>

        <label class="flex items-center gap-2 text-sm text-admin-text-secondary">
            <input type="checkbox" bind:checked={autoDelete} class="rounded border-admin-border" />
            Automatisch löschen, wenn der Termin vorbei ist
        </label>
    {/if}

    <label class="flex items-center gap-2 text-sm text-admin-text-secondary">
        <input type="checkbox" bind:checked={hasDetailPage} class="rounded border-admin-border" />
        Hat eine eigene Seite mit ausführlichem Text
    </label>
    {#if hasDetailPage}
        <TiptapEditor bind:value={body} modelType="post" modelId={post.id} />
    {/if}
</div>

<div class="flex items-center gap-3 border-t border-admin-border px-6 py-4">
    <button
        type="button"
        onclick={save}
        disabled={saving || (hasDates && !startsAt)}
        class="flex cursor-pointer items-center gap-2 rounded-lg bg-admin-primary px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-admin-primary-hover disabled:opacity-50"
    >
        {#if saving}
            <span class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
        {/if}
        {saving ? 'Speichern…' : 'Speichern'}
    </button>
    {#if saved}<span class="text-xs font-medium text-admin-success">Gespeichert</span>{/if}
</div>

<ConfirmDialog
    bind:open={confirmDeleteOpen}
    title="Eintrag löschen"
    message={`„${post.title}" wirklich löschen?`}
    onConfirm={destroy}
/>
