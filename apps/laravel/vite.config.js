import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

const shouldUsePolling = process.env.VITE_USE_POLLING === 'true';
const pollingInterval = Number(process.env.VITE_POLLING_INTERVAL ?? 500);

export default defineConfig({
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (!id.includes('node_modules')) {
                        return undefined;
                    }

                    if (id.includes('/react-router') || id.includes('/@remix-run/router')) {
                        return 'vendor-router';
                    }

                    if (
                        id.includes('/react/') ||
                        id.includes('/react-dom/') ||
                        id.includes('/scheduler/')
                    ) {
                        return 'vendor-react';
                    }

                    return 'vendor';
                },
            },
        },
    },
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
            usePolling: shouldUsePolling,
            interval: pollingInterval,
        },
    },
    plugins: [
        laravel({
            input: [
                'resources/js/app.jsx',
                'resources/scss/error-page.scss',
            ],
            refresh: true,
        }),
        react(),
    ],
});
