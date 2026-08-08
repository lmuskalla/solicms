<script lang="ts">
    interface NavEntry {
        key: string;
        label: string;
    }

    interface Props {
        sections: NavEntry[];
        selectedKey: string;
        fixed: boolean;
    }

    let { sections, selectedKey, fixed }: Props = $props();
</script>

<nav id="site-navigation" class:fixed>
    <ul>
        {#each sections as s (s.key)}
            <li class:selected={s.key === selectedKey}>
                <a href={`#${s.key}`}>{s.label}</a>
            </li>
        {/each}
    </ul>
</nav>
