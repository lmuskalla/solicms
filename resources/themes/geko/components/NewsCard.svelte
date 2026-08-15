<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import PlaceholderPhoto from './PlaceholderPhoto.svelte';
    import type { PostRecord } from '../../../js/types';

    let { post }: { post: PostRecord } = $props();

    const href = $derived(post.slug ? `/aktuelles/${post.slug}` : null);
</script>

{#snippet card()}
    <div class="relative aspect-square overflow-hidden rounded-l-2xl bg-[var(--geko-violet-tint)]">
        {#if post.image}
            <img src={post.image} alt="" class="h-full w-full object-cover" />
        {:else}
            <!-- Honest placeholder — no fabricated stock photo standing in
                 for a real GEKO photo. See THEMES.md; the editor uploads a
                 real one via the "Bild" field once they have one. -->
            <PlaceholderPhoto />
        {/if}
        <span
            class="absolute bottom-3 left-3 max-w-[calc(100%-1.5rem)] truncate rounded-full bg-white/90 px-3 py-1 text-sm font-medium text-[var(--geko-violet)] shadow-sm"
        >
            {post.title}
        </span>
    </div>
{/snippet}

{#if href}
    <Link {href} class="block focus-visible:outline-none">{@render card()}</Link>
{:else}
    {@render card()}
{/if}
