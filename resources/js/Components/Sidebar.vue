<script setup>
import { computed, ref, watch } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'

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
    Tags,
    Settings,
    ChevronDown,
    ChevronRight,
    Menu,
    X,
    Truck,
    EllipsisVertical,
    Monitor,
    UserCog,
} from 'lucide-vue-next'

const emit = defineEmits(['toggle', 'navigate'])

const props = defineProps({
    collapsed: Boolean,
})

const page = usePage()
const isMobileOpen = ref(false)
const currentPath = computed(() => page.url.split('?')[0])

/* AUTH & PERMISSIONS */
const permissions = computed(() => page.props.auth?.permissions || [])
const roles = computed(() => page.props.auth?.roles || [])

const isSuperAdmin = computed(() =>
    roles.value.includes('Super Admin') || roles.value.includes('admin')
)

const can = (permission) => {
    if (!permission || isSuperAdmin.value) return true
    return permissions.value.includes(permission)
}

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
            },
            {
                label: 'Đơn hàng & Hóa đơn',
                icon: FileText,
                href: '/sales',
                paths: ['/sales', '/sales/*'],
            },
            {
                label: 'Dịch vụ sửa chữa',
                icon: Wrench,
                href: '/repairs',
                paths: ['/repairs', '/repairs/*'],
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
            },
            {
                label: 'Sản phẩm & IMEI',
                icon: Boxes,
                href: '/products',
                paths: ['/products', '/products-trash', '/product-imeis', '/imeis'],
                permission: 'products.view',
            },
            {
                label: 'Danh mục',
                icon: FolderTree,
                href: '/categories',
                paths: ['/categories'],
                permission: 'categories.view',
            },
            {
                label: 'Thương hiệu',
                icon: Tags,
                href: '/brands',
                paths: ['/brands', '/brands-trash'],
            },
            {
                label: 'Nhà cung cấp',
                icon: Truck,
                href: '/suppliers',
                paths: ['/suppliers'],
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
            },
        ],
    },
    {
        key: 'reports',
        title: 'Báo cáo & Tài chính',
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

const visibleItems = (items) => items.filter(item => can(item.permission))
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

watch(() => props.collapsed, (val) => {
    if (val) openGroups.value = []
})
</script>

<template>
    <aside
        class="flex h-screen w-full flex-col bg-cyan-950 text-white border-r border-white/10 select-none overflow-hidden"
    >
        <!-- 1. HEADER (CỐ ĐỊNH ĐỈNH) -->
        <div
            class="flex h-[64px] shrink-0 items-center border-b border-white/10 px-4"
            :class="props.collapsed ? 'justify-center' : 'justify-between'"
        >
            <Link
                v-if="!props.collapsed"
                href="/dashboard"
                class="flex min-w-0 items-center gap-3"
                @click="visitMenu('/dashboard', ['/dashboard'])"
            >
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white text-slate-950 shadow">
                    <ShoppingBag :size="19" />
                </div>
                <div class="min-w-0">
                    <div class="truncate text-[15px] font-black tracking-wide">
                        QLBH POS
                    </div>
                </div>
            </Link>

            <Link
                v-else
                href="/dashboard"
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white text-slate-950 shadow"
                @click="visitMenu('/dashboard', ['/dashboard'])"
            >
                <ShoppingBag :size="19" />
            </Link>

            <button
                type="button"
                class="hidden lg:flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-300 hover:bg-white/10 hover:text-white transition"
                @click="toggleSidebar"
            >
                <EllipsisVertical v-if="!props.collapsed" :size="18" />
                <Menu v-else :size="18" />
            </button>
        </div>

        <!-- 2. NAV (TỰ CUỘN ĐỘC LẬP KHI XỔ MENU DÀI) -->
        <nav class="sidebar-scroll flex-1 min-h-0 overflow-y-auto px-3 py-3 space-y-1">
            <!-- TRANG CHỦ -->
            <button
                type="button"
                @click="visitMenu('/dashboard', ['/dashboard'])"
                class="flex h-10 w-full items-center rounded-xl transition"
                :class="[
                    props.collapsed ? 'justify-center px-0' : 'gap-3 px-3',
                    isActive(['/dashboard'])
                        ? 'bg-white text-slate-950 font-bold shadow-sm'
                        : 'text-slate-300 hover:bg-white/10 hover:text-white font-medium'
                ]"
                :title="props.collapsed ? 'Trang chủ' : undefined"
            >
                <Home :size="19" class="shrink-0" />
                <span v-if="!props.collapsed" class="truncate text-sm">
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
                        class="flex w-full items-center rounded-xl transition"
                        :class="[
                            props.collapsed ? 'h-10 justify-center px-0' : 'h-10 justify-between px-3',
                            isGroupActive(group)
                                ? 'bg-cyan-800 text-white font-bold'
                                : 'text-slate-300 hover:bg-white/10 hover:text-white font-medium'
                        ]"
                        :title="props.collapsed ? group.title : undefined"
                        @click="toggleGroup(group)"
                    >
                        <span class="flex items-center" :class="props.collapsed ? '' : 'gap-3 min-w-0'">
                            <component :is="group.icon" :size="19" class="shrink-0" />
                            <span v-if="!props.collapsed" class="truncate text-sm font-bold">
                                {{ group.title }}
                            </span>
                        </span>

                        <template v-if="!props.collapsed">
                            <ChevronDown v-if="isGroupOpen(group)" :size="16" class="shrink-0" />
                            <ChevronRight v-else :size="16" class="shrink-0" />
                        </template>
                    </button>

                    <!-- DANH SÁCH CON (KHI MỞ RỘNG) -->
                    <div
                        v-if="!props.collapsed && isGroupOpen(group)"
                        class="mt-1 space-y-1 ml-3 pl-2 border-l border-white/15"
                    >
                        <button
                            v-for="item in visibleItems(group.items)"
                            :key="item.href"
                            type="button"
                            @click="visitMenu(item.href, item.paths)"
                            class="flex h-9 w-full items-center gap-2.5 rounded-lg px-2.5 transition"
                            :class="[
                                isActive(item.paths)
                                    ? 'bg-cyan-200 text-cyan-950 font-bold'
                                    : 'text-slate-300 hover:bg-white/10 hover:text-white font-medium'
                            ]"
                        >
                            <component :is="item.icon" :size="17" class="shrink-0" />
                            <span class="min-w-0 flex-1 truncate text-[13px] text-left">
                                {{ item.label }}
                            </span>
                        </button>
                    </div>

                    <!-- POPUP FLYOUT (KHI THU GỌN DESKTOP HOVER) -->
                    <div
                        v-if="props.collapsed"
                        class="hidden group-hover/menu-group:block absolute left-full top-0 z-[100] ml-2 w-52 rounded-xl border border-white/10 bg-cyan-950 p-2 shadow-2xl space-y-1"
                    >
                        <div class="px-3 py-1 text-[11px] font-extrabold uppercase text-slate-400 border-b border-white/10 mb-1">
                            {{ group.title }}
                        </div>
                        <button
                            v-for="item in visibleItems(group.items)"
                            :key="item.href"
                            type="button"
                            @click="visitMenu(item.href, item.paths)"
                            class="flex h-9 w-full items-center gap-2.5 rounded-lg px-2.5 transition"
                            :class="[
                                isActive(item.paths)
                                    ? 'bg-cyan-200 text-cyan-950 font-bold'
                                    : 'text-slate-300 hover:bg-white/10 hover:text-white font-medium'
                            ]"
                        >
                            <component :is="item.icon" :size="17" class="shrink-0" />
                            <span class="min-w-0 flex-1 truncate text-[13px] text-left">
                                {{ item.label }}
                            </span>
                        </button>
                    </div>
                </template>
            </div>
        </nav>

        <!-- 3. FOOTER (CỐ ĐỊNH ĐÁY) -->
        <div class="shrink-0 border-t border-white/10 p-2.5 bg-cyan-950">
            <div
                class="flex items-center rounded-xl bg-white/5"
                :class="props.collapsed ? 'justify-center p-1.5' : 'gap-2.5 p-2'"
            >
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-700 text-xs font-bold">
                    {{ (page.props.auth?.user?.name || 'T').charAt(0).toUpperCase() }}
                </div>

                <div v-if="!props.collapsed" class="min-w-0">
                    <div class="truncate text-xs font-bold">
                        {{ page.props.auth?.user?.name || 'Tài khoản' }}
                    </div>
                    <div class="truncate text-[10px] text-slate-400">
                        {{ page.props.auth?.user?.email || page.props.auth?.user?.username }}
                    </div>
                </div>
            </div>
        </div>
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