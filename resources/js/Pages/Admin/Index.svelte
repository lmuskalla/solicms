<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import Layout from '../../Components/Admin/Layout.svelte';
    import type { Auth } from '../../types';

    interface Props {
        auth?: Auth;
    }

    let { auth }: Props = $props();
</script>

<svelte:head>
    <title>Verwaltung</title>
</svelte:head>

{#snippet arrow()}
    <svg
        viewBox="0 0 20 20"
        fill="currentColor"
        class="h-5 w-5 shrink-0 text-admin-text-muted transition-transform group-hover:translate-x-0.5 group-hover:text-admin-primary"
    >
        <path
            fill-rule="evenodd"
            d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z"
            clip-rule="evenodd"
        />
    </svg>
{/snippet}

<Layout {auth}>
    {#snippet children()}
        <h1 class="text-2xl font-semibold tracking-tight text-admin-text">
            Willkommen{auth?.user ? `, ${auth.user.name}` : ''}
        </h1>
        <p class="mt-1 text-sm text-admin-text-secondary">Hier finden Sie eine Übersicht Ihrer Website.</p>

        <div class="mt-8 space-y-4">
            <Link
                href="/admin/pages"
                class="group flex items-center justify-between rounded-admin-card border border-admin-border bg-admin-card p-6 shadow-admin-card transition-all hover:border-admin-primary/30"
            >
                <div class="flex items-center gap-4">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-admin-primary/10 text-admin-primary">
                        <svg viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                            <path d="M4 4a2 2 0 0 1 2-2h5.379a2 2 0 0 1 1.414.586l2.621 2.621A2 2 0 0 1 16 6.621V16a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4Z" />
                        </svg>
                    </div>
                    <div>
                        <p class="font-medium text-admin-text">Seiten bearbeiten</p>
                        <p class="text-sm text-admin-text-secondary">Texte, Bilder und Inhalte Ihrer Website anpassen.</p>
                    </div>
                </div>
                {@render arrow()}
            </Link>

            <Link
                href="/admin/navigation"
                class="group flex items-center justify-between rounded-admin-card border border-admin-border bg-admin-card p-6 shadow-admin-card transition-all hover:border-admin-primary/30"
            >
                <div class="flex items-center gap-4">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-admin-primary/10 text-admin-primary">
                        <svg viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                            <path
                                fill-rule="evenodd"
                                d="M2 4.75A.75.75 0 0 1 2.75 4h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 4.75ZM2 10a.75.75 0 0 1 .75-.75h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 10Zm0 5.25a.75.75 0 0 1 .75-.75h14.5a.75.75 0 0 1 0 1.5H2.75a.75.75 0 0 1-.75-.75Z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </div>
                    <div>
                        <p class="font-medium text-admin-text">Navigation</p>
                        <p class="text-sm text-admin-text-secondary">Menü Ihrer Website zusammenstellen und ordnen.</p>
                    </div>
                </div>
                {@render arrow()}
            </Link>

            <Link
                href="/admin/settings"
                class="group flex items-center justify-between rounded-admin-card border border-admin-border bg-admin-card p-6 shadow-admin-card transition-all hover:border-admin-primary/30"
            >
                <div class="flex items-center gap-4">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-admin-primary/10 text-admin-primary">
                        <svg viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                            <path
                                fill-rule="evenodd"
                                d="M11.078 2.25c-.917 0-1.699.663-1.85 1.567L9.05 4.889c-.02.12-.115.26-.297.348a7.493 7.493 0 0 0-.986.57c-.166.115-.334.126-.45.083L6.3 5.508a1.875 1.875 0 0 0-2.282.819l-.922 1.597a1.875 1.875 0 0 0 .432 2.385l.84.692c.095.078.17.229.154.43a7.598 7.598 0 0 0 0 1.139c.015.2-.059.352-.153.43l-.841.692a1.875 1.875 0 0 0-.432 2.385l.922 1.597a1.875 1.875 0 0 0 2.282.818l1.019-.382c.115-.043.283-.031.45.082.312.214.641.405.985.57.182.088.277.228.297.35l.178 1.071c.151.904.933 1.567 1.85 1.567h1.844c.916 0 1.699-.663 1.85-1.567l.178-1.072c.02-.12.114-.26.297-.349.344-.165.673-.356.985-.57.167-.114.335-.125.45-.082l1.02.382a1.875 1.875 0 0 0 2.28-.819l.923-1.597a1.875 1.875 0 0 0-.432-2.385l-.84-.692c-.095-.078-.17-.229-.154-.43a7.614 7.614 0 0 0 0-1.139c-.016-.2.059-.352.153-.43l.84-.692c.708-.582.891-1.59.433-2.385l-.922-1.597a1.875 1.875 0 0 0-2.282-.818l-1.02.382c-.114.043-.282.031-.449-.083a7.49 7.49 0 0 0-.985-.57c-.183-.087-.277-.227-.297-.348l-.179-1.072a1.875 1.875 0 0 0-1.85-1.567h-1.843ZM12 15.75a3.75 3.75 0 1 0 0-7.5 3.75 3.75 0 0 0 0 7.5Z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </div>
                    <div>
                        <p class="font-medium text-admin-text">Einstellungen</p>
                        <p class="text-sm text-admin-text-secondary">Name, Kontakt-E-Mail und Fußzeilentext Ihrer Website.</p>
                    </div>
                </div>
                {@render arrow()}
            </Link>
        </div>
    {/snippet}
</Layout>
