<script setup>

import {computed, ref, watch } from 'vue'
import {Link, router, usePage, } from '@inertiajs/vue3'

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
    Tags,
    Building2,
    UserCog,
    Settings,
    ChevronDown,
    ChevronRight,
    Menu,
    X,
    RotateCcw,
    Truck,
    ChevronFirst,
    GripVertical,
    EllipsisVertical,
} from 'lucide-vue-next'


const emit = defineEmits(['toggle', 'navigate',])

const props = defineProps({
    collapsed: Boolean
})

const page = usePage()

const openGroups = ref([])

const currentPath = computed(() =>
    page.url.split('?')[0]
)
/*
|--------------------------------------------------------------------------
| AUTH
|--------------------------------------------------------------------------
*/

const permissions = computed(() => {

    return page.props.auth?.permissions || []
})


const roles = computed(() => {

    return page.props.auth?.roles || []
})


const isSuperAdmin = computed(() => {

    return roles.value.includes('Super Admin') ||
        roles.value.includes('admin')
})


const can = (permission) => {

    if (!permission || isSuperAdmin.value) {
        return true
    }

    return permissions.value.includes(permission)
}


/*
|--------------------------------------------------------------------------
| ACTIVE
|--------------------------------------------------------------------------
*/

const isActive = (paths = []) => {

    return paths.some(path =>
        page.url.startsWith(path)
    )
}

const isCurrentMenu = (paths = [], href = '') => {
    if (currentPath.value === href) {
        return true
    }

    return paths.some(path =>
        currentPath.value.startsWith(path)
    )
}


/*
|--------------------------------------------------------------------------
| MENU
|--------------------------------------------------------------------------
*/

const menuGroups = [
    {
        key: 'sales',
        title: 'Bán hàng',
        icon: ShoppingBag,

        items: [
            {
                label: 'POS bán hàng',
                icon: ShoppingBag,
                href: '/pos',
                paths: ['/pos'],
                badge: 'Nhanh',
            },

            {
                label: 'Hóa đơn',
                icon: FileText,
                href: '/sales',
                paths: ['/sales'],
            },

            {
                label: 'Báo cáo',
                icon: BarChart3,
                href: '/reports',
                paths: ['/reports'],
            },
        ],
    },

    {
        key: 'operation',
        title: 'Vận hành',
        icon: Wrench,

        items: [
            {
                label: 'Khách hàng',
                icon: UserRound,
                href: '/customers',
                paths: ['/customers'],
            },

            {
                label: 'Nhà cung cấp',
                icon: Truck,
                href: '/suppliers',
                paths: ['/suppliers'],
            },

            {
                label: 'Sửa chữa',
                icon: Wrench,
                href: '/repairs',
                paths: ['/repairs'],
            },
        ],
    },

    {
        key: 'warehouse',
        title: 'Kho hàng',
        icon: Package,

        items: [
            {
                label: 'Nhập hàng',
                icon: Package,
                href: '/stock-import',
                paths: ['/stock-import'],
            },

            {
                label: 'Sản phẩm',
                icon: Boxes,
                href: '/products',
                paths: [
                    '/products',
                    '/products-trash',
                    '/product-imeis',
                    '/imeis',
                ],
                permission: 'products.view',
            },

            {
                label: 'Danh mục',
                icon: Tags,
                href: '/categories',
                paths: ['/categories'],
                permission: 'categories.view',
            },

            {
                label: 'Thương hiệu',
                icon: Tags,
                href: '/brands',
                paths: [
                    '/brands',
                    '/brands-trash',
                ],
            },
        ],
    },

    {
        key: 'system',
        title: 'Hệ thống',
        icon: Settings,

        items: [
            {
                label: 'Nhân viên',
                icon: Users,
                href: '/users',
                paths: ['/users'],
                permission: 'users.view',
            },

            {
                label: 'Tài khoản',
                icon: UserCog,
                href: '/profile',
                paths: ['/profile'],
            },

            {
                label: 'Thiết lập',
                icon: Settings,
                href: '/settings',
                paths: ['/settings'],
            },
        ],
    },
]


/*
|--------------------------------------------------------------------------
| VISIBLE ITEMS
|--------------------------------------------------------------------------
*/

const visibleItems = (items) => {

    return items.filter(item =>
        can(item.permission)
    )
}


/*
|--------------------------------------------------------------------------
| GROUP ACTIVE
|--------------------------------------------------------------------------
*/

const isGroupActive = (group) => {

    return visibleItems(group.items).some(item =>
        isActive(item.paths)
    )
}


/*
|--------------------------------------------------------------------------
| GROUP OPEN STATE
|--------------------------------------------------------------------------
|
| Nếu chưa có state thì tự mở group đang active.
|
*/

const openGroup = ref([])

const isGroupOpen = (group) => {
    return openGroups.value.includes(group.key)
}

const toggleGroup = (group) => {
    const index = openGroups.value.indexOf(group.key)

    if (index > -1) {
        // đang mở → đóng
        openGroups.value.splice(index, 1)
    } else {
        // chưa mở → thêm vào
        openGroups.value.push(group.key)
    }
}

