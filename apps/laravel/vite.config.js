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
    server: {
        headers: {
            'Cache-Control': 'no-store, no-cache, must-revalidate, max-age=0',
            Pragma: 'no-cache',
            Expires: '0',
        },
        watch: {
            usePolling: true,
            interval: 150,
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
