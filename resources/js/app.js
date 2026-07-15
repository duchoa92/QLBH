import '../css/app.css';
import './bootstrap';

import { createApp, h} from 'vue';
import { createInertiaApp, router} from '@inertiajs/vue3';
import { Toaster, toast  } from 'vue-sonner';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import 'vue-sonner/style.css'
import clickOutside from '@/Directives/clickOutside'
import ModalRoot from '@/Components/ModalRoot.vue'


const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

 // ❗ CHẶN ĐĂNG KÝ NHIỀU LẦN
if (!window._inertiaToastRegistered) {

    window._inertiaToastRegistered = true

    let lastFlash = null

    router.on('error', (event) => {

        const errors = event.detail.errors
        if (!errors) return

        Object.values(errors).forEach(msg => {
            if (msg) toast.error(msg)
        })

        setTimeout(() => {
            const first = Object.keys(errors)[0]
            const el = document.querySelector(`[name="${first}"]`)

            if (el) {
                el.focus()
                el.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                })
            }
        }, 100)
    })

    let shownMessages = new Set()

    router.on('success', (event) => {

    const page = event.detail.page
    const flash = page.props.flash

    if (!flash) return

    if (flash.success) {
        toast.success(flash.success)
    }

    if (flash.error) {
        toast.error(flash.error)
    }

    // QUAN TRỌNG: XÓA FLASH SAU KHI DÙNG
    page.props.flash = {}
})
}

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
                'Suppliers/',
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

        const Root = {
            setup() {

               return () => [
                    h(App, props),

                    // 🔥 QUAN TRỌNG: ModalRoot phải ở đây
                    h(ModalRoot),

                    h(Toaster, {
                        richColors: true,
                        position: 'top-right',
                        closeButton: true,
                        style: {
                            zIndex: 9999,
                            position: 'fixed'
                        },
                    }),
                ]
            }
        }

        const app = createApp(Root)

        app.component(
            'Toaster',
            Toaster
        )

        app.directive(
            'click-outside',
            clickOutside
        )

        app.use(plugin)

        app.use(ZiggyVue)

            
        app.mount(el)
    },
    progress: {
        color: '#4B5563',
    },
});
