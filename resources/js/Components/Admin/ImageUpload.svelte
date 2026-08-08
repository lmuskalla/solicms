<script lang="ts">
    interface Props {
        value: string;
        alt?: string;
        modelType: 'section' | 'post';
        modelId: number;
        collection?: string;
    }

    let { value = $bindable(''), alt = $bindable(''), modelType, modelId, collection = 'content' }: Props = $props();

    let uploading = $state(false);
    let error = $state('');
    let fileInput: HTMLInputElement;

    function xsrfToken(): string {
        const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
        return match ? decodeURIComponent(match[1]) : '';
    }

    async function handleChange(event: Event): Promise<void> {
        const input = event.currentTarget as HTMLInputElement;
        const file = input.files?.[0];
        if (!file) return;

        uploading = true;
        error = '';

        const formData = new FormData();
        formData.append('file', file);
        formData.append('model_type', modelType);
        formData.append('model_id', String(modelId));
        formData.append('collection', collection);

        try {
            const response = await fetch('/admin/media', {
                method: 'POST',
                headers: {
                    'X-XSRF-TOKEN': xsrfToken(),
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
                body: formData,
            });

            if (!response.ok) {
                const body = await response.json().catch(() => null);
                throw new Error(body?.errors?.file?.[0] ?? 'Hochladen fehlgeschlagen.');
            }

            const data = (await response.json()) as { url: string };
            value = data.url;
        } catch (err) {
            error = err instanceof Error ? err.message : 'Hochladen fehlgeschlagen.';
        } finally {
            uploading = false;
            input.value = '';
        }
    }
</script>

<div class="space-y-3">
    {#if value}
        <div class="overflow-hidden rounded-lg border border-admin-border">
            <img src={value} alt="" class="h-40 w-full object-cover" />
        </div>

        <div>
            <label for="alt-text" class="mb-1.5 block text-sm font-medium text-admin-text-secondary">Alt-Text (Bildbeschreibung)</label>
            <input
                id="alt-text"
                type="text"
                bind:value={alt}
                placeholder="Kurze Beschreibung des Bildes, z. B. für Screenreader"
                class="w-full rounded-lg border border-admin-border px-3.5 py-2.5 text-sm text-admin-text shadow-sm transition-colors focus:border-admin-primary focus:outline-none focus:ring-1 focus:ring-admin-primary"
            />
        </div>
    {/if}

    <div class="flex items-center gap-3">
        <button
            type="button"
            onclick={() => fileInput.click()}
            disabled={uploading}
            class="flex cursor-pointer items-center gap-2 rounded-lg border border-admin-border bg-admin-card px-3.5 py-2 text-sm font-medium text-admin-text-secondary shadow-sm transition-colors hover:bg-admin-bg disabled:opacity-50"
        >
            {#if uploading}
                <span class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-admin-text-muted/40 border-t-admin-text-secondary"></span>
            {/if}
            {uploading ? 'Wird hochgeladen…' : value ? 'Bild ersetzen' : 'Bild auswählen'}
        </button>
        {#if value}
            <button
                type="button"
                onclick={() => (value = '')}
                class="cursor-pointer text-sm text-admin-text-muted hover:text-admin-error"
            >
                Entfernen
            </button>
        {/if}
    </div>

    <input
        bind:this={fileInput}
        type="file"
        accept="image/*"
        class="hidden"
        onchange={handleChange}
    />

    {#if error}
        <p class="text-sm text-admin-error">{error}</p>
    {/if}
</div>
