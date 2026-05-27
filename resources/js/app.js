import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { Toaster } from 'vue-sonner';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';


const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {

        createApp({

            render: () =>
                h('div', [

                    h(App, props),

                    h(Toaster, {

                        richColors: true,

                        position: 'top-center',

                        expand: true,

                        closeButton: true,

                        duration: 4000,
                        
                        offset: '20px',

                        toastOptions: {
                            style: {
                                zIndex: '999999',
                            },
                        },

                    }),

                ]),

        })
            .use(plugin)
            .mount(el)
    },
    progress: {
        color: '#4B5563',
    },
});
