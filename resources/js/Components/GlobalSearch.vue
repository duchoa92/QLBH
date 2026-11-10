<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { LoaderCircle, Search, X } from 'lucide-vue-next'
import api from '@/Services/api'
import BarcodeCameraButton from '@/Components/BarcodeCameraButton.vue'
import SearchHighlight from '@/Components/UI/SearchHighlight.vue'
import RecordDetailModal from '@/Components/RecordDetailModal.vue'
import CustomerDetailModal from '@/Pages/Customers/CustomerDetailModal.vue'
import SupplierDetailModal from '@/Pages/Suppliers/SupplierDetailModal.vue'
import UserDetailModal from '@/Pages/Users/UserDetailModal.vue'
import SaleSearchModal from '@/Components/Search/SaleSearchModal.vue'
import RepairSearchModal from '@/Components/Search/RepairSearchModal.vue'
import StockImportDetailModal from '@/Pages/StockImports/StockImportDetailModal.vue'
import ProductDetailModal from '@/Pages/Products/ProductDetailModal.vue'
import ProductImeisModal from '@/Pages/Products/ProductImeisModal.vue'
import { openModal } from '@/Stores/modal'

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
        handleSelect(results.value[activeIndex.value])
    }
}

// Mở đúng modal chi tiết mà trang đích dùng, ngay tại trang hiện tại (không điều hướng)
const modalFor = (record, keyword) => {
    switch (record.type) {
        case 'sale': return [SaleSearchModal, { saleId: record.id }]
        case 'repair': return [RepairSearchModal, { repairId: record.id }]
        case 'stock-import': return [StockImportDetailModal, { importId: record.id }]
        case 'product': return [ProductDetailModal, { productId: record.id, productName: record.name || '' }]
        case 'imei': return [ProductImeisModal, { productId: record.product_id, productName: record.name || '' }]
        case 'customer': return [CustomerDetailModal, { recordId: record.id, keyword }]
        case 'supplier': return [SupplierDetailModal, { recordId: record.id, keyword }]
        case 'user': return [UserDetailModal, { recordId: record.id, keyword }]
        // Danh mục, thương hiệu, đơn vị tính: dùng RecordDetailModal dạng bảng thông tin đơn giản
        default: return [RecordDetailModal, { type: record.type, recordId: record.id, keyword }]
    }
}

const handleSelect = (result) => {
    if (!result?.record) return
    const keyword = query.value.trim()
    open.value = false
    emit('selected')
    const [component, props] = modalFor(result.record, keyword)
    openModal(component, { props })
}

// Gom kết quả theo nhóm (giữ thứ tự xuất hiện); index là vị trí trong danh sách phẳng để điều hướng bằng phím
const groups = computed(() => {
    const map = new Map()
    results.value.forEach((result, index) => {
        if (!map.has(result.type)) map.set(result.type, { type: result.type, items: [] })
        map.get(result.type).items.push({ result, index })
    })
    return [...map.values()]
})

const clear = () => {
    query.value = ''
    results.value = []
    open.value = false
}

const setScannedCode = (code) => {
    query.value = code
    open.value = true
}

onBeforeUnmount(() => {
    clearTimeout(timer)
    controller?.abort()
    document.removeEventListener('mousedown', closeIfOutside)
})

const closeIfOutside = event => {
    if (!root.value?.contains(event.target)) open.value = false
}

onMounted(() => document.addEventListener('mousedown', closeIfOutside))
</script>

<template>
    <!-- Relative bao bọc toàn bộ component -->
    <div ref="root" class="relative w-full">
        <!-- Input Search -->
        <div class="w-full">
          <div class="relative min-w-0 flex-1">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
                v-model="query"
                type="input"
                autocomplete="off"
                placeholder="Tìm nhanh toàn hệ thống..."
                class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50/80 pl-9 pr-20 text-sm font-normal text-slate-800 outline-none transition focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-100"
                @focus="open = true"
                @keydown="onKeydown"
            />
            <LoaderCircle v-if="loading" class="absolute right-12 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin text-blue-500" />
            <button v-else-if="query" type="button" class="absolute right-12 top-1/2 -translate-y-1/2 rounded-md p-1 text-slate-400 hover:bg-slate-100" aria-label="Xóa tìm kiếm" @click="clear">
                <X class="h-3.5 w-3.5" />
            </button>
            <BarcodeCameraButton class="absolute right-1 top-1/2 z-10 -translate-y-1/2 " @scanned="setScannedCode" />
          </div>
        </div>

        <!-- Popup kết quả tìm kiếm -->
        <div
            v-if="open && query.trim().length >= 2"
            class="absolute left-0 right-0 top-full z-50 mt-1.5 max-h-[70vh] w-full max-w-full overflow-y-auto rounded-2xl border border-slate-200 bg-white p-1.5 shadow-2xl animate-in fade-in slide-in-from-top-1 duration-150"
        >
            <div v-if="loading && !results.length" class="px-4 py-4 text-center text-xs font-medium text-slate-500">
                Đang tìm trong dữ liệu...
            </div>
            <div v-else-if="!results.length" class="px-4 py-4 text-center text-xs font-medium text-slate-500">
                Không tìm thấy kết quả phù hợp.
            </div>
            <div v-else>
                <div class="px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                    {{ results.length }} kết quả phù hợp
                </div>
                <section v-for="group in groups" :key="group.type" class="mb-1 last:mb-0">
                    <div class="sticky top-0 z-10 flex items-center justify-between rounded-lg bg-slate-100/95 px-3 py-1 text-[11px] font-extrabold uppercase tracking-wide text-slate-600 backdrop-blur">
                        <span>{{ group.type }}</span>
                        <span class="rounded-full bg-white px-1.5 text-[10px] font-bold text-slate-500">{{ group.items.length }}</span>
                    </div>
                    <div class="mt-0.5 space-y-0.5">
                        <a
                            v-for="{ result, index } in group.items"
                            :key="`${result.type}-${result.record.id}-${index}`"
                            href="#"
                            class="flex items-center gap-2.5 rounded-xl px-2.5 py-2 transition hover:bg-blue-50/80"
                            :class="{ 'bg-blue-50/80': activeIndex === index }"
                            @click.prevent="handleSelect(result)"
                            @mouseenter="activeIndex = index"
                        >
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-bold leading-tight text-slate-800"><SearchHighlight :text="result.label" :keyword="query" /></span>
                                <span v-if="result.detail" class="mt-0.5 block truncate text-xs text-slate-500"><SearchHighlight :text="result.detail" :keyword="query" /></span>
                            </span>
                        </a>
                    </div>
                </section>
            </div>
        </div>

    </div>
</template>
