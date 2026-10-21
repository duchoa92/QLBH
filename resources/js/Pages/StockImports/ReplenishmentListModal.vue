<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { toast } from 'vue-sonner'
import {
    AlertTriangle,
    Check,
    CheckSquare,
    Copy,
    LoaderCircle,
    Search,
    Save,
    ShoppingBasket,
    X,
} from 'lucide-vue-next'

import api from '@/Services/api'
import { formatCurrency } from '@/utils/format'
import BaseModal from '@/Components/UI/BaseModal.vue'

const props = defineProps({
    suppliers: { type: Array, default: () => [] },
})

const emit = defineEmits(['close'])

const products = ref([])
const selectedItems = ref([])
const loading = ref(true)
const loadError = ref('')
const search = ref('')
const stockFilter = ref('all')
const supplierId = ref('')
const copied = ref(false)
const draftSaved = ref(false)
const draftStorageKey = 'stock-import-replenishment-draft-v1'

const selectedSupplier = computed(() =>
    props.suppliers.find(supplier => String(supplier.id) === String(supplierId.value)) || null
)

const stockStatus = product => {
    const stock = Number(product.stock || 0)
    if (stock <= 0) return 'out'
    return stock <= Number(product.alert_stock || 0) ? 'low' : 'available'
}

const filteredProducts = computed(() => {
    const keyword = search.value.trim().toLocaleLowerCase()
    return products.value.filter(product => {
        const matchesSearch = !keyword || [product.name, product.sku, product.barcode]
            .some(value => String(value || '').toLocaleLowerCase().includes(keyword))
        const matchesFilter = stockFilter.value === 'all' || stockStatus(product) === stockFilter.value
        return matchesSearch && matchesFilter
    })
})

const outOfStockCount = computed(() => products.value.filter(product => Number(product.stock) <= 0).length)
const lowStockCount = computed(() => products.value.filter(product => stockStatus(product) === 'low').length)
const availableCount = computed(() => products.value.filter(product => stockStatus(product) === 'available').length)
const selectedIds = computed(() => new Set(selectedItems.value.map(item => Number(item.id))))
const totalEstimate = computed(() => selectedItems.value.reduce(
    (total, item) => total + Math.max(0, Number(item.quantity) || 0) * Math.max(0, Number(item.cost_price) || 0),
    0,
))

const loadProducts = async () => {
    loading.value = true
    loadError.value = ''
    try {
        const { data } = await api.get('/stock-import/replenishment-products')
        products.value = Array.isArray(data.products) ? data.products : []
    } catch (error) {
        console.error('Không thể tải sản phẩm cần nhập:', error)
        loadError.value = 'Không tải được danh sách tồn kho. Hãy thử tải lại.'
    } finally {
        loading.value = false
    }
}

const suggestedQuantity = product => Math.max(1, Math.ceil(Number(product.alert_stock || 0) - Number(product.stock || 0)))

const selectProduct = (product, checked) => {
    const existingIndex = selectedItems.value.findIndex(item => Number(item.id) === Number(product.id))
    if (!checked) {
        if (existingIndex !== -1) selectedItems.value.splice(existingIndex, 1)
        return
    }
    if (existingIndex !== -1) return
    selectedItems.value.push({
        id: product.id,
        name: product.name,
        sku: product.sku,
        unit_name: product.unit_name,
        quantity: suggestedQuantity(product),
        cost_price: Number(product.cost_price || 0),
    })
}

const selectAllVisible = () => {
    const visibleIds = new Set(filteredProducts.value.map(product => Number(product.id)))
    const allSelected = filteredProducts.value.length > 0
        && filteredProducts.value.every(product => selectedIds.value.has(Number(product.id)))
    if (allSelected) {
        selectedItems.value = selectedItems.value.filter(item => !visibleIds.has(Number(item.id)))
        return
    }
    filteredProducts.value.forEach(product => selectProduct(product, true))
}

const removeItem = id => {
    selectedItems.value = selectedItems.value.filter(item => Number(item.id) !== Number(id))
}

