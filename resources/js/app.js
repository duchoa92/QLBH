import '../css/app.css';
import './bootstrap';

import { createApp, defineAsyncComponent, h } from 'vue';
import { createInertiaApp, router} from '@inertiajs/vue3';
import { Toaster, toast  } from 'vue-sonner';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import 'vue-sonner/style.css'
import clickOutside from '@/Directives/clickOutside'
import ModalRoot from '@/Components/ModalRoot.vue'
import { formatMoney, formatCurrency, formatDate, formatDateTime, getCurrencySymbol, setFormatSettings } from '@/utils/format'
import { notifyReferenceDataChanged } from '@/Stores/referenceData'
import { startLiveSync } from '@/Services/liveSync'
import { installAppPathSupport, appBaseUrl } from '@/utils/appPaths'
import { installInsecureNotice, syncInsecureNotice } from '@/utils/insecureNotice'
import { installMobileTables } from '@/utils/mobileTables'
import { installViewportFix } from '@/utils/viewportFix'
import { reloadWhenIdle } from '@/utils/pageBusy'
import { installModalScrollLock } from '@/utils/modalScrollLock'
import { pageTitles } from '@/utils/pageTitles'

const AdminLayout = defineAsyncComponent(() => import('@/Layouts/AdminLayout.vue'))
const pageComponents = import.meta.glob([
    './Pages/**/*.vue',
    '!./Pages/Backups/Index.vue',
    '!./Pages/Brands/Form.vue',
    '!./Pages/Customers/CustomerFormModal.vue',
    '!./Pages/ProductImeis/PriceFormModal.vue',
    '!./Pages/Products/*Modal.vue',
    '!./Pages/Profile/Partials/**/*.vue',
    '!./Pages/Repairs/Repair*.vue',
    '!./Pages/StockImports/ReplenishmentListModal.vue',
    '!./Pages/StockImports/StockImportFormModal.vue',
    '!./Pages/StockImports/StockImportProductModal.vue',
    '!./Pages/StockImports/SupplierSection.vue',
    '!./Pages/Suppliers/Form.vue',
    '!./Pages/Suppliers/Partials/**/*.vue',
    '!./Pages/Users/FormModal.vue',
    '!./Pages/Users/RoleManagementDialog.vue',
    '!./Pages/Users/RoleManagementModal.vue',
])

installAppPathSupport(router)
installInsecureNotice()
installMobileTables()
installViewportFix()
installModalScrollLock()

if ('serviceWorker' in navigator && (window.isSecureContext || location.hostname === 'localhost')) {
    // A newly activated worker owns an already-open tab only after
    // `controllerchange`. Reload once so that an ordinary F5 immediately
    // reflects the new cache policy instead of requiring Shift+F5.
    let reloadedForServiceWorker = false
    navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (reloadedForServiceWorker) return
        reloadedForServiceWorker = true
        // Không tải lại giữa lúc đang nhập liệu: đợi trang rảnh (xem utils/pageBusy.js).
        reloadWhenIdle()
    })

    navigator.serviceWorker.register(new URL('service-worker.js', appBaseUrl), {
        scope: new URL('./', appBaseUrl).pathname,
        updateViaCache: 'none',
    }).then(registration => registration.update())
        .catch(error => console.warn('Không thể đăng ký hỗ trợ offline.', error))
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const pageTitleFor = (page) => {
    const title = page?.props?.title || pageTitles[page?.component]
        || page?.component?.split('/').at(-1)?.replace(/([a-z0-9])([A-Z])/g, '$1 $2')
        || 'Dashboard'
    const shopName = page?.props?.settings?.shop_name
    return `${title} - ${shopName || appName}`
}

let resolvedDocumentTitle = `QLBH - ${appName}`

 // ❗ CHẶN ĐĂNG KÝ NHIỀU LẦN
