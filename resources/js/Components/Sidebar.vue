<script setup>

import { Link, usePage } from '@inertiajs/vue3'

const page = usePage()

const isActive = (url) => {
    return page.url.startsWith(url)
}
// Quyền truy cập
const permissions = page.props.auth.permissions || []
// Kiểm tra quyền
const can = (permission) => {
    return permissions.includes(permission)
}

</script>

<template>

    <aside
        class="w-64 min-h-screen bg-black text-white"
    >

        <div class="p-5 text-2xl font-bold border-b">
            POS SYSTEM
        </div>

        <nav class="p-3">

            <Link
                href="/dashboard"
                class="block px-4 py-2 rounded mb-2"
                :class="isActive('/dashboard')
                    ? 'bg-white text-black'
                    : ''"
            >
                Dashboard
            </Link>

            <Link
                v-if="can('categories.view')" 
                href="/categories"
                class="block px-4 py-2 rounded mb-2"
                :class="isActive('/categories')
                    ? 'bg-white text-black'
                    : ''"
            >
                Danh mục
            </Link>

        </nav>

    </aside>

</template>