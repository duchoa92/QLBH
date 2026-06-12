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
    resolve: (name) => {
        const pages = import.meta.glob(
            [
                './Pages/**/*.vue',
                './Modules/**/*.vue',
            ]
        )


        return resolvePageComponent(
            `./Pages/${name}.vue`,
            pages
        )
        .catch(() => {

            return resolvePageComponent(
                `./${name}.vue`,
                pages
            )

        })
        .then((page) => {


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
                adminModules.some(
                    (module) =>
                        name.startsWith(module)
                )
            ) {

                page.default.layout =
                    AdminLayout
            }


            return page

        })

    },
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
