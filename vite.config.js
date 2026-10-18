import fs from 'node:fs';
import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

const appEnv = loadEnv('development', process.cwd(), '');
const devHost = appEnv.VITE_DEV_HOST || new URL(appEnv.APP_URL || 'http://qlbh.test').hostname;
const appOrigin = appEnv.APP_URL ? new URL(appEnv.APP_URL).origin : `https://${devHost}`;
const laragonCert = 'C:/laragon/etc/ssl/laragon.crt';
const laragonKey = 'C:/laragon/etc/ssl/laragon.key';
const httpsOptions = fs.existsSync(laragonCert) && fs.existsSync(laragonKey)
    ? { cert: fs.readFileSync(laragonCert), key: fs.readFileSync(laragonKey) }
    : undefined;

export default defineConfig({

    plugins: [

        laravel({
            input: ['resources/js/app.js'],
            refresh: true,
        }),

        vue(),
    ],

    server: {
        host: devHost,
        port: 5173,
        strictPort: true,
        origin: `https://${devHost}:5173`,
        cors: {
            origin: [...new Set([appOrigin, `https://${devHost}`, `http://${devHost}`])],
        },
        allowedHosts: [...new Set([devHost, 'qlbh.test'])],
        https: httpsOptions,
        hmr: {
            host: devHost,
            protocol: httpsOptions ? 'wss' : 'ws',
        },
    },

});
