<script lang="ts">
    import { onMount } from 'svelte';
    import type { Snippet } from 'svelte';
    import { Link, router, page } from '@inertiajs/svelte';
    import type { Auth } from '../../types';

    interface Props {
        auth?: Auth;
        children: Snippet;
    }

    let { auth, children }: Props = $props();

    // Loaded only here, at runtime — never bundled into pages that don't
    // mount this component (i.e. never on a tenant's public site).
    onMount(() => {
        import('../../../css/admin.css');
    });

    let loggingOut = $state(false);

    function abmelden(): void {
        loggingOut = true;
        router.post('/admin/logout');
    }

    // Shared by HandleInertiaRequests — false for a theme with no use for
    // inter-page navigation (e.g. a single-page site with nothing to link
    // between). Hiding the entry beats showing a screen that does nothing.
    const usesPageNav = $derived((page.props.usesPageNav as boolean | undefined) ?? true);

    const navItems = $derived(
        [
            { href: '/admin', label: 'Übersicht', match: (url: string) => url === '/admin' },
            { href: '/admin/pages', label: 'Seiten', match: (url: string) => url.startsWith('/admin/pages') },
            usesPageNav && {
                href: '/admin/navigation',
                label: 'Navigation',
                match: (url: string) => url.startsWith('/admin/navigation'),
            },
            { href: '/admin/settings', label: 'Einstellungen', match: (url: string) => url.startsWith('/admin/settings') },
        ].filter((item): item is Exclude<typeof item, false> => item !== false),
    );

    const initials = $derived(
        (auth?.user?.name ?? '?')
            .split(' ')
            .map((part) => part[0])
            .slice(0, 2)
            .join('')
            .toUpperCase(),
    );

    // Shared by every Inertia response (HandleInertiaRequests::share) — the
    // domain actually being visited, since one install serves every tenant.
    const domain = $derived((page.props.domain as string | undefined) ?? 'Verwaltung');
</script>

<!-- Navy sidebar matches the login screen's brand panel — one consistent
     color style throughout, not a light shell that only shows up after
     signing in. -->
<div class="flex min-h-screen bg-admin-bg font-sans text-admin-text">
    <aside class="flex w-64 shrink-0 flex-col justify-between bg-admin-text text-white">
        <div>
            <div class="flex items-center gap-2 px-6 py-6">
                <img src="/images/brand/mark.png" alt="solicms" class="h-8 w-8" />
                <a
                    href="/"
                    target="_blank"
                    rel="noopener"
                    title="Website ansehen"
                    class="truncate text-sm font-semibold tracking-tight text-white hover:underline"
                >
                    {domain}
                </a>
            </div>

            <nav class="mt-2 space-y-1 px-3">
                {#each navItems as item}
                    {@const active = item.match(page.url)}
                    <Link
                        href={item.href}
                        class="block rounded-lg px-3 py-2 text-sm font-medium transition-colors {active
                            ? 'bg-white/10 text-white'
                            : 'text-white/60 hover:bg-white/5 hover:text-white'}"
                    >
                        {item.label}
                    </Link>
                {/each}
            </nav>
        </div>

        <div class="border-t border-white/10 p-4">
            <div class="flex items-center gap-3 rounded-lg px-2 py-2">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/10 text-xs font-semibold text-white">
                    {initials}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-white">{auth?.user?.name ?? ''}</p>
                    <p class="truncate text-xs text-white/60">{auth?.user?.email ?? ''}</p>
                </div>
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
        <div class="mx-auto max-w-4xl">
            {@render children()}
        </div>
    </main>
</div>
