<script lang="ts">
    interface Props {
        open: boolean;
        title: string;
        message: string;
        confirmLabel?: string;
        cancelLabel?: string;
        danger?: boolean;
        onConfirm: () => void;
    }

    let {
        open = $bindable(false),
        title,
        message,
        confirmLabel = 'Löschen',
        cancelLabel = 'Abbrechen',
        danger = true,
        onConfirm,
    }: Props = $props();

    let confirmButton: HTMLButtonElement | undefined = $state();

    function close(): void {
        open = false;
    }

    function confirm(): void {
        open = false;
        onConfirm();
    }

    function onWindowKeydown(event: KeyboardEvent): void {
        if (open && event.key === 'Escape') close();
    }

    function onOverlayClick(event: MouseEvent): void {
        if (event.target === event.currentTarget) close();
    }

    // Autofocus so Enter confirms immediately and Escape works without the
    // user needing to click into the dialog first.
    $effect(() => {
        if (open) confirmButton?.focus();
    });
</script>

<svelte:window onkeydown={onWindowKeydown} />

{#if open}
    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-admin-text/40 p-4"
        onclick={onOverlayClick}
        role="presentation"
    >
        <div
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="confirm-dialog-title"
            aria-describedby="confirm-dialog-message"
            class="w-full max-w-sm rounded-admin-card border border-admin-border bg-admin-card p-6 shadow-lg"
        >
            <h2 id="confirm-dialog-title" class="text-base font-semibold text-admin-text">{title}</h2>
            <p id="confirm-dialog-message" class="mt-2 text-sm text-admin-text-secondary">{message}</p>

            <div class="mt-6 flex justify-end gap-3">
                <button
                    type="button"
                    onclick={close}
                    class="cursor-pointer rounded-lg border border-admin-border bg-admin-card px-3.5 py-2 text-sm font-medium text-admin-text-secondary shadow-sm transition-colors hover:bg-admin-bg"
                >
                    {cancelLabel}
                </button>
                <button
                    bind:this={confirmButton}
                    type="button"
                    onclick={confirm}
                    class="cursor-pointer rounded-lg px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition-colors {danger
                        ? 'bg-admin-error hover:bg-admin-error/90'
                        : 'bg-admin-primary hover:bg-admin-primary-hover'}"
                >
                    {confirmLabel}
                </button>
            </div>
        </div>
    </div>
{/if}
