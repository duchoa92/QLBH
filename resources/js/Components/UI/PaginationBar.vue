<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    data: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    // An embedding page can use its own JSON data source without forcing an
    // Inertia navigation for page links or page-size changes.
    ajax: { type: Boolean, default: false },
})
const emit = defineEmits(['navigate', 'per-page'])

const visit = (url) => {
    if (!url) return
    if (props.ajax) { emit('navigate', url); return }
    router.visit(url, { preserveState: true, preserveScroll: true })
}

const changePerPage = (value) => {
    if (props.ajax) { emit('per-page', value); return }
    const params = new URLSearchParams(window.location.search)
    params.set('per_page', String(value))
    params.delete('page')
    router.get(`${window.location.pathname}?${params.toString()}`, {}, {
        preserveState: true, preserveScroll: true, replace: true,
    })
}

// Mobile: chỉ hiện Trước / Sau / trang hiện tại và 2 trang bên cạnh để không bị tràn hàng.
const activeIndex = computed(() => (props.data?.links || []).findIndex(link => link.active))
const isNavLink = (label) => /previous|prev|next|&laquo;|&raquo;/i.test(String(label || ''))
const mobileHideClass = (link, index) => {
    if (isNavLink(link.label) || link.active) return ''
    if (activeIndex.value >= 0 && Math.abs(index - activeIndex.value) <= 1) return ''
    return 'hidden sm:inline-block'
}

const pageLabel = (label) => {
    const plainLabel = String(label || '').replace(/<[^>]*>/g, '').replace(/&laquo;|&raquo;|&#039;|&amp;/g, ' ').trim().toLowerCase()
    if (plainLabel.includes('previous') || plainLabel.includes('prev')) return '‹ Trước'
    if (plainLabel.includes('next')) return 'Sau ›'
    return String(label || '').replace(/<[^>]*>/g, '').trim()
}
</script>

<template>
    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2 border-t border-[#c5d4e3] bg-[#dce7f2] px-3 py-3 text-xs sm:px-4 sm:text-sm">
        <div class="order-1 text-[#40566d]">Hiển thị {{ data.from || 0 }}–{{ data.to || 0 }} / {{ data.total || 0 }} dòng</div>
        <nav v-if="data.links?.length" class="order-3 flex w-full items-center justify-center gap-1 sm:order-2 sm:w-auto" aria-label="Phân trang">
            <button v-for="(link, index) in data.links" :key="`${link.label}-${link.url}-${index}`" type="button" :aria-label="pageLabel(link.label)" :disabled="!link.url" @click="visit(link.url)" class="min-h-10 min-w-10 rounded-md border px-3 py-1.5 text-sm transition disabled:cursor-not-allowed disabled:opacity-40 sm:min-h-0 sm:min-w-9" :class="[mobileHideClass(link, index), link.active ? 'border-emerald-500 bg-emerald-600 text-white' : 'border-slate-300 bg-white text-slate-600 hover:border-emerald-300 hover:bg-emerald-50']">{{ pageLabel(link.label) }}</button>
        </nav>
        <div class="order-2 flex shrink-0 items-center gap-2 text-[#40566d] sm:order-3">
            <label :for="`page-size-${data.current_page || 1}`">Số dòng</label>
            <span class="relative inline-flex shrink-0">
                <select :id="`page-size-${data.current_page || 1}`" :value="filters?.per_page || 10" @change="changePerPage(Number($event.target.value))" class="h-10 w-[4.5rem] appearance-none sm:h-9 rounded-md border border-slate-200 bg-white bg-none py-1 pl-2.5 pr-7 text-sm font-medium tabular-nums text-slate-700 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100" style="background-image: none">
                <option :value="10">10</option><option :value="20">20</option><option :value="50">50</option><option :value="100">100</option>
                </select>
                <svg aria-hidden="true" viewBox="0 0 20 20" fill="currentColor" class="pointer-events-none absolute right-2 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500">
                    <path fill-rule="evenodd" d="M5.22 7.47a.75.75 0 0 1 1.06 0L10 11.19l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.53a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                </svg>
            </span>
        </div>
    </div>
</template>
