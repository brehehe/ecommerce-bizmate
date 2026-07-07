import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import { svelte } from '@sveltejs/vite-plugin-svelte';
import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

export default defineConfig({
    server: {
        host: 'localhost',
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            fonts: [
                bunny('Plus Jakarta Sans', {
                    weights: [300, 400, 500, 600, 700, 800],
                }),
                bunny('Outfit', {
                    weights: [300, 400, 500, 600, 700, 800],
                }),
                bunny('Inter', {
                    weights: [300, 400, 500, 600, 700, 800],
                }),
                bunny('Roboto', {
                    weights: [300, 400, 500, 700],
                }),
                bunny('Poppins', {
                    weights: [300, 400, 500, 600, 700, 800],
                }),
                bunny('Montserrat', {
                    weights: [300, 400, 500, 600, 700, 800],
                }),
                bunny('Nunito', {
                    weights: [300, 400, 500, 600, 700, 800],
                }),
                bunny('Ubuntu', {
                    weights: [300, 400, 500, 700],
                }),
                bunny('Playfair Display', {
                    weights: [400, 500, 600, 700],
                }),
                bunny('Merriweather', {
                    weights: [300, 400, 700],
                }),
            ],
        }),
        inertia(),
        tailwindcss(),
        svelte(),
        wayfinder({
            formVariants: true,
        }),
    ],
});
