<script lang="ts">
    import { Link } from '@inertiajs/svelte';

    interface NavItem {
        label: string;
        href: string;
    }

    interface Props {
        config: Record<string, string>;
        footerNav?: NavItem[];
    }

    let { config, footerNav = [] }: Props = $props();
</script>

<footer>
    <div class="footer-inner">
        <div class="flex flex-col justify-between gap-8 md:flex-row md:gap-12">
            <div class="footer-brand">
                <a href="/" title="Zur Startseite" class="footer-wordmark">DVM</a>
                <p>{config.footer_text}</p>
            </div>

            <nav class="footer-menu">
                <ul>
                    {#each footerNav as item (item.href)}
                        <li><Link href={item.href}>{item.label}</Link></li>
                    {/each}
                    {#if config.contact_email}
                        <li><a href={`mailto:${config.contact_email}`}>{config.contact_email}</a></li>
                    {/if}
                </ul>
            </nav>
        </div>

        <div class="footer-bottom">© {new Date().getFullYear()} DVM Bündnis &mdash; Alle Rechte vorbehalten</div>
    </div>
</footer>
