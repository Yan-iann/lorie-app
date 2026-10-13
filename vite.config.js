import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { google } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                // Inter: drawn for user interfaces, so it stays clear at small sizes.
                // Downloaded at build time and served from this app (no runtime
                // requests to Google). It is a variable font, so one ~47 kB file
                // covers every weight below.
                google('Inter', {
                    weights: [400, 500, 600, 700, 800],
                    // No preloading: the font is split by script (Latin, Cyrillic,
                    // Greek…) and the browser fetches only the part a page uses.
                    // Preloading would force every script on every visit.
                    preload: false,
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
