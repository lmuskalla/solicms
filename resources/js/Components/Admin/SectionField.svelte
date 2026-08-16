<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import { showToast } from '../../lib/toast.svelte';
    import TiptapEditor from './TiptapEditor.svelte';
    import ImageUpload from './ImageUpload.svelte';
    import PostsManager from './PostsManager.svelte';
    import type { PostRecord } from '../../types';

    interface Props {
        section: {
            id: number;
            page_id: number;
            key: string;
            label: string;
            type: string;
            value: string | null;
            alt?: string | null;
            posts?: PostRecord[];
            note?: string;
        };
    }

    let { section }: Props = $props();
    // Deliberately captured once: this component is remounted per section.id
    // ({#each ... (section.id)}), so the initial value is all it needs.
    let value = $state(untrack(() => section.value ?? ''));
    let alt = $state(untrack(() => section.alt ?? ''));
    let saving = $state(false);
    let saved = $state(false);
    let managerOpen = $state(false);

    const inputClasses =
        'w-full rounded-lg border border-admin-border px-3.5 py-2.5 text-sm text-admin-text shadow-sm transition-colors focus:border-admin-primary focus:outline-none focus:ring-1 focus:ring-admin-primary';

    function speichern(): void {
        saving = true;
        saved = false;

        router.patch(
            `/admin/pages/${section.page_id}/sections/${section.id}`,
            section.type === 'image' ? { value, alt } : { value },
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
</script>

<div class="rounded-admin-card border border-admin-border bg-admin-card p-6 shadow-admin-card">
    <div class="mb-3 flex items-center justify-between">
        <label for={`section-${section.id}`} class="text-sm font-medium text-admin-text-secondary">{section.label}</label>
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

    {#if section.type === 'posts' || section.type === 'events'}
        <!-- No `value`/Speichern flow at all — a 'posts'/'events' section's
             content is its `posts` relation, managed entirely through the
             modal below. See THEMES.md §8/§9 and App\Models\Post's doc
             comment. When this is a posts_ref card, section.id is the real
             *source* section's id (possibly on another page), so the modal
             writes through to the exact same rows either page's editor
             would reach. -->
        {#if section.note}
            <p class="mb-3 text-xs text-admin-text-muted">{section.note}</p>
        {/if}
        <div class="flex items-center justify-between rounded-lg border border-dashed border-admin-border px-4 py-3">
            <span class="text-sm text-admin-text-secondary">
                {section.posts?.length ?? 0} {section.posts?.length === 1 ? 'Eintrag' : 'Einträge'}
            </span>
            <button
                type="button"
                onclick={() => (managerOpen = true)}
                class="cursor-pointer rounded-lg border border-admin-border bg-admin-card px-3.5 py-2 text-sm font-medium text-admin-text-secondary shadow-sm transition-colors hover:bg-admin-bg"
            >
                Verwalten
            </button>
        </div>

        <PostsManager bind:open={managerOpen} sectionId={section.id} posts={section.posts ?? []} hasDates={section.type === 'events'} />
    {:else}
        {#if section.type === 'textarea'}
            <textarea id={`section-${section.id}`} bind:value rows="5" class={inputClasses}></textarea>
        {:else if section.type === 'wysiwyg'}
            <TiptapEditor bind:value modelType="section" modelId={section.id} />
        {:else if section.type === 'image'}
            <ImageUpload bind:value bind:alt modelType="section" modelId={section.id} />
        {:else if section.type === 'email'}
            <input id={`section-${section.id}`} type="email" bind:value class={inputClasses} />
        {:else if section.type === 'url'}
            <input id={`section-${section.id}`} type="url" bind:value class={inputClasses} />
        {:else if section.type === 'color'}
            <input id={`section-${section.id}`} type="color" bind:value class="h-10 w-20 rounded-lg border border-admin-border" />
        {:else}
            <input id={`section-${section.id}`} type="text" bind:value class={inputClasses} />
        {/if}

        <div class="mt-4">
            <button
                onclick={speichern}
                disabled={saving}
                class="flex items-center gap-2 rounded-lg bg-admin-primary px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-admin-primary-hover disabled:opacity-50 cursor-pointer"
            >
                {#if saving}
                    <span class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                {/if}
                {saving ? 'Speichern…' : 'Speichern'}
            </button>
        </div>
    {/if}
</div>
