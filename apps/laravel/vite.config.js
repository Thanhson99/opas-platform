import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

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
            input: ['resources/js/app.jsx', 'resources/scss/app.scss'],
            refresh: true,
        }),
        react(),
    ],
});
