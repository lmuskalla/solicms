<script lang="ts">
    import { onDestroy, onMount } from 'svelte';
    import { Editor } from '@tiptap/core';
    import StarterKit from '@tiptap/starter-kit';
    import Link from '@tiptap/extension-link';
    import Image from '@tiptap/extension-image';
    import Placeholder from '@tiptap/extension-placeholder';
    import FileHandler from '@tiptap/extension-file-handler';

    interface Props {
        value: string;
        modelType: 'section' | 'post';
        modelId: number;
    }

    let { value = $bindable(''), modelType, modelId }: Props = $props();

    let element: HTMLDivElement;
    let editor: Editor;
    // Bumped on every Tiptap transaction so the toolbar's active-state classes
    // re-evaluate — Tiptap's internal state isn't a Svelte rune.
    let revision = $state(0);

    let imageInput: HTMLInputElement;
    let fileInput: HTMLInputElement;
    let uploadingImage = $state(false);
    let uploadingFile = $state(false);
    let uploadError = $state('');

    const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];

    function xsrfToken(): string {
        const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
        return match ? decodeURIComponent(match[1]) : '';
    }

    async function upload(file: File): Promise<{ url: string; filename: string; mimeType: string }> {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('model_type', modelType);
        formData.append('model_id', String(modelId));
        formData.append('collection', 'attachments');

        const response = await fetch('/admin/media', {
            method: 'POST',
            headers: { 'X-XSRF-TOKEN': xsrfToken(), Accept: 'application/json' },
            credentials: 'same-origin',
            body: formData,
        });

        if (!response.ok) {
            const body = await response.json().catch(() => null);
            throw new Error(body?.errors?.file?.[0] ?? 'Hochladen fehlgeschlagen.');
        }

        return response.json();
    }

    // Images are embedded inline; anything else (a PDF) is inserted as a
    // normal link with the original filename as its visible text — readers
    // click it to download.
    function insertUploaded(url: string, filename: string, mimeType: string, pos?: number): void {
        const chain = editor.chain().focus();

        if (mimeType.startsWith('image/')) {
            // Defaults to empty (decorative), not the raw uploaded filename —
            // a filename like "IMG_4213.jpg" is not a meaningful description
            // for a screen reader. See
            // docs/tasks/2026-08-08_feature-improvements.md TASK-8. Adding a
            // per-inline-image alt field to this flow is a separate,
            // out-of-scope task.
            if (pos !== undefined) {
                chain.insertContentAt(pos, { type: 'image', attrs: { src: url, alt: '' } }).run();
            } else {
                chain.setImage({ src: url, alt: '' }).run();
            }
            return;
        }

        const html = `<a href="${url}" target="_blank" rel="noopener">${filename}</a> `;

        if (pos !== undefined) {
            chain.insertContentAt(pos, html).run();
        } else {
            chain.insertContent(html).run();
        }
    }

    async function uploadAndInsert(files: File[], pos?: number): Promise<void> {
        uploadError = '';

        for (const file of files) {
            try {
                const { url, filename, mimeType } = await upload(file);
                insertUploaded(url, filename, mimeType, pos);
            } catch (err) {
                uploadError = err instanceof Error ? err.message : 'Hochladen fehlgeschlagen.';
            }
        }
    }

    onMount(() => {
        editor = new Editor({
            element,
            extensions: [
                StarterKit,
                Link.configure({ openOnClick: false }),
                Image,
                Placeholder.configure({ placeholder: 'Text eingeben…' }),
                FileHandler.configure({
                    allowedMimeTypes: ALLOWED_MIME_TYPES,
                    onDrop: (_editor, files, pos) => uploadAndInsert(files, pos),
                    onPaste: (_editor, files) => uploadAndInsert(files),
                }),
            ],
            content: value,
            editorProps: {
                attributes: {
                    class: 'prose prose-gray max-w-none focus:outline-none min-h-[10rem] px-4 py-3',
                },
            },
            onUpdate: ({ editor }) => {
                value = editor.getHTML();
            },
            onTransaction: () => {
                revision++;
            },
        });
    });

    onDestroy(() => editor?.destroy());

    function isActive(name: string, attrs?: Record<string, unknown>): boolean {
        revision;
        return editor?.isActive(name, attrs) ?? false;
    }

    function setLink(): void {
        const previousUrl = editor.getAttributes('link').href;
        const url = window.prompt('Link-URL', previousUrl ?? 'https://');

        if (url === null) return;

        if (url === '') {
            editor.chain().focus().extendMarkRange('link').unsetLink().run();
            return;
        }

        editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
    }

    async function handlePickedFile(event: Event, busyFlag: 'image' | 'file'): Promise<void> {
        const input = event.currentTarget as HTMLInputElement;
        const file = input.files?.[0];
        if (!file) return;

        if (busyFlag === 'image') uploadingImage = true;
        else uploadingFile = true;

        await uploadAndInsert([file]);

        if (busyFlag === 'image') uploadingImage = false;
        else uploadingFile = false;
        input.value = '';
    }
