<script lang="ts">
    import type { Component } from 'svelte';

    // Lazy loaders, not eager — nothing here is fetched or evaluated until the
    // specific key for the current request is actually invoked below. Admin
    // pages never mount this component, so they never touch this at all;
    // visiting a `default`-themed tenant never even downloads `dvm`'s files.
    const componentLoaders = import.meta.glob<{ default: Component }>('/resources/themes/**/*.svelte');
    const styleLoaders = import.meta.glob('/resources/themes/**/style.css');

    interface Props {
        page: { title: string; template: string };
        sections: Record<string, { value: string }>;
        config: Record<string, string>;
        nav: Array<{ label: string; href: string }>;
        footerNav: Array<{ label: string; href: string }>;
        themeComponent: string;
        url: string;
    }

    let { page, sections, config, nav, footerNav, themeComponent, url }: Props = $props();

    let Template = $state<Component | null>(null);

    // Re-runs on every Inertia navigation that changes themeComponent — visits
    // to different pages under the same tenant reuse this component instance
    // rather than remounting it, since Frontend\PageController always renders
    // the same 'Frontend/Page'.
    $effect(() => {
        const theme = themeComponent.split('/')[0];
        const componentPath = `/resources/themes/${themeComponent}.svelte`;
        const stylePath = `/resources/themes/${theme}/style.css`;
        const fallbackPath = '/resources/themes/default/Wysiwyg.svelte';

        let cancelled = false;

        Promise.all([
            (componentLoaders[componentPath] ?? componentLoaders[fallbackPath])(),
            (styleLoaders[stylePath] ?? (() => Promise.resolve()))(),
        ]).then(([mod]) => {
            if (!cancelled) {
                Template = (mod as { default: Component }).default;
            }
        });

        return () => {
            cancelled = true;
        };
    });
</script>

<!-- Svelte 5: render the component variable directly; <svelte:component> is deprecated -->
{#if Template}
    <Template {page} {sections} {config} {nav} {footerNav} {url} />
{/if}