const saveDraft = () => {
    try {
        localStorage.setItem(draftStorageKey, JSON.stringify({
            selectedItems: selectedItems.value,
            supplierId: supplierId.value,
            savedAt: new Date().toISOString(),
        }))
        draftSaved.value = true
        toast.success('Đã lưu danh sách lấy hàng trên thiết bị này')
    } catch (error) {
        console.error('Không thể lưu danh sách lấy hàng:', error)
        toast.error('Không thể lưu danh sách trên thiết bị này')
    }
}

watch([selectedItems, supplierId], () => {
    draftSaved.value = false
}, { deep: true })

const shareText = computed(() => {
    const greeting = selectedSupplier.value?.name
        ? `Kính gửi ${selectedSupplier.value.name},\nBên em cần lấy các mặt hàng sau:\n`
        : 'Bên em cần lấy các mặt hàng sau:\n'
    return `${greeting}${selectedItems.value.map((item, index) => `${index + 1}. ${item.name}${item.sku ? ` (${item.sku})` : ''} — ${item.quantity} ${item.unit_name || 'cái'}`).join('\n')}\nNhờ anh/chị báo giá và thời gian giao hàng giúp em. Xin cảm ơn!`
})

const copyList = async () => {
    if (!selectedItems.value.length) return
    try {
        await navigator.clipboard.writeText(shareText.value)
        copied.value = true
        toast.success('Đã sao chép danh sách gửi nhà cung cấp')
        window.setTimeout(() => { copied.value = false }, 1800)
    } catch (error) {
        console.error('Không thể sao chép danh sách:', error)
        toast.error('Trình duyệt không cho phép sao chép danh sách')
    }
}

onMounted(() => {
    try {
        const draft = JSON.parse(localStorage.getItem(draftStorageKey) || 'null')
        if (Array.isArray(draft?.selectedItems)) selectedItems.value = draft.selectedItems
        if (draft?.supplierId) supplierId.value = String(draft.supplierId)
        draftSaved.value = Boolean(draft)
    } catch (error) {
        console.warn('Bản nháp danh sách lấy hàng không đọc được:', error)
    }

    loadProducts()
})
</script>

