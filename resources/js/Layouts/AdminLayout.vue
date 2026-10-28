<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import {
    Menu,
    X,
    Store,
    User,
    LogOut,
    ChevronDown,
    Shield,
    Wrench,
    Search
} from 'lucide-vue-next'

import Sidebar from '@/Components/Sidebar.vue'
import ConfirmBox from '@/Components/ConfirmBox.vue'
import GlobalSearch from '@/Components/GlobalSearch.vue'
// Thông báo
import NotificationBell from '@/Components/NotificationBell.vue'
import WorkShiftAutoOpen from '@/Components/WorkShiftAutoOpen.vue'
import { openModal } from '@/Stores/modal'
import { prepareOpenPos } from '@/utils/pos'
import { imageUrl } from '@/utils/imageUrl'
import { appBasePath } from '@/utils/appPaths'

const sidebarOpen = ref(false)
const isDesktop = ref(false)
const userDropdownOpen = ref(false)
const showMobileSearch = ref(false)

// Trạng thái Collapse của Sidebar
const sidebarCollapsed = ref(
    JSON.parse(localStorage.getItem('sidebar-collapsed') || 'false')
)

const effectiveSidebarCollapsed = computed(() =>
    isDesktop.value && sidebarCollapsed.value
)

const page = usePage()
const user = computed(() => page.props.auth?.user || { name: 'Administrator', email: 'admin@gmail.com' })
const shopName = computed(() => page.props.settings?.shop_name || 'QLBH POS')
const userRoles = computed(() => page.props.auth?.roles || [])
const roleTitle = computed(() => userRoles.value[0] || 'Nhân viên')
const permissions = computed(() => page.props.auth?.permissions || [])
const can = (permission) => permissions.value.includes(permission)

const currentTitle = computed(() => {
    const rawPath = page.url.split('?')[0].replace(/\/+$/, '') || '/'
    const path = appBasePath && rawPath.startsWith(`${appBasePath}/`)
        ? rawPath.slice(appBasePath.length)
        : rawPath

    const componentTitles = {
        'Dashboard': 'Dashboard',
        'Products/Index': 'Sản phẩm',
        'Products/Form': 'Thông tin sản phẩm',
        'Products/Show': 'Chi tiết sản phẩm',
        'ProductImeis/Index': 'IMEI',
        'ProductImeis/Lookup': 'Tra cứu IMEI',
        'ProductImeis/Show': 'Chi tiết IMEI',
        'Categories/Index': 'Danh mục',
        'Brands/Index': 'Thương hiệu',
        'Customers/Index': 'Khách hàng',
        'Customers/Create': 'Thêm khách hàng',
        'Customers/Edit': 'Sửa khách hàng',
        'Customers/Show': 'Chi tiết khách hàng',
        'Sales/Index': 'Hóa đơn bán hàng',
        'Sales/Show': 'Chi tiết hóa đơn',
        'Sales/Edit': 'Sửa hóa đơn',
        'StockImports/Index': 'Nhập hàng',
        'StockImports/List': 'Phiếu nhập hàng',
        'Suppliers/Index': 'Nhà cung cấp',
        'Suppliers/Create': 'Thêm nhà cung cấp',
        'Suppliers/Edit': 'Sửa nhà cung cấp',
        'Repairs/Index': 'Sửa chữa thiết bị',
        'Users/Index': 'Nhân viên',
        'Users/Create': 'Thêm nhân viên',
        'Users/Edit': 'Sửa nhân viên',
        'Users/RoleManagement': 'Phân quyền',
        'Debts/Index': 'Quản lý công nợ',
        'Expenses/Index': 'Chi phí cửa hàng',
        'Taxes/Index': 'Khai thuế',
        'Reports/Index': 'Báo cáo & Thống kê',
        'WorkShifts/History': 'Lịch sử kiểm kê',
        'Settings/Index': 'Thiết lập',
        'Backups/Index': 'Sao lưu dữ liệu',
        'Profile/Edit': 'Tài khoản cá nhân',
    }

    const componentTitle = componentTitles[page.component]

    if (path.startsWith('/settings')) return page.props.active_section === 'backups' ? 'Sao lưu dữ liệu' : 'Thiết lập'
    if (page.component === 'Records/Show' && page.props.title) return page.props.title
    if (componentTitle) return componentTitle

    if (path.startsWith('/pos')) return path.includes('payment-settings') ? 'Thiết lập thanh toán POS' : 'POS bán hàng'
    if (path.startsWith('/products')) return 'Sản phẩm'
    if (path.startsWith('/product-imeis') || path.startsWith('/imeis')) return 'IMEI'
    if (path.startsWith('/categories') || path.startsWith('/brands')) return 'Danh mục & Thương hiệu'
    if (path.startsWith('/customers')) return 'Khách hàng'
    if (path.startsWith('/sales')) return 'Hóa đơn'
    if (path.startsWith('/work-shifts')) return 'Lịch sử kiểm kê'
    if (path.startsWith('/debts/customers')) return 'Thu công nợ khách hàng'
    if (path.startsWith('/debts/suppliers')) return 'Thanh toán công nợ nhà cung cấp'
    if (path.startsWith('/repairs')) return 'Sửa chữa thiết bị'
    if (path.startsWith('/users')) return 'Nhân viên'
    if (path.startsWith('/stock-import')) return 'Nhập hàng'
    if (path.startsWith('/suppliers')) return 'Nhà cung cấp'
    if (path.startsWith('/expenses')) return 'Chi phí cửa hàng'
    if (path.startsWith('/tax-declaration')) return 'Khai thuế'
    if (path.startsWith('/reports')) return 'Báo cáo & Thống kê'
    if (path.startsWith('/backups')) return 'Sao lưu dữ liệu'
    if (path.startsWith('/profile')) return 'Tài khoản'

    return page.component?.split('/').at(-1)?.replace(/([a-z0-9])([A-Z])/g, '$1 $2') || 'Dashboard'
})

