<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { LoaderCircle, Search, X } from 'lucide-vue-next'
import BarcodeCameraButton from '@/Components/BarcodeCameraButton.vue'
import GlobalSearchResults from '@/Components/GlobalSearchResults.vue'
import { useGlobalSearch } from '@/Composables/useGlobalSearch'
import { globalSearchDialogOpen } from '@/Stores/globalSearch'

// Cửa sổ tìm toàn hệ thống: dành cho trang không có ô tìm kiếm riêng (Dashboard...) hoặc mở nhanh bằng Ctrl/Cmd + K.
const query = ref('')
const input = ref(null)
const close = () => { globalSearchDialogOpen.value = false }
const { results, loading, activeIndex, groups, select, handleKey } = useGlobalSearch(query, { onSelected: close })
const hasKeyword = computed(() => query.value.trim().length >= 2)

watch(globalSearchDialogOpen, async (open) => {
    if (!open) { query.value = ''; return }
    await nextTick()
    input.value?.focus()
})

const onKeydown = (event) => {
    if (event.key === 'Escape') { close(); return }
    handleKey(event)
}
</script>

<template>
    <div v-if="globalSearchDialogOpen" class="fixed inset-0 z-[9990] flex items-start justify-center bg-slate-900/50 p-3 pt-[8dvh] backdrop-blur-sm" @mousedown.self="close">
        <div class="flex max-h-[80dvh] w-full max-w-xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
            <div class="relative border-b border-slate-100 p-3">
                <Search class="pointer-events-none absolute left-6 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                    ref="input"
                    v-model="query"
                    type="text"
                    autocomplete="off"
                    placeholder="Tìm nhanh toàn hệ thống..."
                    class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50/80 pl-9 pr-24 text-sm text-slate-800 outline-none transition focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-100"
                    @keydown="onKeydown"
                />
                <LoaderCircle v-if="loading" class="absolute right-[4.25rem] top-1/2 h-4 w-4 -translate-y-1/2 animate-spin text-blue-500" />
                <button v-else-if="query" type="button" class="absolute right-[4.25rem] top-1/2 -translate-y-1/2 rounded-md p-1 text-slate-400 hover:bg-slate-100" aria-label="Xóa tìm kiếm" @click="query = ''; input?.focus()"><X class="h-3.5 w-3.5" /></button>
                <BarcodeCameraButton class="absolute right-4 top-1/2 z-10 -translate-y-1/2" @scanned="query = $event" />
            </div>
            <div class="overflow-y-auto p-1.5">
                <GlobalSearchResults v-if="hasKeyword" :groups="groups" :total="results.length" :loading="loading" :keyword="query" :active-index="activeIndex" @select="select" @hover="activeIndex = $event" />
                <p v-else class="px-4 py-6 text-center text-xs font-medium text-slate-400">Nhập ít nhất 2 ký tự để tìm sản phẩm, IMEI, khách hàng, hóa đơn, phiếu sửa...</p>
            </div>
        </div>
    </div>
</template>
