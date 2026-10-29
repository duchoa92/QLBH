import fs from 'node:fs';
import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

const appEnv = loadEnv('development', process.cwd(), '');
const devHost = appEnv.VITE_DEV_HOST || new URL(appEnv.APP_URL || 'http://qlbh.test').hostname;
const appOrigin = appEnv.APP_URL ? new URL(appEnv.APP_URL).origin : `https://${devHost}`;
const laragonCert = 'C:/laragon/etc/ssl/laragon.crt';
const laragonKey = 'C:/laragon/etc/ssl/laragon.key';
const devCert = appEnv.VITE_DEV_CERT || laragonCert;
const devKey = appEnv.VITE_DEV_KEY || laragonKey;
const httpsEnabled = appEnv.VITE_DEV_HTTPS === 'true';

if (httpsEnabled && (!fs.existsSync(devCert) || !fs.existsSync(devKey))) {
    throw new Error(`Vite HTTPS is enabled, but its certificate or key is missing (${devCert}, ${devKey}).`);
}

const httpsOptions = httpsEnabled
    ? { cert: fs.readFileSync(devCert), key: fs.readFileSync(devKey) }
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
        host: true,
        port: 5173,
        strictPort: true,
        origin: `${httpsOptions ? 'https' : 'http'}://${devHost}:5173`,
        cors: {
            origin: [...new Set([appOrigin, `https://${devHost}`, `http://${devHost}`, 'https://qlbh.test', 'http://qlbh.test'])],
        },
        allowedHosts: [...new Set([devHost, 'qlbh.test'])],
        https: httpsOptions,
        hmr: {
            host: devHost,
            protocol: httpsOptions ? 'wss' : 'ws',
        },
    },

});
