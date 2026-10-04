import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { fileURLToPath } from 'url';
import path from 'path';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/src/main.tsx'],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            'sonner': path.resolve(__dirname, 'resources/js/src/app/utils/toast.ts'),
        }
    },
    build: {
        rollupOptions: {
            input: ['resources/css/app.css', 'resources/js/src/main.tsx']
        }
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
