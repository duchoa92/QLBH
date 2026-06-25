<script setup>

import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const emit = defineEmits([
    'navigate',
])

const page = usePage()

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

const isActive = (paths) => {

    return paths.some((path) => page.url.startsWith(path))
}

const menuGroups = [
    {
        title: 'Bán Hàng',
        items: [
            {
                label: 'POS bán hàng',
                href: '/pos',
                paths: ['/pos'],
                badge: 'Nhanh',
            },
            {
                label: 'Hóa đơn',
                href: '/sales',
                paths: ['/sales'],
            },
        ],
    },
    {
        title: 'Vận hành',
        items: [
            {
                label: 'Tổng quan',
                href: '/dashboard',
                paths: ['/dashboard'],
            },
            {
                label: 'Khách hàng',
                href: '/customers',
                paths: ['/customers'],
            },

            {
                label: 'Nhà cung cấp',
                href: '/suppliers',
                paths: ['/suppliers'],
            },

            {
                label: 'Sửa chữa',
                href: '/repairs',
                paths: ['/repairs'],
            },

            
        ],
    },
    {
        title: 'Kho hàng',
        items: [
            {
                label: 'Sản phẩm',
                href: '/products',
                paths: ['/products', '/products-trash', '/product-imeis', '/imeis'],
                permission: 'products.view',
            },
            {
                label: 'Danh mục',
                href: '/categories',
                paths: ['/categories'],
                permission: 'categories.view',
            },
            {
                label: 'Thương hiệu',
                href: '/brands',
                paths: ['/brands', '/brands-trash'],
            },
        ],
    },
    {
        title: 'Hệ thống',
        items: [
            {
                label: 'Nhân viên',
                href: '/users',
                paths: ['/users'],
                permission: 'users.view',
            },
            {
                label: 'Tài khoản',
                href: '/profile',
                paths: ['/profile'],
            },
        ],
    },
]

const visibleItems = (items) => {

    return items.filter((item) => can(item.permission))
}
</script>

<template>

    <aside class="flex h-full flex-col bg-slate-950 text-white">

        <div class="border-b border-white/10 p-5">

            <Link
                href="/dashboard"
                class="block"
                @click="emit('navigate')"
            >
                <div class="text-xl font-black tracking-wide">
                    QLBH POS
                </div>

                <div class="mt-1 text-xs font-medium text-slate-400">
                    Quản lý bán hàng
                </div>
            </Link>

        </div>

        <nav class="min-h-0 flex-1 space-y-6 overflow-y-auto p-3">

            <div
                v-for="group in menuGroups"
                :key="group.title"
            >

                <div
                    v-if="visibleItems(group.items).length"
                    class="mb-2 px-3 text-[11px] font-bold uppercase tracking-wide text-slate-500"
                >
                    {{ group.title }}
                </div>

                <div class="space-y-1">

                    <Link
                        v-for="item in visibleItems(group.items)"
                        :key="item.href"
                        :href="item.href"
                        @click="emit('navigate')"
                        class="flex items-center justify-between rounded-md px-3 py-2.5 text-sm font-semibold transition"
                        :class="isActive(item.paths)
                            ? 'bg-white text-slate-950 shadow-sm'
                            : 'text-slate-300 hover:bg-white/10 hover:text-white'"
                    >
                        <span>{{ item.label }}</span>

                        <span
                            v-if="item.badge"
                            class="rounded bg-emerald-400 px-2 py-0.5 text-[10px] font-black uppercase text-emerald-950"
                        >
                            {{ item.badge }}
                        </span>
                    </Link>

                </div>

            </div>

        </nav>

        <div class="border-t border-white/10 p-4">

            <div class="rounded-md bg-white/10 p-3">

                <div class="text-sm font-bold">
                    {{ page.props.auth?.user?.name || 'Tài khoản' }}
                </div>

                <div class="mt-1 truncate text-xs text-slate-400">
                    {{ page.props.auth?.user?.email || page.props.auth?.user?.username }}
                </div>

            </div>

        </div>

    </aside>

</template>
