<script setup>
import { prepareOpenPos } from '@/utils/pos'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

import {
    Home,
    ShoppingBag,
    FileText,
    BarChart3,
    Users,
    UserRound,
    Wrench,
    Package,
    Boxes,
    FolderTree,
    Settings,
    ChevronDown,
    ChevronRight,
    Menu,
    Truck,
    EllipsisVertical,
    Monitor,
    UserCog,
    SearchCheck,
    HandCoins,
    Receipt,
    History,
    PanelLeftOpen,
} from 'lucide-vue-next'

const emit = defineEmits(['toggle', 'navigate'])

const props = defineProps({
    collapsed: Boolean,
})

const page = usePage()
const isMobileOpen = ref(false)
const currentPath = computed(() => page.url.split('?')[0])
const shopName = computed(() => page.props.settings?.shop_name || 'QLBH POS')

/* AUTH & PERMISSIONS */
const permissions = computed(() => page.props.auth?.permissions || [])

const can = (permission) => {
    return !permission || permissions.value.includes(permission)
}

const homeUrl = computed(() => {
    const destinations = [
        ['dashboard.view', '/dashboard'],
        ['repairs.view', '/repairs'],
        ['pos.access', '/pos'],
        ['stock_imports.view', '/stock-import'],
        ['products.view', '/products'],
        ['customers.view', '/customers'],
        ['sales.view', '/sales'],
        ['users.view', '/users'],
        ['settings.view', '/settings'],
    ]
    return destinations.find(([permission]) => can(permission))?.[1] || '/profile'
})

/* ACTIVE ROUTE CHECKERS */
const isActive = (paths = []) => {
    return paths.some(path => page.url.startsWith(path))
}

const isCurrentMenu = (paths = [], href = '') => {
    if (currentPath.value === href) return true
    return paths.some(path => currentPath.value.startsWith(path))
}

/* MENU GROUPS */
const menuGroups = [
    {
        key: 'sales',
        title: 'Bán hàng',
        icon: ShoppingBag,
        items: [
            {
                label: 'POS bán hàng',
                icon: Monitor,
                href: '/pos',
                paths: ['/pos'],
                badge: 'Nhanh',
                permission: 'pos.access',
            },
            {
                label: 'Lịch sử kiểm kê',
                icon: History,
                href: '/work-shifts',
                paths: ['/work-shifts'],
                permission: 'pos.access',
                requiresOpeningCount: true,
            },
            {
                label: 'Đơn hàng & Hóa đơn',
                icon: FileText,
                href: '/sales',
                paths: ['/sales', '/sales/*'],
                permission: 'sales.view',
            },
            {
                label: 'Dịch vụ sửa chữa',
                icon: Wrench,
                href: '/repairs',
                paths: ['/repairs', '/repairs/*'],
                permission: 'repairs.view',
            },
        ],
    },
    {
        key: 'warehouse',
        title: 'Kho & Hàng hóa',
        icon: Package,
        items: [
            {
                label: 'Nhập hàng',
                icon: Package,
                href: '/stock-import',
                paths: ['/stock-import', '/stock-import/*'],
                permission: 'stock_imports.view',
            },
            {
                label: 'Sản phẩm',
                icon: Boxes,
                href: '/products',
                paths: ['/products', '/products-trash', '/product-imeis'],
                permission: 'products.view',
            },
            {
                label: 'Tra cứu',
                icon: SearchCheck,
                href: '/lookup',
                paths: ['/lookup'],
                permission: 'products.view',
            },
            {
                label: 'Danh mục & Thương hiệu',
                icon: FolderTree,
                href: can('categories.view') ? '/categories' : '/brands',
                paths: ['/categories', '/brands'],
                permissions: ['categories.view', 'brands.view'],
            },
            {
                label: 'Nhà cung cấp',
                icon: Truck,
                href: '/suppliers',
                paths: ['/suppliers'],
                permission: 'suppliers.view',
            },
        ],
    },
    {
        key: 'crm',
        title: 'Khách hàng',
        icon: Users,
        items: [
            {
                label: 'Danh sách khách hàng',
                icon: UserRound,
                href: '/customers',
                paths: ['/customers', '/customers/*'],
                permission: 'customers.view',
            },
        ],
    },
    {
        key: 'finance',
        title: 'Tài chính',
        icon: HandCoins,
        items: [
            {
                label: 'Thu công nợ khách hàng',
                icon: HandCoins,
                href: '/debts/customers',
                paths: ['/debts/customers'],
                permission: 'customer_debts.view',
            },
            {
                label: 'Thanh toán NCC',
                icon: HandCoins,
                href: '/debts/suppliers',
                paths: ['/debts/suppliers'],
                permission: 'supplier_debts.view',
            },
            {
                label: 'Chi phí cửa hàng',
                icon: Receipt,
                href: '/expenses',
                paths: ['/expenses'],
                permission: 'expenses.view',
            },
            {
                label: 'Khai thuế',
                icon: Receipt,
                href: '/tax-declaration',
                paths: ['/tax-declaration'],
                permission: 'reports.view',
            },
        ],
    },
    {
        key: 'reports',
        title: 'Báo cáo',
        icon: BarChart3,
        items: [
            {
                label: 'Báo cáo bán hàng',
                icon: BarChart3,
                href: '/reports',
                paths: ['/reports', '/reports/*'],
                permission: 'reports.view',
            },
        ],
    },
    {
        key: 'system',
        title: 'Hệ thống',
        icon: Settings,
        items: [
            {
                label: 'Quản lý Nhân viên',
                icon: Users,
                href: '/users',
                paths: ['/users'],
                permission: 'users.view',
            },
            {
                label: 'Tài khoản cá nhân',
                icon: UserCog,
                href: '/profile',
                paths: ['/profile'],
            },
            {
                label: 'Thiết lập cửa hàng',
                icon: Settings,
                href: '/settings',
                paths: ['/settings'],
                permission: 'settings.view',
            },
        ],
    },
]

