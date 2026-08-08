import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import { svelte } from '@sveltejs/vite-plugin-svelte';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
                // Admin UI typeface — see DESIGN.md.
                bunny('Inter', {
                    weights: [400, 500, 600, 700],
                }),
            ],
        }),
        tailwindcss(),
        svelte(),
    ],
    server: {
        // Vite runs inside a container; bind to all interfaces so the published
        // port is reachable, and advertise an origin the browser can resolve.
        // laravel-vite-plugin writes server.origin into the hot file verbatim.
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        origin: 'http://localhost:5173',
        // Vite 8 only allows its own origin by default. The app is served from
        // :8000 on localhost and on tenant subdomains, so allow those explicitly
        // rather than opening the dev server to every origin.
        cors: {
            origin: [
                /^https?:\/\/(?:[a-z0-9-]+\.)*localhost(?::\d+)?$/,
                /^https?:\/\/127\.0\.0\.1(?::\d+)?$/,
            ],
        },
        hmr: {
            host: 'localhost',
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
