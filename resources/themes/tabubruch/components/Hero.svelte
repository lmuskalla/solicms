<script lang="ts">
    import logoUrl from '../assets/images/logo-animation.gif';

    interface NavEntry {
        key: string;
        label: string;
    }

    interface Props {
        tagline: string;
        shrunk: boolean;
        subtitleVisible: boolean;
        subtitleFast: boolean;
        mobileMenuOpen: boolean;
        sections: NavEntry[];
        contactEmail: string;
        logoWidth: number;
        onToggleMenu: () => void;
    }

    let { tagline, shrunk, subtitleVisible, subtitleFast, mobileMenuOpen, sections, contactEmail, logoWidth, onToggleMenu }: Props =
        $props();
</script>

<header id="logo">
    <div id="logoContainer" class:shrunk>
        <img
            src={logoUrl}
            title="Tabubruch Beratung"
            alt="Tabubruch Beratung Logo"
            style={shrunk ? undefined : `width: ${logoWidth}vw`}
        />

        <div class="subtitle" class:visible={subtitleVisible} style={subtitleFast ? 'transition-duration: 0.2s' : undefined}>
            <span>Tabubruch</span>
            <span>{tagline}</span>
        </div>

        <div class="cta">
            <div class="cta-subtitle">Tabubruch - {tagline}</div>
            {#if contactEmail}
                <a href={`mailto:${contactEmail}`}>Kontaktier mich</a>
            {/if}
        </div>

        <button type="button" class="mobile-menu" class:change={mobileMenuOpen} onclick={onToggleMenu} aria-label="Menü öffnen">
            <span class="bar1"></span>
            <span class="bar2"></span>
            <span class="bar3"></span>
        </button>

        <nav id="mobile-navigation" class:open={mobileMenuOpen}>
            <ul>
                {#each sections as s (s.key)}
                    <li><a href={`#${s.key}`} onclick={onToggleMenu}>{s.label}</a></li>
                {/each}
            </ul>
        </nav>
    </div>
</header>
