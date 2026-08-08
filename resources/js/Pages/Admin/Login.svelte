<script lang="ts">
    import { onMount } from 'svelte';
    import { Link, router } from '@inertiajs/svelte';

    interface Props {
        errors?: { email?: string };
    }

    let { errors = {} }: Props = $props();

    // The only admin page that doesn't wrap in Components/Admin/Layout.svelte,
    // so it needs its own copy of the same lazy admin.css import.
    onMount(() => {
        import('../../../css/admin.css');
    });

    let email = $state('');
    let password = $state('');
    let remember = $state(false);
    let processing = $state(false);

    function submit(event: SubmitEvent): void {
        event.preventDefault();
        processing = true;

        router.post(
            '/admin/login',
            { email, password, remember },
            {
                onFinish: () => {
                    processing = false;
                    password = '';
                },
            },
        );
    }
</script>

<svelte:head>
    <title>Anmelden</title>
</svelte:head>

<div class="flex min-h-screen font-sans">
    <!-- Dark ink background, not the coral brand color: the logo mark is
         itself coral/teal/amber/periwinkle, so a solid coral panel would
         make its own coral half disappear into the background. -->
    <div class="hidden w-1/2 flex-col justify-between bg-admin-text p-12 text-white lg:flex">
        <div class="flex items-center gap-2">
            <img src="/images/brand/mark.png" alt="solicms" class="h-8 w-8" />
            <span class="text-sm font-semibold tracking-tight">solicms</span>
        </div>

        <div>
            <h1 class="text-3xl font-semibold tracking-tight">Willkommen zurück.</h1>
            <p class="mt-3 max-w-sm text-white/80">Melden Sie sich an, um die Inhalte Ihrer Website zu bearbeiten.</p>
        </div>

        <p class="text-xs text-white/60">&copy; {new Date().getFullYear()}</p>
    </div>

    <div class="flex flex-1 items-center justify-center bg-admin-bg px-6">
        <div class="w-full max-w-sm">
            <h2 class="mb-1 text-xl font-semibold text-admin-text">Anmelden</h2>
            <p class="mb-8 text-sm text-admin-text-secondary">Geben Sie Ihre Zugangsdaten ein.</p>

            <form onsubmit={submit} class="space-y-5">
                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium text-admin-text-secondary">E-Mail-Adresse</label>
                    <input
                        id="email"
                        type="email"
                        bind:value={email}
                        autocomplete="username"
                        required
                        class="w-full rounded-lg border border-admin-border bg-admin-card px-3.5 py-2.5 text-sm text-admin-text shadow-sm transition-colors focus:border-admin-primary focus:outline-none focus:ring-1 focus:ring-admin-primary"
                    />
                </div>

                <div>
                    <div class="mb-1.5 flex items-center justify-between">
                        <label for="password" class="text-sm font-medium text-admin-text-secondary">Passwort</label>
                        <Link href="/admin/forgot-password" class="text-sm text-admin-primary hover:text-admin-primary-hover">
                            Passwort vergessen?
                        </Link>
                    </div>
                    <input
                        id="password"
                        type="password"
                        bind:value={password}
                        autocomplete="current-password"
                        required
                        class="w-full rounded-lg border border-admin-border bg-admin-card px-3.5 py-2.5 text-sm text-admin-text shadow-sm transition-colors focus:border-admin-primary focus:outline-none focus:ring-1 focus:ring-admin-primary"
                    />
                </div>

                {#if errors.email}
                    <p class="rounded-lg bg-admin-error/10 px-3 py-2 text-sm text-admin-error">{errors.email}</p>
                {/if}

                <label class="flex items-center gap-2 text-sm text-admin-text-secondary">
                    <input type="checkbox" bind:checked={remember} class="rounded border-admin-border text-admin-primary focus:ring-admin-primary" />
                    Angemeldet bleiben
                </label>

                <button
                    type="submit"
                    disabled={processing}
                    class="flex w-full items-center justify-center gap-2 rounded-lg bg-admin-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-admin-primary-hover disabled:opacity-50 cursor-pointer"
                >
                    {#if processing}
                        <span class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                    {/if}
                    {processing ? 'Anmeldung läuft…' : 'Anmelden'}
                </button>
            </form>
        </div>
    </div>
</div>
