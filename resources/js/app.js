import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { Toaster } from 'vue-sonner';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import 'vue-sonner/style.css'


const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ).then((page) => {

            const adminModules = [
                'Brands/',
                'Categories/',
                'Customers/',
                'ProductImeis/',
                'Products/',
                'Repairs/',
                'Sales/',
            ]

            if (
                !page.default.layout &&
                adminModules.some((module) => name.startsWith(module))
            ) {
                page.default.layout = AdminLayout
            }

            return page
        }),
    setup({ el, App, props, plugin }) {

        createApp({

            render: () => [
                h(App, props),
                h(Toaster, {
                    richColors: true,
                    position: 'top-right',
                    closeButton: true,
                }),
            ],

        })
            .component(
                'Toaster',
                Toaster
            )
            .use(plugin)
            .use(ZiggyVue)
            .mount(el)
    },
    progress: {
        color: '#4B5563',
    },
});