if (!window._inertiaToastRegistered) {

    window._inertiaToastRegistered = true

    let nativeValidationToastTimer = null
    let nativeValidationToastShown = false

    const normalizeFieldName = (name) => String(name || '').replace(/\[([^\]]+)\]/g, '.$1')
    const markInvalidFields = (errors) => {
        const controls = Array.from(document.querySelectorAll('input, select, textarea'))
            .filter((control) => control.name || control.id)
        const firstFieldName = Object.keys(errors || {})[0]
        let firstField = null

        Object.entries(errors || {}).forEach(([name, message]) => {
            const field = controls.find((control) => {
                const controlNames = [control.name, control.id].map(normalizeFieldName).filter(Boolean)
                return controlNames.some((controlName) => controlName === name || controlName.startsWith(`${name}.`))
            })

            if (!field) return

            field.dataset.submitInvalid = 'true'
            field.setAttribute('aria-invalid', 'true')
            if (!firstField && (normalizeFieldName(field.name) === firstFieldName || field.name === firstFieldName)) firstField = field
        })

        return firstField || controls.find((field) => [field.name, field.id].some((value) => normalizeFieldName(value) === firstFieldName)) || null
    }

    document.addEventListener('invalid', (event) => {
        const field = event.target
        if (!(field instanceof HTMLElement)) return

        field.dataset.submitInvalid = 'true'
        field.setAttribute('aria-invalid', 'true')

        if (nativeValidationToastTimer) return
        nativeValidationToastShown = false
        nativeValidationToastTimer = setTimeout(() => {
            nativeValidationToastTimer = null
            if (nativeValidationToastShown) return
            nativeValidationToastShown = true
            const invalidField = document.querySelector('[data-submit-invalid="true"]:invalid')
            const label = invalidField?.labels?.[0]?.textContent?.trim()
            toast.error(label ? `Vui lòng kiểm tra trường: ${label}` : 'Vui lòng điền đầy đủ và đúng các trường bắt buộc.')
        }, 0)
    }, true)

    const clearSubmitInvalid = (event) => {
        const field = event.target
        if (!(field instanceof HTMLElement) || !field.matches('input, select, textarea')) return
        delete field.dataset.submitInvalid
        field.removeAttribute('aria-invalid')
    }

    document.addEventListener('input', clearSubmitInvalid, true)
    document.addEventListener('change', clearSubmitInvalid, true)

    router.on('error', (event) => {

        const errors = event.detail.errors
        if (!errors) return

        const firstMessage = Object.values(errors).flat().find(Boolean)
        if (firstMessage) toast.error(firstMessage)

        setTimeout(() => {
            const el = markInvalidFields(errors)

            if (el) {
                el.focus()
                el.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                })
            }
        }, 100)
    })

    router.on('success', (event) => {

    const method = event.detail.visit?.method?.toLowerCase()

    if (method && !['get', 'head'].includes(method)) {
        notifyReferenceDataChanged()
    }

    const page = event.detail.page
    const flash = page.props.flash

    if (!flash) return

    if (flash.success) {
        toast.success(flash.success, { id: flash.success })
    }

    if (flash.error) {
        toast.error(flash.error)
    }

    // QUAN TRỌNG: XÓA FLASH SAU KHI DÙNG
    page.props.flash = {}
})
}

createInertiaApp({
    title: (title) => title ? `${title} - ${appName}` : resolvedDocumentTitle,
    resolve: (name) => resolvePageComponent(
        `./Pages/${name}.vue`,
        pageComponents
    ).then((page) => {


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
                page.default.layout === undefined &&
                adminModules.some(
                    (module) =>
                        name.startsWith(module)
                )
            ) {

                page.default.layout =
                    AdminLayout
            }


            return page

        }),
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

        
        const applyPageSettings = (page) => {
            const settings = page.props.settings || {}
            setFormatSettings(settings)
            document.documentElement.lang = settings.app_locale || 'vi'

            let printStyle = document.getElementById('configured-print-page')
            if (!printStyle) {
                printStyle = document.createElement('style')
                printStyle.id = 'configured-print-page'
                document.head.appendChild(printStyle)
            }

            const component = page.component || ''
            const printComponents = ['Sales/Receipt']
            if (!printComponents.some((name) => component.endsWith(name))) {
                printStyle.textContent = ''
                return
            }

            const paper = settings.print_paper_size || 'a4'
            const sizes = { a4: 'A4', a5: 'A5', '80mm': '80mm 200mm', '58mm': '58mm 150mm' }
            const thermal = paper === '80mm' || paper === '58mm'
            const orientation = thermal ? '' : ` ${settings.print_orientation === 'landscape' ? 'landscape' : 'portrait'}`
            const pageSize = `${sizes[paper] || sizes.a4}${orientation}`
            const pageMargin = thermal ? '0' : '10mm'
            const receiptWidth = paper === '58mm' ? '54mm' : paper === '80mm' ? '76mm' : '100%'
            printStyle.textContent = `@media print { :root { --configured-receipt-width: ${receiptWidth}; } @page { size: ${pageSize}; margin: ${pageMargin}; } .receipt-container { width: var(--configured-receipt-width) !important; } }`
        }
        applyPageSettings(props.initialPage)
        // Tự cập nhật dữ liệu các trang khi có thay đổi (xem Services/liveSync.js).
        startLiveSync(props.initialPage)
        resolvedDocumentTitle = pageTitleFor(props.initialPage)
        document.title = resolvedDocumentTitle
        router.on('navigate', (event) => {
            const currentPage = event.detail.page
            applyPageSettings(currentPage)
            startLiveSync(currentPage)
            syncInsecureNotice()
            resolvedDocumentTitle = pageTitleFor(currentPage)
            document.title = resolvedDocumentTitle
            navigator.serviceWorker?.controller?.postMessage({
                type: 'CACHE_OFFLINE_PAGE',
                page: currentPage,
                html: document.documentElement.outerHTML,
            })
        })

        app.config.globalProperties.$money = formatCurrency
        app.config.globalProperties.$currency = getCurrencySymbol
        app.config.globalProperties.$date = formatDate
        app.config.globalProperties.$dateTime = formatDateTime

        app.use(ZiggyVue, window.Ziggy)

            
        app.mount(el)
    },
    progress: {
        color: '#4B5563',
    },
});