import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/js/app.js',
                'resources/js/pages/Feed.jsx',
                'resources/js/components/Card.jsx',
                'resources/js/components/CardList.jsx',
                'resources/css/app.css',
            ],
            refresh: true,
        }),
        react(),
    ],
    server: {
        host: '',
        hmr: {
            host: '',
        },
        port: 5173,
        strictPort: true,
        cors: true,
    },
    // build: {
    //     manifest: true,
    //     outDir: 'public/build',
    // },
});
