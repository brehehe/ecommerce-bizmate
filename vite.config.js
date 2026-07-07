import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { svelte } from '@sveltejs/vite-plugin-svelte';
import tailwindcss from '@tailwindcss/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import inertia from '@inertiajs/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
        }),
        inertia(),
        svelte(),
        tailwindcss(),
        wayfinder(),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});