const openActiveGroup = () => {
    openGroups.value = menuGroups
        .filter(group => isGroupActive(group))
        .map(group => group.key)
}

openActiveGroup()


/*
|--------------------------------------------------------------------------
| TOGGLE SIDEBAR
|--------------------------------------------------------------------------
*/
const toggleSidebar = () => {
    emit('toggle')
}

/*
|--------------------------------------------------------------------------
| NAVIGATE
|--------------------------------------------------------------------------
*/

const navigate = () => {
    emit('navigate')
}

const visitMenu = (href, paths = []) => {
    if (isCurrentMenu(paths, href)) {
        navigate()
        return
    }

    router.visit(href, {
        preserveState: false,
        preserveScroll: false,
        replace: false,
        onStart: () => {
            navigate()
        },
    })
}

// click hiện menu con
const handleGroupClick = (group) => {
    toggleGroup(group)
}

// Auto đóng khi collapsed
watch(() => props.collapsed, (val) => {
    if (val) openGroups.value = []
})

</script>
<template>

    <aside
        class="relative flex h-full flex-col bg-cyan-950 text-white transition-all duration-300 ease-in-out"
        :class="props.collapsed ? 'w-[70px]' : 'w-[280px] lg:w-[240px]'"
    >

        <!-- ========================================================= -->
        <!-- HEADER -->
        <!-- ========================================================= -->

        <div
            class="flex h-[82px] shrink-0 items-center border-b border-white/10 px-4"
            :class="props.collapsed ? 'justify-center' : 'justify-between'"
        >

            <!-- LOGO -->
            <Link
                v-if="!props.collapsed"
                href="/dashboard"
                class="flex min-w-0 items-center gap-3"
                @click="visitMenu('/dashboard', ['/dashboard'])"
            >

                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-slate-950 shadow"
                >
                    <ShoppingBag :size="21" />
                </div>

                <div class="min-w-0">

                    <div
                        class="
                            truncate
                            text-[17px]
                            font-black
                            tracking-wide
                        "
                    >
                        QLBH POS
                    </div>

                    <div
                        class="
                            mt-0.5
                            truncate
                            text-[11px]
                            font-medium
                            text-slate-400
                        "
                    >
                        Quản lý bán hàng
                    </div>

                </div>

            </Link>


            <!-- props.collapsed LOGO -->
            <Link
                v-else
                href="/dashboard"
                class="
                    flex
                    h-9
                    w-9
                    items-center
                    justify-center
                    rounded-xl
                    text-slate-950
                "
                @click="navigate"
            >
                
            </Link>


            <!-- MENU BUTTON -->
            <button
                type="button"
                class="
                    flex
                    h-9
                    w-9
                    shrink-0
                    items-center
                    justify-center
                    rounded-lg
                    text-slate-300
                    transition
                    hover:bg-white/10
                    hover:text-white
                "
                @click="toggleSidebar"
            >

                 <EllipsisVertical
                    v-if="!props.collapsed"
                    :size="20"
                />

                <Menu
                    v-else
                    :size="20"
                />

            </button>

        </div>


        <!-- ========================================================= -->
        <!-- NAV -->
        <!-- ========================================================= -->

        <nav
            class="sidebar-scroll min-h-0 flex-1 overflow-y-auto overflow-x-hidden px-3 py-4"
        >

            <!-- TRANG CHỦ -->

            <button
                type="button"
                @click="navigate"
                class="
                    w-full
                    mb-3
                    flex
                    h-11
                    items-center
                    rounded-xl
                    transition
                "
                :class="[
                    props.collapsed
                        ? 'justify-center px-0'
                        : 'gap-3 px-3',

                    isActive(['/dashboard'])
                        ? 'bg-white text-slate-950 shadow-sm'
                        : 'text-slate-300 hover:bg-white/10 hover:text-white'
                ]"
                title="Trang chủ"
            >

                <Home
                    :size="20"
                    class="shrink-0"
                />

                <span
                    v-if="!props.collapsed"
                    class="truncate text-sm font-semibold"
                >
                    Trang chủ
                </span>

            </button>


            <!-- ===================================================== -->
            <!-- GROUPS -->
            <!-- ===================================================== -->

            <div
                v-for="group in menuGroups"
                :key="group.key"
                class="mb-2 relative"
            >

                <template
                    v-if="visibleItems(group.items).length"
                >

                    <!-- ================================================= -->
                    <!-- GROUP HEADER -->
                    <!-- ================================================= -->

                    <button
                        type="button"
                        class="group flex w-full items-center rounded-xl transition"
                        :class="[
                            props.collapsed
                                ? 'h-11 justify-center px-0'
                                : 'h-11 justify-between px-3',

                            isGroupActive(group)
                                ? (props.collapsed
                                    ? 'text-white bg-cyan-700'
                                    : 'bg-cyan-700 text-white shadow-inner')
                                : 'text-slate-400 hover:bg-white/5 hover:text-white'
                        ]"
                        :title="props.collapsed ? group.title : undefined"
                        @click="handleGroupClick(group)"
                    >

                        <span
                            class="
                                flex
                                min-w-0
                                items-center
                            "
                            :class="props.collapsed ? '' : 'gap-3'"
                        >

                            <component
                                :is="group.icon"
                                :size="20"
                                class="shrink-0"
                            />

                            <span
                                v-if="!props.collapsed"
                                class="
                                    truncate
                                    text-sm
                                    font-bold
                                "
                            >
                                {{ group.title }}
                            </span>

                        </span>


                        <ChevronDown
                            v-if="!props.collapsed && isGroupOpen(group)"
                            :size="17"
                            class="shrink-0"
                        />

                        <ChevronRight
                            v-if="!props.collapsed && !isGroupOpen(group)"
                            :size="17"
                            class="shrink-0"
                        />

                    </button>


                    <!-- ================================================= -->
                    <!-- Menu con -->
                    <!-- ================================================= -->
                    <transition
                        enter-active-class="transition-all duration-300 ease-out"
                        enter-from-class="opacity-0 -translate-y-2"
                        enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="transition-all duration-200 ease-in"
                        leave-from-class="opacity-100 translate-y-0"
                        leave-to-class="opacity-0 -translate-y-2"
                    >
                        <div
                            v-if="(props.collapsed && isGroupOpen(group)) || (!props.collapsed && isGroupOpen(group))"
                            class="mt-1 space-y-1"
                            :class="props.collapsed
                                ? 'absolute left-full top-0 z-50 ml-2 w-56 rounded-xl border border-white/10 bg-cyan-950 p-2 shadow-2xl'
                                : 'ml-3'"
                        >
                            <button
                                v-for="item in visibleItems(group.items)"
                                :key="item.href"
                                type="button"
                                @click="visitMenu(item.href, item.paths)"
                                class="group/item flex h-10 w-full items-center rounded-lg transition"
                                :class="[
                                    props.collapsed
                                        ? 'justify-start gap-3 px-3'
                                        : 'gap-3 px-3',

                                    isActive(item.paths)
                                        ? props.collapsed
                                            ? 'bg-cyan-200 text-cyan-950 shadow-md'
                                            : 'bg-cyan-200 text-cyan-950 shadow-md'
                                        : 'text-slate-400 hover:bg-white/10 hover:text-white'
                                ]"
                                :title="props.collapsed ? item.label : undefined"
                            >

                                <component
                                    :is="item.icon"
                                    :size="18"
                                    class="shrink-0"
                                />

                                <span
                                    v-if="!props.collapsed || isGroupOpen(group)"
                                    class="min-w-0 flex-1 truncate text-[13px] font-medium"
                                >
                                    {{ item.label }}
                                </span>

                                <span
                                    v-if="(!props.collapsed || isGroupOpen(group)) && item.badge"
                                    class="rounded bg-emerald-400 px-1.5 py-0.5 text-[9px] font-black uppercase text-emerald-950"
                                >
                                    {{ item.badge }}
                                </span>

                            </button>
                        </div>
                    </transition>
                </template>

            </div>

        </nav>


        <!-- ========================================================= -->
        <!-- USER -->
        <!-- ========================================================= -->

        <div
            class="
                shrink-0
                border-t
                border-white/10
                p-3
            "
        >

            <div
                class="
                    flex
                    items-center
                    rounded-xl
                    bg-white/5
                "
                :class="props.collapsed
                    ? 'justify-center p-2'
                    : 'gap-3 p-3'"
            >

                <!-- AVATAR -->

                <div
                    class="
                        flex
                        h-9
                        w-9
                        shrink-0
                        items-center
                        justify-center
                        rounded-full
                        bg-slate-700
                        text-sm
                        font-bold
                    "
                >
                    {{
                        (
                            page.props.auth?.user?.name ||
                            'T'
                        ).charAt(0).toUpperCase()
                    }}
                </div>


                <!-- USER INFO -->

                <div
                    v-if="!props.collapsed"
                    class="min-w-0"
                >

                    <div
                        class="
                            truncate
                            text-sm
                            font-bold
                        "
                    >
                        {{
                            page.props.auth?.user?.name ||
                            'Tài khoản'
                        }}
                    </div>

                    <div
                        class="
                            mt-0.5
                            truncate
                            text-[11px]
                            text-slate-400
                        "
                    >
                        {{
                            page.props.auth?.user?.email ||
                            page.props.auth?.user?.username
                        }}
                    </div>

                </div>

            </div>

        </div>

    </aside>

</template>
<style>
    /* SCROLL NHỎ + ẨN */
    .sidebar-scroll {
        scrollbar-width: thin;
        scrollbar-color: transparent transparent;
    }

    /* Chrome */
    .sidebar-scroll::-webkit-scrollbar {
        width: 4px;
    }

    .sidebar-scroll::-webkit-scrollbar-track {
        background: transparent;
    }

    .sidebar-scroll::-webkit-scrollbar-thumb {
        background: transparent;
        border-radius: 10px;
        transition: background 0.3s;
    }

    /* Khi hover hoặc scroll thì hiện */
    .sidebar-scroll:hover::-webkit-scrollbar-thumb {
        background: rgba(255,255,255,0.3);
    }
</style>
