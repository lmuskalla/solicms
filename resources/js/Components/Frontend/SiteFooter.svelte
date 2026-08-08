<script lang="ts">
    interface Props {
        config: Record<string, string>;
    }

    let { config }: Props = $props();
</script>

<footer class="border-t border-gray-100 bg-gray-50">
    <div class="mx-auto max-w-5xl px-6 py-10 text-sm text-gray-500">
        {#if config.footer_text}
            <p>{config.footer_text}</p>
        {/if}
        {#if config.contact_email}
            <p class="mt-1">
                <a href={`mailto:${config.contact_email}`} class="hover:text-gray-900">{config.contact_email}</a>
            </p>
        {/if}
    </div>
</footer>
