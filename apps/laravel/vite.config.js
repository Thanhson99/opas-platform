import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    css: {
        preprocessorMaxWorkers: 0,
        preprocessorOptions: {
            scss: {
                api: 'modern',
            },
        },
    },
    plugins: [
        laravel({
            input: ['resources/js/app.js', 'resources/scss/app.scss', 'resources/css/app.css'],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
