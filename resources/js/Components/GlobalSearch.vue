<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { LoaderCircle, Search, X } from 'lucide-vue-next'
import api from '@/Services/api'

const emit = defineEmits(['selected'])

const query = ref('')
const results = ref([])
const open = ref(false)
const loading = ref(false)
const activeIndex = ref(-1)
const root = ref(null)
let timer = null
let controller = null
let requestId = 0

watch(query, value => {
    clearTimeout(timer)
    controller?.abort()
    const id = ++requestId
    activeIndex.value = -1

    const keyword = value.trim()
    if (keyword.length < 2) {
        results.value = []
        loading.value = false
        return
    }

    open.value = true
    loading.value = true
    results.value = []
    timer = setTimeout(async () => {
        controller = new AbortController()
        try {
            const { data } = await api.get('/api/global-search', {
                params: { q: keyword },
                signal: controller.signal,
            })
            if (id === requestId) results.value = data.results || []
        } catch (error) {
            if (error.name !== 'CanceledError' && id === requestId) results.value = []
        } finally {
            if (id === requestId) loading.value = false
        }
    }, 180)
})

const closeIfOutside = event => {
    if (!root.value?.contains(event.target)) open.value = false
}

const onKeydown = event => {
    if (event.key === 'Escape') {
        open.value = false
        return
    }
    if (!open.value || !results.value.length) return
    if (event.key === 'ArrowDown') {
        event.preventDefault()
        activeIndex.value = (activeIndex.value + 1) % results.value.length
    } else if (event.key === 'ArrowUp') {
        event.preventDefault()
        activeIndex.value = activeIndex.value <= 0 ? results.value.length - 1 : activeIndex.value - 1
    } else if (event.key === 'Enter' && activeIndex.value >= 0) {
        event.preventDefault()
        handleSelect(results.value[activeIndex.value].url)
    }
}

const handleSelect = (url) => {
    open.value = false
    emit('selected')
    router.visit(url)
}

const clear = () => {
    query.value = ''
    results.value = []
    open.value = false
}

onMounted(() => document.addEventListener('mousedown', closeIfOutside))
onBeforeUnmount(() => {
    clearTimeout(timer)
    controller?.abort()
    document.removeEventListener('mousedown', closeIfOutside)
})
</script>

<template>
    <!-- Relative bao bọc toàn bộ component -->
    <div ref="root" class="relative w-full">
        <!-- Input Search -->
        <div class="relative w-full">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
                v-model="query"
                type="search"
                autocomplete="off"
                placeholder="Tìm nhanh toàn hệ thống..."
                class="h-9 w-full rounded-xl border border-slate-200 bg-slate-50/80 pl-9 pr-8 text-xs font-medium text-slate-800 outline-none transition focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-100"
                @focus="open = true"
                @keydown="onKeydown"
            />
            <LoaderCircle v-if="loading" class="absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin text-blue-500" />
            <button v-else-if="query" type="button" class="absolute right-2 top-1/2 -translate-y-1/2 rounded-md p-1 text-slate-400 hover:bg-slate-100" aria-label="Xóa tìm kiếm" @click="clear">
                <X class="h-3.5 w-3.5" />
            </button>
        </div>

        <!-- Popup kết quả: Đã fix left-0 right-0 w-full để nằm khớp dưới thanh search -->
        <div 
            v-if="open && query.trim().length >= 2" 
            class="absolute left-0 right-0 top-full z-50 mt-1.5 max-h-[70vh] w-full overflow-y-auto rounded-2xl border border-slate-200 bg-white p-1.5 shadow-2xl animate-in fade-in slide-in-from-top-1 duration-150"
        >
            <div v-if="loading && !results.length" class="px-4 py-4 text-center text-xs font-medium text-slate-500">
                Đang tìm trong dữ liệu...
            </div>
            <div v-else-if="!results.length" class="px-4 py-4 text-center text-xs font-medium text-slate-500">
                Không tìm thấy kết quả phù hợp.
            </div>
            <div v-else class="space-y-0.5">
                <div class="px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                    {{ results.length }} kết quả phù hợp
                </div>
                <Link
                    v-for="(result, index) in results"
                    :key="`${result.type}-${result.url}-${index}`"
                    :href="result.url"
                    class="flex items-center gap-2.5 rounded-xl px-2.5 py-2 transition hover:bg-blue-50/80"
                    :class="{ 'bg-blue-50/80': activeIndex === index }"
                    @click="handleSelect(result.url)"
                    @mouseenter="activeIndex = index"
                >
                    <span class="shrink-0 rounded-lg bg-slate-100 px-2 py-1 text-center text-[10px] font-bold text-slate-600">
                        {{ result.type }}
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-xs font-bold text-slate-800 leading-tight">{{ result.label }}</span>
                        <span v-if="result.detail" class="mt-0.5 block truncate text-[11px] text-slate-500">{{ result.detail }}</span>
                    </span>
                </Link>
            </div>
        </div>
    </div>
</template>