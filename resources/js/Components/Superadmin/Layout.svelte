<script lang="ts">
    import { onMount } from 'svelte';
    import type { Snippet } from 'svelte';
    import { router } from '@inertiajs/svelte';
    import type { Auth } from '../../types';

    interface Props {
        auth?: Auth;
        children: Snippet;
    }

    let { auth, children }: Props = $props();

    // Same admin design system as the tenant admin UI — one product, one
    // look — loaded lazily so it never reaches a tenant's public site.
    onMount(() => {
        import('../../../css/admin.css');
    });

    let loggingOut = $state(false);

    function abmelden(): void {
        loggingOut = true;
        router.post('/superadmin/logout');
    }
</script>

<div class="flex min-h-screen bg-admin-bg font-sans text-admin-text">
    <aside class="flex w-64 shrink-0 flex-col justify-between bg-admin-text text-white">
        <div>
            <div class="flex items-center gap-2 px-6 py-6">
                <img src="/images/brand/mark.png" alt="solicms" class="h-8 w-8" />
                <span class="text-sm font-semibold tracking-tight text-white">Plattform</span>
            </div>

            <nav class="mt-2 space-y-1 px-3">
                <span class="block rounded-lg bg-white/10 px-3 py-2 text-sm font-medium text-white">Tenants</span>
            </nav>
        </div>

        <div class="border-t border-white/10 p-4">
            <div class="min-w-0 px-2 py-2">
                <p class="truncate text-sm font-medium text-white">{auth?.user?.name ?? ''}</p>
                <p class="truncate text-xs text-white/60">{auth?.user?.email ?? ''}</p>
            </div>
            <button
                onclick={abmelden}
                disabled={loggingOut}
                class="mt-2 flex w-full items-center gap-2 rounded-lg px-2 py-2 text-sm text-white/60 transition-colors hover:bg-white/5 hover:text-white disabled:opacity-50 cursor-pointer"
            >
                <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                    <path
                        fill-rule="evenodd"
                        d="M3 4.25A2.25 2.25 0 0 1 5.25 2h5.5A2.25 2.25 0 0 1 13 4.25v2a.75.75 0 0 1-1.5 0v-2a.75.75 0 0 0-.75-.75h-5.5a.75.75 0 0 0-.75.75v11.5c0 .414.336.75.75.75h5.5a.75.75 0 0 0 .75-.75v-2a.75.75 0 0 1 1.5 0v2A2.25 2.25 0 0 1 10.75 18h-5.5A2.25 2.25 0 0 1 3 15.75V4.25Z"
                        clip-rule="evenodd"
                    />
                    <path
                        fill-rule="evenodd"
                        d="M6 10a.75.75 0 0 1 .75-.75h9.19l-2.72-2.72a.75.75 0 1 1 1.06-1.06l4 4a.75.75 0 0 1 0 1.06l-4 4a.75.75 0 1 1-1.06-1.06l2.72-2.72H6.75A.75.75 0 0 1 6 10Z"
                        clip-rule="evenodd"
                    />
                </svg>
                Abmelden
            </button>
        </div>
    </aside>

    <main class="flex-1 px-8 py-10">
        <div class="mx-auto max-w-5xl">
            {@render children()}
        </div>
    </main>
</div>
