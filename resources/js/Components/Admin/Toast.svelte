<script lang="ts">
    import { fly } from 'svelte/transition';
    import { quintOut } from 'svelte/easing';
    import { toast } from '../../lib/toast.svelte';
</script>

<!-- One instance per admin page, mounted once in Admin/Layout.svelte. Reads
     the module-level toast store from lib/toast.svelte.ts, so any save flow
     (section, config, post, page template/published) can trigger it without
     wiring anything. Fixed top-right, above the z-50 modals (PostsManager,
     ConfirmDialog) so saves made inside modals still show the toast. -->
{#if toast.visible}
    <div
        role="status"
        aria-live="polite"
        transition:fly={{ y: -12, duration: 250, easing: quintOut }}
        class="fixed right-4 top-4 z-[60] flex items-center gap-2.5 rounded-admin-card border border-admin-border border-l-4 border-l-admin-success bg-admin-card px-4 py-3 shadow-xl shadow-admin-success/10"
    >
        <svg viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 shrink-0 text-admin-success">
            <path
                fill-rule="evenodd"
                d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z"
                clip-rule="evenodd"
            />
        </svg>
        <span class="text-sm text-admin-text">{toast.message}</span>
    </div>
{/if}