const visibleItems = (items) => items.filter(item =>
    (!item.requiresOpeningCount || page.props.settings?.work_shift_opening_count_enabled !== false && page.props.settings?.work_shift_opening_count_enabled !== 0 && page.props.settings?.work_shift_opening_count_enabled !== '0')
    && (item.permissions ? item.permissions.some(permission => can(permission)) : can(item.permission))
)
const isGroupActive = (group) => visibleItems(group.items).some(item => isActive(item.paths))

/* GROUP OPEN STATE */
const openGroups = ref([])
const isGroupOpen = (group) => openGroups.value.includes(group.key)

const toggleGroup = (group) => {
    const index = openGroups.value.indexOf(group.key)
    if (index > -1) {
        openGroups.value.splice(index, 1)
    } else {
        openGroups.value.push(group.key)
    }
}

const openActiveGroup = () => {
    openGroups.value = menuGroups
        .filter(group => isGroupActive(group))
        .map(group => group.key)
}

openActiveGroup()

/* NAVIGATION */
const toggleSidebar = () => emit('toggle')
const navigate = () => {
    isMobileOpen.value = false
    emit('navigate')
}

const visitMenu = (href, paths = []) => {
    isMobileOpen.value = false

    if (href === '/pos') {
        prepareOpenPos()
    }
    if (isCurrentMenu(paths, href)) {
        navigate()
        return
    }

    router.visit(href, {
        preserveState: false,
        preserveScroll: false,
        replace: false,
        onStart: () => navigate(),
    })
}

/* FLYOUT CON (KHI THU GỌN): bấm icon nhóm để mở danh sách mục con, bấm lại / bấm ra ngoài / Esc để đóng */
const flyoutKey = ref(null)
const flyoutPos = ref({ top: 0, left: 0 })
const flyoutGroup = computed(() => menuGroups.find(group => group.key === flyoutKey.value) || null)

const closeFlyout = () => { flyoutKey.value = null }

const toggleFlyout = (group, event) => {
    if (flyoutKey.value === group.key) {
        closeFlyout()
        return
    }

    const rect = event.currentTarget.getBoundingClientRect()
    const estimatedHeight = 48 + visibleItems(group.items).length * 40 + 16

    flyoutPos.value = {
        top: Math.max(8, Math.min(rect.top, window.innerHeight - estimatedHeight - 8)),
        left: rect.right + 8,
    }
    flyoutKey.value = group.key
}

const visitFlyoutItem = (item) => {
    closeFlyout()
    visitMenu(item.href, item.paths)
}

