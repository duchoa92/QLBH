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
import { Ziggy } from './ziggy'
import { formatMoney, formatCurrency, formatDate, formatDateTime, getCurrencySymbol, setFormatSettings } from '@/utils/format'
import { notifyReferenceDataChanged } from '@/Stores/referenceData'
import { installAppPathSupport, appBaseUrl } from '@/utils/appPaths'

installAppPathSupport(router)

if ('serviceWorker' in navigator && (window.isSecureContext || location.hostname === 'localhost')) {
    navigator.serviceWorker.register(new URL('service-worker.js', appBaseUrl), {
        scope: new URL('./', appBaseUrl).pathname,
        updateViaCache: 'none',
    }).catch(error => console.warn('Không thể đăng ký hỗ trợ offline.', error))
}

const app = createApp({})
const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const pageTitleFor = (page) => {
    const titles = {
        Dashboard: 'Dashboard',
        'Products/Index': 'Sản phẩm', 'Products/Form': 'Thông tin sản phẩm', 'Products/Show': 'Chi tiết sản phẩm',
        'ProductImeis/Index': 'IMEI', 'ProductImeis/Lookup': 'Tra cứu IMEI', 'ProductImeis/Show': 'Chi tiết IMEI',
        'Categories/Index': 'Danh mục', 'Categories/Form': 'Thông tin danh mục', 'Brands/Index': 'Thương hiệu',
        'Customers/Index': 'Khách hàng', 'Customers/Create': 'Thêm khách hàng', 'Customers/Edit': 'Sửa khách hàng', 'Customers/Show': 'Chi tiết khách hàng',
        'Sales/Index': 'Hóa đơn bán hàng', 'Sales/Show': 'Chi tiết hóa đơn', 'Sales/Edit': 'Sửa hóa đơn', 'Sales/Receipt': 'Hóa đơn bán hàng',
        'StockImports/Index': 'Nhập hàng', 'StockImports/List': 'Phiếu nhập hàng', 'StockImports/Show': 'Chi tiết phiếu nhập',
        'Suppliers/Index': 'Nhà cung cấp', 'Suppliers/Create': 'Thêm nhà cung cấp', 'Suppliers/Edit': 'Sửa nhà cung cấp',
        'Repairs/Index': 'Sửa chữa thiết bị', 'Users/Index': 'Nhân viên', 'Users/Create': 'Thêm nhân viên', 'Users/Edit': 'Sửa nhân viên',
        'Users/RoleManagement': 'Phân quyền', 'Debts/Index': 'Quản lý công nợ', 'Expenses/Index': 'Chi phí cửa hàng',
        'Taxes/Index': 'Khai thuế', 'Reports/Index': 'Báo cáo & Thống kê', 'WorkShifts/History': 'Lịch sử kiểm kê',
        'Settings/Index': 'Thiết lập', 'Backups/Index': 'Sao lưu dữ liệu', 'Profile/Edit': 'Tài khoản cá nhân',
        'Pos/Index': 'POS bán hàng', 'Pos/SaleInvoice': 'Hóa đơn POS', 'Auth/Login': 'Đăng nhập', 'Auth/Register': 'Đăng ký',
        'Auth/ForgotPassword': 'Quên mật khẩu', 'Auth/ResetPassword': 'Đặt lại mật khẩu', 'Auth/VerifyEmail': 'Xác minh email',
        'Auth/ConfirmPassword': 'Xác nhận mật khẩu', 'Setup/Index': 'Thiết lập ban đầu', 'Welcome': 'Trang chủ',
    }
    const title = page?.props?.title || titles[page?.component]
        || page?.component?.split('/').at(-1)?.replace(/([a-z0-9])([A-Z])/g, '$1 $2')
        || 'Dashboard'
    const shopName = page?.props?.settings?.shop_name
    return `${title} - ${shopName || appName}`
}

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

    const method = event.detail.visit?.method?.toLowerCase()

    if (method && !['get', 'head'].includes(method)) {
        notifyReferenceDataChanged()
    }

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
            const printComponents = ['Sales/Receipt', 'Sales/Show', 'Pos/SaleInvoice']
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
        document.title = pageTitleFor(props.initialPage)
        router.on('navigate', (event) => {
            const currentPage = event.detail.page
            applyPageSettings(currentPage)
            document.title = pageTitleFor(currentPage)
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

        // `@routes` renders the route table for the current request. Prefer it
        // over the generated fallback, which may contain another machine's host.
        app.use(ZiggyVue, window.Ziggy || Ziggy)

            
        app.mount(el)
    },
    progress: {
        color: '#4B5563',
    },
});
