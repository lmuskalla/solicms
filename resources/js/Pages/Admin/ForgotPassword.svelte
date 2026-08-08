<script lang="ts">
    import { onMount } from 'svelte';
    import { Link, router } from '@inertiajs/svelte';

    interface Props {
        flash?: { success?: string | null };
    }

    let { flash }: Props = $props();

    onMount(() => {
        import('../../../css/admin.css');
    });

    let email = $state('');
    let processing = $state(false);

    function submit(event: SubmitEvent): void {
        event.preventDefault();
        processing = true;

        router.post(
            '/admin/forgot-password',
            { email },
            { onFinish: () => (processing = false) },
        );
    }
</script>

<svelte:head>
    <title>Passwort vergessen</title>
</svelte:head>

<div class="flex min-h-screen items-center justify-center bg-admin-bg px-6 font-sans">
    <div class="w-full max-w-sm">
        <h2 class="mb-1 text-xl font-semibold text-admin-text">Passwort vergessen</h2>
        <p class="mb-8 text-sm text-admin-text-secondary">
            Geben Sie Ihre E-Mail-Adresse ein — wir senden Ihnen einen Link zum Zurücksetzen.
        </p>

        {#if flash?.success}
            <p class="mb-5 rounded-lg bg-admin-success/10 px-4 py-3 text-sm text-admin-success">{flash.success}</p>
        {/if}

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

            <button
                type="submit"
                disabled={processing}
                class="flex w-full items-center justify-center gap-2 rounded-lg bg-admin-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-admin-primary-hover disabled:opacity-50 cursor-pointer"
            >
                {#if processing}
                    <span class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                {/if}
                {processing ? 'Wird gesendet…' : 'Link senden'}
            </button>

            <Link href="/admin/login" class="block text-center text-sm text-admin-text-secondary hover:text-admin-text">
                Zurück zur Anmeldung
            </Link>
        </form>
    </div>
</div>