const onOutsidePointer = (event) => {
    if (flyoutKey.value && !event.target.closest?.('[data-sidebar-flyout]')) closeFlyout()
}
const onEscape = (event) => {
    if (event.key === 'Escape') closeFlyout()
}

onMounted(() => {
    window.addEventListener('pointerdown', onOutsidePointer)
    window.addEventListener('keydown', onEscape)
    window.addEventListener('resize', closeFlyout)
})
onBeforeUnmount(() => {
    window.removeEventListener('pointerdown', onOutsidePointer)
    window.removeEventListener('keydown', onEscape)
    window.removeEventListener('resize', closeFlyout)
})

watch(() => props.collapsed, (val) => {
    closeFlyout()
    if (val) openGroups.value = []
})
</script>

<template>
    <aside
        class="flex h-full min-h-0 w-full flex-col bg-cyan-950 text-white border-r border-white/10 select-none overflow-hidden shadow-2xl lg:shadow-none"
    >
        <!-- 1. HEADER (CỐ ĐỊNH ĐỈNH) -->
        <div
            class="flex h-[64px] shrink-0 items-center border-b border-white/10 px-4"
            :class="props.collapsed ? 'justify-center' : 'justify-between'"
        >
            <template v-if="!props.collapsed">
                <button
                    type="button"
                    class="flex min-w-0 items-center gap-3 active:scale-95 transition"
                    @click="visitMenu(homeUrl, [homeUrl])"
                >
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-600 text-white shadow-md shadow-blue-600/30">
                        <ShoppingBag :size="19" />
                    </div>
                    <div class="min-w-0 text-left">
                        <div class="truncate text-[15px] font-black tracking-wide leading-tight">
                            {{ shopName }}
                        </div>
                        <div class="truncate text-[10px] text-cyan-300/70 font-medium">
                            Hệ thống quản lý
                        </div>
                    </div>
                </button>

                <button
                    type="button"
                    class="hidden lg:flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-300 hover:bg-white/10 hover:text-white transition active:scale-90"
                    title="Thu gọn menu"
                    @click="toggleSidebar"
                >
                    <EllipsisVertical :size="18" />
                </button>
            </template>

            <!-- THU GỌN: chỉ một biểu tượng, bấm để mở rộng lại (rê chuột hiện biểu tượng mở rộng) -->
            <button
                v-else
                type="button"
                class="group/toggle flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-600 text-white shadow-md shadow-blue-600/30 transition hover:bg-blue-500 active:scale-95"
                title="Mở rộng menu"
                aria-label="Mở rộng menu"
                @click="toggleSidebar"
            >
                <ShoppingBag :size="19" class="group-hover/toggle:hidden" />
                <PanelLeftOpen :size="19" class="hidden group-hover/toggle:block" />
            </button>
        </div>

        <!-- 2. NAV (TỰ CUỘN ĐỘC LẬP KHI XỔ MENU DÀI) -->
        <nav class="sidebar-scroll flex-1 min-h-0 overflow-y-auto overscroll-contain px-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3 space-y-1.5">
            <!-- TRANG CHỦ -->
            <button
                type="button"
                @click="visitMenu(homeUrl, [homeUrl])"
                class="flex h-10 w-full items-center rounded-xl transition-all duration-200"
                :class="[
                    props.collapsed ? 'justify-center px-0' : 'gap-3 px-3.5',
                    isActive(['/dashboard'])
                        ? 'bg-blue-600 text-white font-bold shadow-md shadow-blue-600/30'
                        : 'text-slate-300 hover:bg-white/10 hover:text-white font-medium'
                ]"
                :title="props.collapsed ? 'Trang chủ' : undefined"
            >
                <Home :size="18" class="shrink-0" />
                <span v-if="!props.collapsed" class="truncate text-xs font-bold">
                    Trang chủ
                </span>
            </button>

            <!-- CÁC NHÓM MENU -->
            <div
                v-for="group in menuGroups"
                :key="group.key"
                class="relative group/menu-group"
            >
                <template v-if="visibleItems(group.items).length">
                    <!-- NÚT NHÓM CHA -->
                    <button
                        type="button"
                        data-sidebar-flyout
                        class="flex w-full items-center rounded-xl transition-all duration-200"
                        :class="[
                            props.collapsed ? 'h-10 justify-center px-0' : 'h-10 justify-between px-3.5',
                            isGroupActive(group) || (props.collapsed && flyoutKey === group.key)
                                ? 'bg-cyan-900/90 text-white font-bold'
                                : 'text-slate-300 hover:bg-white/10 hover:text-white font-medium'
                        ]"
                        :title="props.collapsed ? group.title : undefined"
                        @click="props.collapsed ? toggleFlyout(group, $event) : toggleGroup(group)"
                    >
                        <span class="flex items-center" :class="props.collapsed ? '' : 'gap-3 min-w-0'">
                            <component :is="group.icon" :size="18" class="shrink-0" />
                            <span v-if="!props.collapsed" class="truncate text-xs font-bold">
                                {{ group.title }}
                            </span>
                        </span>

                        <template v-if="!props.collapsed">
                            <ChevronDown v-if="isGroupOpen(group)" :size="15" class="shrink-0 text-cyan-300" />
                            <ChevronRight v-else :size="15" class="shrink-0 text-slate-400" />
                        </template>
                    </button>

                    <!-- DANH SÁCH CON (KHI MỞ RỘNG - XỔ XUỐNG DẠNG TREE) -->
                    <div
                        v-if="!props.collapsed && isGroupOpen(group)"
                        class="mt-1.5 space-y-1.5 ml-4 pl-3 border-l-2 border-white/10"
                    >
                        <button
                            v-for="item in visibleItems(group.items)"
                            :key="item.href"
                            type="button"
                            @click="visitMenu(item.href, item.paths)"
                            class="flex h-11 w-full items-center gap-3 rounded-xl px-3 transition-all duration-150 lg:h-9"
                            :class="[
                                isActive(item.paths)
                                    ? 'bg-blue-600 text-white font-bold shadow-sm shadow-blue-600/30'
                                    : 'text-slate-300 hover:bg-white/10 hover:text-white font-medium'
                            ]"
                        >
                            <component :is="item.icon" :size="16" class="shrink-0 opacity-80" />
                            <span class="min-w-0 flex-1 truncate text-xs text-left">
                                {{ item.label }}
                            </span>
                        </button>
                    </div>


                </template>
            </div>
        </nav>

        <!-- FLYOUT MỤC CON (thu gọn): đưa ra <body> để không bị cắt bởi vùng cuộn / overflow của thanh bên -->
        <Teleport to="body">
            <div
                v-if="props.collapsed && flyoutGroup"
                data-sidebar-flyout
                class="fixed z-[1100] w-60 space-y-1 rounded-2xl border border-white/10 bg-cyan-950 p-2 text-white shadow-2xl"
                :style="{ top: `${flyoutPos.top}px`, left: `${flyoutPos.left}px` }"
            >
                <div class="mb-1 flex items-center gap-2 border-b border-white/10 px-3 py-2 text-[11px] font-extrabold uppercase tracking-wider text-cyan-300">
                    <component :is="flyoutGroup.icon" :size="14" class="shrink-0" />
                    {{ flyoutGroup.title }}
                </div>
                <button
                    v-for="item in visibleItems(flyoutGroup.items)"
                    :key="item.href"
                    type="button"
                    class="flex h-10 w-full items-center gap-3 rounded-xl px-3 transition-all duration-150"
                    :class="isActive(item.paths)
                        ? 'bg-blue-600 text-white font-bold shadow-sm shadow-blue-600/30'
                        : 'text-slate-300 hover:bg-white/10 hover:text-white font-medium'"
                    @click="visitFlyoutItem(item)"
                >
                    <component :is="item.icon" :size="17" class="shrink-0" />
                    <span class="min-w-0 flex-1 truncate text-left text-xs">{{ item.label }}</span>
                </button>
            </div>
        </Teleport>
    </aside>
</template>

<style scoped>
.sidebar-scroll {
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.2) transparent;
}

.sidebar-scroll::-webkit-scrollbar {
    width: 4px;
}

.sidebar-scroll::-webkit-scrollbar-track {
    background: transparent;
}

.sidebar-scroll::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 10px;
}

.sidebar-scroll:hover::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.4);
}
</style>