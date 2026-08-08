<script lang="ts">
    import { onMount, untrack } from 'svelte';
    import { router } from '@inertiajs/svelte';

    interface Props {
        token: string;
        email: string;
        errors?: { email?: string; password?: string };
    }

    let { token, email: initialEmail, errors = {} }: Props = $props();

    onMount(() => {
        import('../../../css/admin.css');
    });

    let email = $state(untrack(() => initialEmail));
    let password = $state('');
    let passwordConfirmation = $state('');
    let processing = $state(false);

    function submit(event: SubmitEvent): void {
        event.preventDefault();
        processing = true;

        router.post(
            '/admin/reset-password',
            { token, email, password, password_confirmation: passwordConfirmation },
            { onFinish: () => (processing = false) },
        );
    }
</script>

<svelte:head>
    <title>Passwort zurücksetzen</title>
</svelte:head>

<div class="flex min-h-screen items-center justify-center bg-admin-bg px-6 font-sans">
    <div class="w-full max-w-sm">
        <h2 class="mb-1 text-xl font-semibold text-admin-text">Neues Passwort festlegen</h2>
        <p class="mb-8 text-sm text-admin-text-secondary">Wählen Sie ein neues Passwort für Ihr Konto.</p>

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
                {#if errors.email}
                    <p class="mt-1 text-sm text-admin-error">{errors.email}</p>
                {/if}
            </div>

            <div>
                <label for="password" class="mb-1.5 block text-sm font-medium text-admin-text-secondary">Neues Passwort</label>
                <input
                    id="password"
                    type="password"
                    bind:value={password}
                    autocomplete="new-password"
                    required
                    minlength="8"
                    class="w-full rounded-lg border border-admin-border bg-admin-card px-3.5 py-2.5 text-sm text-admin-text shadow-sm transition-colors focus:border-admin-primary focus:outline-none focus:ring-1 focus:ring-admin-primary"
                />
                {#if errors.password}
                    <p class="mt-1 text-sm text-admin-error">{errors.password}</p>
                {/if}
            </div>

            <div>
                <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-admin-text-secondary">
                    Passwort bestätigen
                </label>
                <input
                    id="password_confirmation"
                    type="password"
                    bind:value={passwordConfirmation}
                    autocomplete="new-password"
                    required
                    minlength="8"
                    class="w-full rounded-lg border border-admin-border bg-admin-card px-3.5 py-2.5 text-sm text-admin-text shadow-sm transition-colors focus:border-admin-primary focus:outline-none focus:ring-1 focus:ring-admin-primary"
                />
            </div>

            <button
                type="submit"
                disabled={processing}
                class="flex w-full items-center justify-center gap-2 rounded-lg bg-admin-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-admin-primary-hover disabled:opacity-50 cursor-pointer"
            >
                {#if processing}
                    <span class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                {/if}
                {processing ? 'Wird gespeichert…' : 'Passwort ändern'}
            </button>
        </form>
    </div>
</div>
