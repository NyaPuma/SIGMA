import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                // Ficheiros do Swagger UI:
                'resources/css/swagger/swagger.css',
                'resources/js/swagger/utils.js',
                'resources/js/swagger/search.js',
                'resources/js/swagger/badges.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        hmr: {
            host: '10.204.66.201',
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});