<template>
    <BaseModal title="Lập danh sách cần lấy hàng" size="2xl" body-class="p-0" @close="emit('close')">
        <div class="grid min-h-0 grid-cols-1 lg:grid-cols-[minmax(0,1.55fr)_minmax(300px,0.85fr)]">
            <section class="min-w-0 border-b border-slate-200 lg:border-b-0 lg:border-r">
                <div class="space-y-4 border-b border-slate-100 bg-slate-50/70 p-3 sm:p-4">
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <button type="button" class="rounded-lg border px-3 py-2 text-left transition" :class="stockFilter === 'all' ? 'border-slate-800 bg-slate-800 text-white' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'" @click="stockFilter = 'all'">
                            <span class="block text-lg font-bold leading-5">{{ products.length }}</span><span class="mt-1 block text-[11px]">Tất cả</span>
                        </button>
                        <button type="button" class="rounded-lg border px-3 py-2 text-left transition" :class="stockFilter === 'out' ? 'border-red-600 bg-red-600 text-white' : 'border-red-100 bg-white text-red-700 hover:bg-red-50'" @click="stockFilter = 'out'">
                            <span class="block text-lg font-bold leading-5">{{ outOfStockCount }}</span><span class="mt-1 block text-[11px]">Hết hàng</span>
                        </button>
                        <button type="button" class="rounded-lg border px-3 py-2 text-left transition" :class="stockFilter === 'low' ? 'border-amber-500 bg-amber-500 text-white' : 'border-amber-100 bg-white text-amber-700 hover:bg-amber-50'" @click="stockFilter = 'low'">
                            <span class="block text-lg font-bold leading-5">{{ lowStockCount }}</span><span class="mt-1 block text-[11px]">Gần hết hàng</span>
                        </button>
                        <button type="button" class="col-span-2 rounded-lg border px-3 py-2 text-left transition sm:col-span-1" :class="stockFilter === 'available' ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-emerald-100 bg-white text-emerald-700 hover:bg-emerald-50'" @click="stockFilter = 'available'">
                            <span class="block text-lg font-bold leading-5">{{ availableCount }}</span><span class="mt-1 block text-[11px]">Còn hàng</span>
                        </button>
                    </div>

                    <label class="relative block">
                        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input v-model="search" type="search" placeholder="Tìm tên hàng hoặc mã SKU..." class="h-10 w-full rounded-lg border border-slate-200 bg-white pl-9 pr-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" />
                    </label>
                </div>

                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-2.5 sm:px-5">
                    <label class="inline-flex cursor-pointer items-center gap-2 text-xs font-semibold text-slate-600">
                        <input type="checkbox" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" :checked="filteredProducts.length > 0 && filteredProducts.every(product => selectedIds.has(Number(product.id)))" @change="selectAllVisible" />
                        Chọn tất cả đang hiển thị
                    </label>
                    <span class="text-xs text-slate-400">{{ filteredProducts.length }} mặt hàng</span>
                </div>

                <div class="max-h-[42dvh] overflow-y-auto lg:max-h-[calc(100dvh-22rem)]">
                    <div v-if="loading" class="flex min-h-48 items-center justify-center gap-2 text-sm text-slate-500">
                        <LoaderCircle class="h-4 w-4 animate-spin" /> Đang tải tồn kho...
                    </div>
                    <div v-else-if="loadError" class="p-8 text-center">
                        <AlertTriangle class="mx-auto h-8 w-8 text-amber-500" />
                        <p class="mt-2 text-sm text-slate-600">{{ loadError }}</p>
                        <button type="button" class="mt-3 rounded-lg border px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="loadProducts">Tải lại</button>
                    </div>
                    <div v-else-if="!filteredProducts.length" class="flex min-h-48 flex-col items-center justify-center px-6 text-center">
                        <Check class="h-8 w-8 rounded-full bg-emerald-50 p-1.5 text-emerald-600" />
                        <p class="mt-3 text-sm font-semibold text-slate-800">{{ products.length ? 'Không tìm thấy mặt hàng phù hợp' : 'Kho chưa có mặt hàng cần bổ sung' }}</p>
                        <p class="mt-1 text-xs text-slate-500">Thử thay đổi từ khóa tìm kiếm hoặc bộ lọc tồn kho.</p>
                    </div>
                    <div v-else class="divide-y divide-slate-100">
                        <label v-for="product in filteredProducts" :key="product.id" class="flex cursor-pointer items-start gap-3 px-4 py-3 transition hover:bg-blue-50/50 sm:px-5">
                            <input type="checkbox" class="mt-1 rounded border-slate-300 text-blue-600 focus:ring-blue-500" :checked="selectedIds.has(Number(product.id))" @change="selectProduct(product, $event.target.checked)" />
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-2">
                                    <span class="truncate text-sm font-semibold text-slate-900">{{ product.name }}</span>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="stockStatus(product) === 'out' ? 'bg-red-50 text-red-700' : stockStatus(product) === 'low' ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700'">{{ stockStatus(product) === 'out' ? 'Hết hàng' : stockStatus(product) === 'low' ? 'Gần hết' : 'Còn hàng' }}</span>
                                </span>
                                <span class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-slate-500">
                                    <span>SKU: {{ product.sku || '—' }}</span>
                                    <span>Tồn: <b :class="stockStatus(product) === 'out' ? 'text-red-600' : stockStatus(product) === 'low' ? 'text-amber-700' : 'text-emerald-700'">{{ product.stock }} {{ product.unit_name }}</b></span>
                                    <span>Mức báo: {{ product.alert_stock }}</span>
                                </span>
                            </span>
                            <span class="hidden shrink-0 text-right text-xs text-slate-500 sm:block">Giá nhập gần nhất<br /><b class="mt-0.5 inline-block text-slate-800">{{ formatCurrency(product.cost_price) }}</b></span>
                        </label>
                    </div>
                </div>
            </section>

            <aside class="flex min-h-0 flex-col bg-white">
                <div class="space-y-3 border-b border-slate-100 p-4 sm:p-5">
                    <label class="block text-xs font-semibold text-slate-600">Nhà cung cấp <span class="font-normal text-slate-400">(không bắt buộc)</span>
                        <select v-model="supplierId" class="mt-1.5 h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-normal text-slate-800 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                            <option value="">Chưa chọn nhà cung cấp</option>
                            <option v-for="supplier in suppliers" :key="supplier.id" :value="String(supplier.id)">{{ supplier.name }}</option>
                        </select>
                    </label>
                    <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2.5">
                        <span class="text-xs text-slate-600">Tổng tiền dự kiến</span>
                        <span class="text-sm font-bold text-slate-900">{{ formatCurrency(totalEstimate) }}</span>
                    </div>
                </div>

                <div class="max-h-[34dvh] min-h-32 flex-1 overflow-y-auto p-4 sm:p-5 lg:max-h-[calc(100dvh-26rem)]">
                    <div v-if="!selectedItems.length" class="flex h-full min-h-28 flex-col items-center justify-center text-center text-slate-400">
                        <ShoppingBasket class="h-8 w-8" />
                        <p class="mt-2 text-xs">Chọn sản phẩm bên trái để lên danh sách.</p>
                    </div>
                    <div v-else class="space-y-3">
                        <article v-for="item in selectedItems" :key="item.id" class="rounded-xl border border-slate-200 p-3">
                            <div class="flex items-start gap-2">
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-semibold leading-5 text-slate-900">{{ item.name }}</div>
                                    <div class="mt-0.5 text-[11px] text-slate-500">{{ item.sku || 'Không có SKU' }} · {{ item.unit_name || 'Cái' }}</div>
                                </div>
                                <button type="button" class="rounded-md p-1 text-slate-400 hover:bg-red-50 hover:text-red-600" :aria-label="`Bỏ ${item.name} khỏi danh sách`" @click="removeItem(item.id)"><X class="h-4 w-4" /></button>
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-2">
                                <label class="text-[11px] font-medium text-slate-500">Số lượng cần lấy
                                    <input v-model.number="item.quantity" type="number" min="1" step="1" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-2.5 text-sm font-semibold text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" />
                                </label>
                                <label class="text-[11px] font-medium text-slate-500">Giá nhập ước tính
                                    <input v-model.number="item.cost_price" type="number" min="0" step="1000" class="mt-1 h-9 w-full rounded-lg border border-slate-200 px-2.5 text-sm font-semibold text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" />
                                </label>
                            </div>
                            <div class="mt-2 text-right text-xs font-semibold text-slate-700">Tạm tính: {{ formatCurrency(Math.max(0, Number(item.quantity) || 0) * Math.max(0, Number(item.cost_price) || 0)) }}</div>
                        </article>
                    </div>
                </div>

                <div class="border-t border-slate-100 bg-slate-50/70 p-4 sm:p-5">
                    <div class="mb-3 flex items-center gap-2 text-[11px] leading-4 text-slate-500"><CheckSquare class="h-4 w-4 shrink-0 text-emerald-600" /> Nội dung gửi chỉ gồm tên sản phẩm và số lượng cần lấy.</div>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-[auto_1fr_auto]">
                        <button type="button" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100" @click="saveDraft">
                            <Save class="h-4 w-4" /> {{ draftSaved ? 'Đã lưu' : 'Lưu danh sách' }}
                        </button>
                        <button type="button" class="col-span-2 inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50 sm:col-span-1" :disabled="!selectedItems.length || selectedItems.some(item => Number(item.quantity) < 1)" @click="copyList">
                            <Check v-if="copied" class="h-4 w-4" /><Copy v-else class="h-4 w-4" /> {{ copied ? 'Đã sao chép' : 'Sao chép nội dung' }}
                        </button>
                        <button type="button" class="min-h-11 rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-100" @click="emit('close')">Đóng</button>
                    </div>
                </div>
            </aside>
        </div>
    </BaseModal>
</template>
