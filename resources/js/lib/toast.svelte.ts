/**
 * Module-level toast store for the admin UI.
 *
 * Svelte 5 runes at module scope give every admin component a single shared,
 * reactive toast: any save flow can call `showToast('Gespeichert')` and the
 * one `Toast.svelte` mounted in Admin/Layout.svelte renders it. Auto-dismiss
 * (~2.5s) mirrors the 2s timeout the inline "Gespeichert" badges already use.
 *
 * The state lives in one `$state` object (rather than `export const message =
 * $state('')`) because Svelte 5 forbids exporting a module-level `$state`
 * binding that is reassigned — mutating the state object's properties is the
 * supported pattern.
 */
export const toast = $state({
    message: '',
    visible: false,
});

let hideTimer: ReturnType<typeof setTimeout> | undefined;

export function showToast(text: string): void {
    toast.message = text;
    toast.visible = true;

    clearTimeout(hideTimer);
    hideTimer = setTimeout(() => {
        toast.visible = false;
    }, 2500);
}