</script>

{#snippet toolbarButton(label: string, active: boolean, onclick: () => void, disabled?: boolean)}
    <button
        type="button"
        {onclick}
        disabled={disabled ?? false}
        class="cursor-pointer rounded-md px-2.5 py-1.5 text-sm font-medium transition-colors disabled:cursor-default disabled:opacity-50 {active
            ? 'bg-admin-primary text-white'
            : 'text-admin-text-secondary hover:bg-admin-bg'}"
    >
        {label}
    </button>
{/snippet}

<div class="overflow-hidden rounded-lg border border-admin-border focus-within:border-admin-primary focus-within:ring-1 focus-within:ring-admin-primary">
    <div class="flex flex-wrap items-center gap-1 border-b border-admin-border bg-admin-bg p-1.5">
        {@render toolbarButton('Fett', isActive('bold'), () => editor.chain().focus().toggleBold().run())}
        {@render toolbarButton('Kursiv', isActive('italic'), () => editor.chain().focus().toggleItalic().run())}
        {@render toolbarButton('H2', isActive('heading', { level: 2 }), () =>
            editor.chain().focus().toggleHeading({ level: 2 }).run(),
        )}
        {@render toolbarButton('H3', isActive('heading', { level: 3 }), () =>
            editor.chain().focus().toggleHeading({ level: 3 }).run(),
        )}
        {@render toolbarButton('Liste', isActive('bulletList'), () => editor.chain().focus().toggleBulletList().run())}
        {@render toolbarButton('Nummeriert', isActive('orderedList'), () =>
            editor.chain().focus().toggleOrderedList().run(),
        )}
        {@render toolbarButton('Zitat', isActive('blockquote'), () => editor.chain().focus().toggleBlockquote().run())}
        {@render toolbarButton('Link', isActive('link'), setLink)}

        <span class="mx-1 h-5 w-px bg-admin-border"></span>

        {@render toolbarButton(uploadingImage ? 'Bild wird hochgeladen…' : 'Bild einfügen', false, () => imageInput.click(), uploadingImage)}
        {@render toolbarButton(uploadingFile ? 'Datei wird hochgeladen…' : 'Datei anhängen', false, () => fileInput.click(), uploadingFile)}

        <input bind:this={imageInput} type="file" accept="image/*" class="hidden" onchange={(e) => handlePickedFile(e, 'image')} />
        <input bind:this={fileInput} type="file" accept="image/*,application/pdf" class="hidden" onchange={(e) => handlePickedFile(e, 'file')} />
    </div>

    <div bind:this={element}></div>

    {#if uploadError}
        <p class="border-t border-admin-border px-4 py-2 text-sm text-admin-error">{uploadError}</p>
    {/if}

    <p class="border-t border-admin-border px-4 py-1.5 text-xs text-admin-text-muted">
        Bilder und PDFs können auch direkt in den Text gezogen oder eingefügt werden.
    </p>
</div>
