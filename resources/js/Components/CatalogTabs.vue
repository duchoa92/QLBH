<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const page = usePage()
const currentPath = computed(() => page.url.split('?')[0])
const permissions = computed(() => page.props.auth?.permissions || [])

const tabs = computed(() => [
    { label: 'Danh mục', href: '/categories', paths: ['/categories'] },
    { label: 'Thương hiệu', href: '/brands', paths: ['/brands'] },
].filter((tab, index) => permissions.value.includes(index === 0 ? 'categories.view' : 'brands.view')))

const isActive = (tab) => tab.paths.some(path => currentPath.value.startsWith(path))
</script>

<template>
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200">
        <nav class="flex gap-2" aria-label="Danh mục và thương hiệu">
            <Link
                v-for="tab in tabs"
                :key="tab.href"
                :href="tab.href"
                class="-mb-px border-b-2 px-4 py-2.5 text-sm font-semibold transition"
                :class="isActive(tab)
                    ? 'border-blue-600 text-blue-700'
                    : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700'"
                :aria-current="isActive(tab) ? 'page' : undefined"
            >
                {{ tab.label }}
            </Link>
        </nav>
        <div class="flex shrink-0 items-center gap-2 pb-1">
            <slot />
        </div>
    </div>
</template>
