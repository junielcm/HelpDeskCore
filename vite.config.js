import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        vue(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js/src', import.meta.url)),
        },
    },
    server: {
        // En Docker el dev server corre en otro contenedor, asi que la URL que
        // se publica en public/hot debe ser la que ve el navegador, no la del
        // contenedor. Sin esta variable el comportamiento es el de siempre.
        origin: process.env.VITE_DEV_ORIGIN || undefined,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
        hmr: {
            host: 'localhost',
        },
    },
});
