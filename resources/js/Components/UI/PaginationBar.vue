<script setup>
import { router } from '@inertiajs/vue3'

const props = defineProps({
    data: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
})

const visit = (url) => {
    if (url) router.visit(url, { preserveState: true, preserveScroll: true })
}

const changePerPage = (value) => {
    const params = new URLSearchParams(window.location.search)
    params.set('per_page', String(value))
    params.delete('page')
    router.get(`${window.location.pathname}?${params.toString()}`, {}, {
        preserveState: true, preserveScroll: true, replace: true,
    })
}
</script>

<template>
    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-white px-4 py-3 text-sm">
        <div class="text-slate-500">Hiển thị {{ data.from || 0 }}–{{ data.to || 0 }} / {{ data.total || 0 }} dòng</div>
        <nav v-if="data.links?.length" class="flex items-center gap-1" aria-label="Phân trang">
            <button v-for="link in data.links" :key="`${link.label}-${link.url}`" type="button" v-html="link.label" :disabled="!link.url" @click="visit(link.url)" class="min-w-9 rounded-md border px-3 py-1.5 text-sm transition disabled:cursor-not-allowed disabled:opacity-40" :class="link.active ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-emerald-300 hover:bg-emerald-50'" />
        </nav>
        <div class="flex items-center gap-2 text-slate-500">
            <label :for="`page-size-${data.current_page || 1}`">Số dòng</label>
            <select :id="`page-size-${data.current_page || 1}`" :value="filters?.per_page || 10" @change="changePerPage(Number($event.target.value))" class="rounded-md border border-slate-200 bg-white px-2 py-1.5 text-sm text-slate-700 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                <option :value="10">10</option><option :value="20">20</option><option :value="50">50</option><option :value="100">100</option>
            </select>
        </div>
    </div>
</template>
