<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import type { PostRecord } from '../../../js/types';

    let { post }: { post: PostRecord } = $props();

    const href = $derived(post.slug ? `/aktuelles/${post.slug}` : null);
    const formatted = $derived(
        post.starts_at
            ? new Date(post.starts_at).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' })
            : '',
    );
</script>

{#snippet row()}
    <div class="flex items-center justify-between py-2">
        <span class="text-2xl font-light text-neutral-900">{post.title}</span>
        <span class="flex items-center gap-4">
            <span class="text-2xl text-neutral-500">{formatted}</span>
            <span class="text-3xl font-thin text-[var(--geko-violet)]">→</span>
        </span>
    </div>
{/snippet}

<div class="border-b border-black">
    {#if href}
        <Link {href} class="block hover:opacity-70">{@render row()}</Link>
    {:else}
        {@render row()}
    {/if}
</div>
