<script lang="ts">
    /**
     * Shared `<svelte:head>` content for every theme's public-facing
     * templates — see docs/tasks/2026-08-08_seo-findings.md TASK-2. Every
     * template previously rendered its own `<title>`-only head block
     * independently; this centralizes title/description/canonical/OG/
     * Twitter tags in one place so the next head-tag fix touches one file
     * instead of N.
     *
     * Data-source decisions (see TASK-1 and TASK-3 — there's no dedicated
     * meta-description or OG-image field in the data model):
     *   - `description`, if not passed explicitly, is derived from the
     *     first text/textarea/wysiwyg section with content on the current
     *     page (HTML stripped, truncated).
     *   - `image`, if not passed explicitly, is derived from the first
     *     `image`-type section with a value on the current page (e.g. a
     *     hero image) — there's no site-wide logo config to fall back to
     *     (see TenantProvisioner::seedSiteConfig()'s doc comment: logos are
     *     hard-defined per theme, not tenant config). Omitted entirely when
     *     no such section exists, rather than guessing.
     *   - `url` is the tenant's canonical URL for the current page (its
     *     primary domain — see Frontend\PageController), used for both
     *     `og:url` and `<link rel="canonical">`.
     */
    interface SectionLike {
        value?: string | null;
        type?: string | null;
        alt?: string | null;
    }

    interface Props {
        title: string;
        config?: Record<string, string>;
        sections?: Record<string, SectionLike>;
        description?: string | null;
        image?: string | null;
        url?: string | null;
        type?: string;
    }

    let { title, sections = {}, description = null, image = null, url = null, type = 'website' }: Props = $props();

    const MAX_DESCRIPTION_LENGTH = 160;

    function stripHtml(html: string): string {
        return html
            .replace(/<[^>]+>/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function truncate(text: string, max: number): string {
        if (text.length <= max) return text;

        return text.slice(0, max - 1).trimEnd() + '…';
    }

    function deriveDescription(): string {
        const candidate = Object.values(sections).find(
            (section) => !!section?.value && ['wysiwyg', 'textarea', 'text'].includes(section.type ?? ''),
        );

        return candidate?.value ? truncate(stripHtml(candidate.value), MAX_DESCRIPTION_LENGTH) : '';
    }

    function deriveImage(): string {
        const candidate = Object.values(sections).find((section) => section?.type === 'image' && !!section.value);

        return candidate?.value ?? '';
    }

    const resolvedDescription = $derived(description ?? deriveDescription());
    const resolvedImage = $derived(image ?? deriveImage());
</script>

<svelte:head>
    <title>{title}</title>

    {#if resolvedDescription}
        <meta name="description" content={resolvedDescription} />
    {/if}

    {#if url}
        <link rel="canonical" href={url} />
    {/if}

    <meta property="og:title" content={title} />
    <meta property="og:type" content={type} />
    {#if url}
        <meta property="og:url" content={url} />
    {/if}
    {#if resolvedDescription}
        <meta property="og:description" content={resolvedDescription} />
    {/if}
    {#if resolvedImage}
        <meta property="og:image" content={resolvedImage} />
    {/if}

    <meta name="twitter:card" content={resolvedImage ? 'summary_large_image' : 'summary'} />
    <meta name="twitter:title" content={title} />
    {#if resolvedDescription}
        <meta name="twitter:description" content={resolvedDescription} />
    {/if}
    {#if resolvedImage}
        <meta name="twitter:image" content={resolvedImage} />
    {/if}
</svelte:head>
