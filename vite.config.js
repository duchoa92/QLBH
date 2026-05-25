import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({

    plugins: [

        laravel({
            input: ['resources/js/app.js'],
            refresh: true,
        }),

        vue(),
    ],

    server: {
        host: 'qlbh.test',
        port: 5173,
        strictPort: true,
        hmr: {
            host: 'qlbh.test',
        },
    },

});