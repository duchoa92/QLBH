<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import PageActions from '@/Components/UI/PageActions.vue'

const page = usePage()
const currentPath = computed(() => page.url.split('?')[0])
const permissions = computed(() => page.props.auth?.permissions || [])

const tabs = computed(() => [
    { label: 'Danh mục', href: '/categories', paths: ['/categories'], permission: 'categories.view' },
    { label: 'Thương hiệu', href: '/brands', paths: ['/brands'], permission: 'brands.view' },
    { label: 'Đơn vị tính', href: '/units', paths: ['/units'], permission: 'units.manage' },
].filter(tab => permissions.value.includes(tab.permission)))

const isActive = (tab) => tab.paths.some(path => currentPath.value.startsWith(path))
</script>

<template>
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200">
        <nav class="flex gap-2" aria-label="Danh mục, thương hiệu và đơn vị tính">
            <Link
                v-for="tab in tabs"
                :key="tab.href"
                :href="tab.href"
                class="-mb-px rounded-t-lg border-b-2 px-4 py-2.5 text-sm font-semibold transition"
                :class="isActive(tab)
                    ? 'border-blue-600 bg-blue-50 text-blue-700'
                    : 'border-transparent text-slate-500 hover:border-blue-300 hover:bg-slate-100 hover:text-slate-800'"
                :aria-current="isActive(tab) ? 'page' : undefined"
            >
                {{ tab.label }}
            </Link>
        </nav>
        <PageActions><slot /></PageActions>
    </div>
</template>