watch([shopName, currentTitle], ([name, title]) => {
    document.documentElement.lang = page.props.settings?.app_locale || 'vi'
    document.documentElement.dataset.shopName = name
    document.title = `${title} - ${name}`
}, { immediate: true })

const toggleSidebar = () => {
    sidebarCollapsed.value = !sidebarCollapsed.value
    localStorage.setItem(
        'sidebar-collapsed',
        JSON.stringify(sidebarCollapsed.value)
    )
}

const updateViewport = () => {
    isDesktop.value = window.matchMedia('(min-width: 1024px)').matches
    if (isDesktop.value) {
        sidebarOpen.value = false
        showMobileSearch.value = false
    }
}

const handleLogout = () => {
    router.post('/logout')
}

const openRepairReceipt = async () => {
    const { default: RepairFormModal } = await import('@/Pages/Repairs/RepairFormModal.vue')
    openModal(RepairFormModal)
}

onMounted(() => {
    updateViewport()
    window.addEventListener('resize', updateViewport)
})

onBeforeUnmount(() => {
    window.removeEventListener('resize', updateViewport)
})
</script>

<template>
<div class="h-dvh w-full max-w-full overflow-hidden bg-slate-100/80 text-slate-900 flex flex-col font-sans antialiased select-none">

    <!-- MOBILE OVERLAY CHO SIDEBAR -->
    <div
        v-if="sidebarOpen"
        class="fixed inset-0 z-40 bg-slate-950/60 backdrop-blur-xs lg:hidden transition-opacity"
        @click="sidebarOpen = false"
    ></div>

    <!-- ====== LAYOUT FLEX ====== -->
    <div class="flex h-full w-full overflow-hidden">

        <!-- SIDEBAR WRAPPER -->
        <div
            class="fixed inset-y-0 left-0 z-50 h-screen transition-all duration-300 lg:static shrink-0 shadow-xl lg:shadow-none"
            :class="[
                sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
                effectiveSidebarCollapsed ? 'lg:w-[70px]' : 'w-[280px] lg:w-[240px]'
            ]"
        >
            <Sidebar
                :collapsed="effectiveSidebarCollapsed"
                @navigate="sidebarOpen = false"
                @toggle="toggleSidebar"
            />
        </div>

        <!-- MAIN CONTENT AREA -->
        <div class="flex-1 flex flex-col h-full min-w-0 overflow-hidden bg-slate-50">

            <!-- HEADER HOÀN CHỈNH ĐÃ THÊM NÚT SỬA CHỮA & TỐI ƯU MOBILE -->
            <header class="shrink-0 border-b border-slate-200/80 bg-white z-30 shadow-2xs relative">
                <div class="flex h-16 items-center justify-between px-3 sm:px-6 gap-2 sm:gap-4">

                    <!-- GÓC TRÁI: MOBILE MENU TOGGLE & TIÊU ĐỀ -->
                    <div class="flex items-center gap-2 sm:gap-3 shrink-0 min-w-0">
                        <button
                            type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100 lg:hidden transition active:scale-95 shrink-0"
                            @click="sidebarOpen = !sidebarOpen"
                            aria-label="Mở menu"
                        >
                            <X v-if="sidebarOpen" :size="18" />
                            <Menu v-else :size="18" />
                        </button>

                        <div class="min-w-0">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 truncate hidden sm:block">
                                Hệ thống quản lý
                            </div>
                            <h1 class="text-base sm:text-lg font-black text-slate-900 truncate leading-tight">
                                {{ currentTitle }}
                            </h1>
                        </div>
                    </div>

                    <!-- GÓC PHẢI: TÌM KIẾM, NÚT TÁC VỤ & USER -->
                    <div class="flex items-center gap-2 sm:gap-3 shrink-0">

                        <!-- TÌM KIẾM GLOBAL (DESKTOP: Hiện cố định kích thước) -->
                        <div class="hidden md:block w-44 lg:w-64 shrink-0">
                            <GlobalSearch />
                        </div>

                        <!-- BUTTON TÌM KIẾM (MOBILE: Bấm để bật/tắt ô search) -->
                        <button
                            type="button"
                            @click="showMobileSearch = !showMobileSearch"
                            class="md:hidden flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100 transition active:scale-95 shrink-0"
                            title="Tìm kiếm"
                        >
                            <Search :size="18" />
                        </button>

                        <!-- NÚT 1: TIẾP NHẬN MÁY SỬA -->
                        <button
                            v-if="can('repairs.create')"
                            type="button"
                            @click="openRepairReceipt"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-amber-500 px-2.5 sm:px-3 py-2 text-xs font-bold text-white shadow-md shadow-amber-500/20 hover:bg-amber-600 active:scale-95 transition-all shrink-0"
                            title="Tiếp nhận sửa chữa"
                        >
                            <Wrench :size="15" />
                            <span class="hidden lg:inline">Nhận máy sửa</span>
                        </button>

                        <!-- NÚT 2: MỞ POS BÁN HÀNG -->
                        <Link
                            v-if="can('pos.access')"
                            href="/pos"
                            @click="prepareOpenPos"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-2.5 sm:px-3 py-2 text-xs font-bold text-white shadow-md shadow-blue-600/20 hover:bg-blue-700 active:scale-95 transition-all shrink-0"
                            title="Mở POS bán hàng"
                        >
                            <Store :size="15" />
                            <span class="hidden sm:inline">Mở POS</span>
                        </Link>

                        <!-- THÔNG BÁO -->
                        <NotificationBell />

                        <div class="h-6 w-px bg-slate-200 hidden sm:block shrink-0"></div>

                        <!-- USER DROPDOWN MENU -->
                        <div class="relative shrink-0">
                            <button
                                type="button"
                                @click="userDropdownOpen = !userDropdownOpen"
                                class="flex items-center gap-2 p-1 sm:p-1.5 rounded-xl border border-slate-200/80 hover:bg-slate-50 active:bg-slate-100 transition"
                            >
                                <div class="h-8 w-8 overflow-hidden rounded-lg bg-cyan-950 text-white font-black text-xs flex items-center justify-center shadow-xs shrink-0">
                                    <img v-if="user.avatar" :src="imageUrl(user.avatar)" alt="Ảnh đại diện" class="h-full w-full object-cover">
                                    <span v-else>{{ (user.name || 'A').charAt(0).toUpperCase() }}</span>
                                </div>
                                <div class="hidden xl:block text-left">
                                    <div class="text-xs font-bold text-slate-800 leading-tight truncate max-w-[100px]">
                                        {{ user.name }}
                                    </div>
                                    <div class="text-[10px] font-medium text-slate-400">
                                        {{ roleTitle }}
                                    </div>
                                </div>
                                <ChevronDown :size="14" class="text-slate-400 hidden sm:block transition-transform duration-200" :class="userDropdownOpen && 'rotate-180'" />
                            </button>

                            <!-- DROPDOWN TÀI KHOẢN -->
                            <div
                                v-if="userDropdownOpen"
                                @click="userDropdownOpen = false"
                                class="absolute right-0 mt-2 w-52 rounded-2xl bg-white p-1.5 shadow-xl border border-slate-100 z-50 text-xs animate-in fade-in slide-in-from-top-2 duration-150"
                            >
                                <div class="px-3 py-2 border-b border-slate-100 mb-1">
                                    <p class="font-bold text-slate-900 truncate">{{ user.name }}</p>
                                    <p class="text-[11px] text-slate-400 truncate">{{ user.email || user.username }}</p>
                                </div>

                                <Link
                                    href="/profile"
                                    class="flex items-center gap-2 px-3 py-2 rounded-xl text-slate-700 hover:bg-slate-100 font-medium transition"
                                >
                                    <User :size="15" class="text-slate-400" />
                                    <span>Tài khoản cá nhân</span>
                                </Link>

                                <Link
                                    v-if="can('settings.view')"
                                    href="settings"
                                    class="flex items-center gap-2 px-3 py-2 rounded-xl text-slate-700 hover:bg-slate-100 font-medium transition"
                                >
                                    <Shield :size="15" class="text-slate-400" />
                                    <span>Thiết lập hệ thống</span>
                                </Link>

                                <div class="my-1 border-t border-slate-100"></div>

                                <button
                                    type="button"
                                    @click="handleLogout"
                                    class="w-full flex items-center gap-2 px-3 py-2 rounded-xl text-rose-600 hover:bg-rose-50 font-bold text-left transition"
                                >
                                    <LogOut :size="15" />
                                    <span>Đăng xuất</span>
                                </button>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- KHU VỰC TÌM KIẾM DÀNH CHO MOBILE (Trượt xuống khi bấm nút kính lúp) -->
                <div v-if="showMobileSearch" class="md:hidden border-t border-slate-200/80 p-2.5 bg-slate-50 animate-in slide-in-from-top-2 duration-150">
                    <GlobalSearch @selected="showMobileSearch = false" />
                </div>
            </header>

            <!-- MAIN CONTENT AREA -->
            <main class="flex-1 overflow-y-auto p-2 sm:p-3 lg:p-4">
                <div class="mx-auto w-full max-w-[1760px] space-y-3">
                    <slot :key="page.url" />
                </div>
            </main>

        </div>
    </div>
</div>

<ConfirmBox />
<WorkShiftAutoOpen />
</template>
