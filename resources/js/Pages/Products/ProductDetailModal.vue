<script setup>
import { computed, onMounted, ref } from 'vue'
import {
    Barcode, Check, ChevronRight, CircleCheck, Clock, Copy, Eye, EyeOff,
    Info, Layers, LayoutGrid, Package, Pencil, Printer, Search, Smartphone,
    TriangleAlert, Undo2, Wrench, X,
} from 'lucide-vue-next'
import BaseModal from '@/Components/UI/BaseModal.vue'
import { formatCurrency, formatDate } from '@/utils/format'
import { openModal } from '@/Stores/modal'
import { canPrintLabels } from '@/utils/printLabels'
import PrintLabelsModal from './PrintLabelsModal.vue'

const props = defineProps({
    productId: { type: [Number, String], required: true },
    productName: { type: String, default: '' },
    onEdit: { type: Function, default: null },
})

const emit = defineEmits(['close'])

/* ================= STATE ================= */
const product = ref(null)
const loading = ref(true)
const error = ref('')

const activeTab = ref('overview')
const showCostPrice = ref(true)
const copiedImei = ref(null)
const imeiSearch = ref('')
const imeiFilterStatus = ref('all')
const variantFilterId = ref(null) // lọc IMEI theo biến thể (null = tất cả)
const imageFailed = ref(false)

onMounted(async () => {
    try {
        const { data } = await window.axios.get(route('products.show', props.productId), {
            headers: { Accept: 'application/json' },
        })
        product.value = data.product
    } catch {
        error.value = 'Không tải được thông tin sản phẩm.'
    } finally {
        loading.value = false
    }
})

/* ================= PRODUCT TYPE ================= */
const hasImei = computed(() => Boolean(product.value?.manage_stock_by_serial) || product.value?.product_type === 'imei')
const hasVariants = computed(() => (product.value?.variants?.length || 0) > 0)

const productType = computed(() => {
    if (hasImei.value && hasVariants.value) return 'combo'
    if (hasVariants.value) return 'variants'
    if (hasImei.value) return 'imei'
    return 'standard'
})

const typeMeta = computed(() => ({
    standard: { label: 'Sản phẩm thường', icon: Package },
    variants: { label: 'Có biến thể', icon: Layers },
    imei: { label: 'Quản lý IMEI / Serial', icon: Barcode },
    combo: { label: 'Có biến thể & quản lý IMEI', icon: Smartphone },
}[productType.value]))

/* ================= TABS ================= */
const tabs = computed(() => [
    { key: 'overview', label: 'Tổng quan', icon: LayoutGrid },
    hasVariants.value && { key: 'variants', label: 'Biến thể', icon: Layers, count: product.value.variants.length },
    hasImei.value && { key: 'imei', label: 'IMEI / Serial', icon: Barcode, count: (product.value.imeis || []).length },
].filter(Boolean))

/* ================= IMEI HELPERS ================= */
const normalizeStatus = (status) => ({ 0: 'in_stock', 1: 'sold', 2: 'repairing', 3: 'returned' }[status] ?? status)

const statusMeta = (status) => ({
    in_stock: { label: 'Trong kho', icon: CircleCheck, cls: 'bg-emerald-100 text-emerald-800 border-emerald-200' },
    sold: { label: 'Đã bán', icon: Clock, cls: 'bg-slate-200 text-slate-700 border-slate-300' },
    repairing: { label: 'Đang sửa', icon: Wrench, cls: 'bg-amber-100 text-amber-800 border-amber-200' },
    returned: { label: 'Đã trả', icon: Undo2, cls: 'bg-sky-100 text-sky-800 border-sky-200' },
}[normalizeStatus(status)] || { label: 'Khác', icon: Info, cls: 'bg-slate-100 text-slate-600 border-slate-200' })

const isInStock = (imei) => normalizeStatus(imei.status) === 'in_stock'

const imeiCounts = computed(() => {
    const counts = { in_stock: 0, sold: 0, other: 0 }
    for (const item of product.value?.imeis || []) {
        const status = normalizeStatus(item.status)
        if (status === 'in_stock') counts.in_stock++
        else if (status === 'sold') counts.sold++
        else counts.other++
    }
    return counts
})

/* ================= STOCK & PRICE ================= */
const variantStock = (variant) => hasImei.value
    ? (product.value.imeis || []).filter(i => i.variant_id === variant.id && isInStock(i)).length
    : Number(variant.stock || 0)

const totalStock = computed(() => {
    const p = product.value
    if (!p) return 0
    if (hasImei.value) return (p.imeis || []).filter(isInStock).length
    if (hasVariants.value) return p.variants.reduce((sum, v) => sum + Number(v.stock || 0), 0)
    return Number(p.stock || 0)
})

const unitName = computed(() => product.value?.unit?.short_name || product.value?.unit?.name || 'Cái')

const stockState = computed(() => {
    const alert = Number(product.value?.alert_stock || 0)
    if (totalStock.value <= 0) return { key: 'out', label: 'Hết hàng', cls: 'bg-rose-50 text-rose-700 border-rose-200' }
    if (alert > 0 && totalStock.value <= alert) return { key: 'low', label: 'Sắp hết', cls: 'bg-amber-50 text-amber-700 border-amber-200' }
    return { key: 'ok', label: 'Sẵn hàng', cls: 'bg-emerald-50 text-emerald-700 border-emerald-200' }
})

const sellPrice = computed(() => Number(product.value?.sell_price ?? product.value?.price ?? 0))

// Có biến thể -> hiện khoảng giá min – max
const priceText = computed(() => {
    if (!hasVariants.value) return formatCurrency(sellPrice.value)
    const prices = product.value.variants.map(v => Number(v.sell_price || 0)).filter(Boolean)
    if (!prices.length) return formatCurrency(sellPrice.value)
    const min = Math.min(...prices)
    const max = Math.max(...prices)
    return min === max ? formatCurrency(min) : `${formatCurrency(min)} – ${formatCurrency(max)}`
})

const margin = computed(() => {
    const cost = Number(product.value?.cost_price || 0)
    if (hasVariants.value || !cost || !sellPrice.value) return null
    return ((sellPrice.value - cost) / sellPrice.value) * 100
})

/* ================= VARIANTS ================= */
const variantName = (variant) => {
    const attrs = Array.isArray(variant.attributes) ? variant.attributes : []
    const text = attrs.map(a => a?.value).filter(Boolean).join(' · ')
    return text || variant.sku || `Biến thể #${variant.id}`
}

const attributeGroups = computed(() => {
    const groups = {}
    for (const variant of product.value?.variants || []) {
        for (const attr of Array.isArray(variant.attributes) ? variant.attributes : []) {
            if (!attr?.name) continue
            groups[attr.name] ||= new Set()
            if (attr.value) groups[attr.name].add(attr.value)
        }
    }
    return Object.entries(groups).map(([name, values]) => ({ name, values: [...values] }))
})

const variantImeiCount = (variant) => (product.value?.imeis || []).filter(i => i.variant_id === variant.id).length
const filterVariant = computed(() => (product.value?.variants || []).find(v => v.id === variantFilterId.value) || null)

// Từ tab Biến thể -> nhảy sang tab IMEI đã lọc sẵn theo phiên bản
const viewVariantImeis = (variant) => {
    variantFilterId.value = variant.id
    imeiSearch.value = ''
    imeiFilterStatus.value = 'all'
    activeTab.value = 'imei'
}

/* ================= IMEI LIST ================= */
const activeImeiList = computed(() => {
    const all = product.value?.imeis || []
    return variantFilterId.value === null ? all : all.filter(i => i.variant_id === variantFilterId.value)
})

const filteredImeiList = computed(() => {
    const keyword = imeiSearch.value.trim().toLowerCase()
    return activeImeiList.value.filter((item) => {
        const matchSearch = !keyword
            || String(item.imei || '').toLowerCase().includes(keyword)
            || String(item.serial || '').toLowerCase().includes(keyword)
            || String(item.note || '').toLowerCase().includes(keyword)
        const status = normalizeStatus(item.status)
        const matchStatus = imeiFilterStatus.value === 'all'
            || (imeiFilterStatus.value === 'other' ? !['in_stock', 'sold'].includes(status) : status === imeiFilterStatus.value)
        return matchSearch && matchStatus
    })
})

/* ================= ACTIONS ================= */
const handleCopy = async (text) => {
    try {
        await navigator.clipboard.writeText(text)
    } catch {
        const el = document.createElement('textarea')
        el.value = text
        document.body.appendChild(el)
        el.select()
        document.execCommand('copy')
        document.body.removeChild(el)
    }
    copiedImei.value = text
    setTimeout(() => { if (copiedImei.value === text) copiedImei.value = null }, 2000)
}

const canPrint = computed(() => canPrintLabels(product.value))
const printBarcode = () => openModal(PrintLabelsModal, { props: { products: [product.value] } })
const imageUrl = computed(() => (imageFailed.value ? null : product.value?.image_url || null))
</script>

<template>
    <BaseModal
        size="2xl"
        modal-class="!max-w-5xl"
        header-class="!px-5 !py-4"
        body-class="p-0"
        @close="emit('close')"
    >
        <!-- ================= HEADER ================= -->
        <template #header>
            <div class="flex min-w-0 items-center gap-3">
                <div class="shrink-0 rounded-xl border border-indigo-100 bg-indigo-50 p-2.5 text-indigo-600">
                    <component :is="typeMeta.icon" class="h-5 w-5" />
                </div>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700">{{ typeMeta.label }}</span>
                        <span
                            v-if="product"
                            class="rounded border px-2 py-0.5 text-xs font-semibold"
                            :class="product.is_active === false ? 'border-slate-200 bg-slate-100 text-slate-500' : 'border-emerald-200 bg-emerald-50 text-emerald-700'"
                        >{{ product.is_active === false ? 'Ngừng kinh doanh' : 'Đang kinh doanh' }}</span>
                        <span class="font-mono text-xs text-slate-500">ID: {{ productId }}</span>
                    </div>
                    <h2 class="mt-0.5 truncate text-lg font-bold leading-snug text-slate-900">{{ product?.name || productName }}</h2>
                </div>
            </div>
            <button
                type="button"
                class="ml-3 shrink-0 rounded-xl p-2 text-slate-400 transition hover:bg-white hover:text-red-500"
                title="Đóng (Esc)"
                aria-label="Đóng"
                @click="emit('close')"
            >
                <X class="h-5 w-5" />
            </button>
        </template>

        <!-- ================= BODY ================= -->
        <div v-if="loading" class="p-10 text-center text-sm text-slate-500">Đang tải thông tin…</div>
        <div v-else-if="error" class="p-10 text-center text-sm text-rose-600">{{ error }}</div>

        <div v-else-if="product" class="grid min-h-full md:grid-cols-[17rem_1fr]">
            <!-- ========== SIDEBAR TÓM TẮT ========== -->
            <aside class="space-y-4 border-b border-slate-200 bg-slate-50 p-4 md:border-b-0 md:border-r">
                <div class="flex h-48 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <img
                        v-if="imageUrl"
                        :src="imageUrl"
                        :alt="product.name"
                        class="h-full w-full object-contain p-2"
                        loading="lazy"
                        @error="imageFailed = true"
                    />
                    <div v-else class="flex flex-col items-center gap-1.5 text-slate-300">
                        <component :is="typeMeta.icon" class="h-10 w-10" />
                        <span class="text-[11px] font-medium text-slate-400">Chưa có ảnh</span>
                    </div>
                </div>

                <!-- Giá -->
                <div class="rounded-xl border border-slate-200 bg-white p-3.5">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Giá bán</span>
                        <button
                            type="button"
                            class="text-slate-400 hover:text-slate-600"
                            :title="showCostPrice ? 'Ẩn giá vốn' : 'Hiện giá vốn'"
                            @click="showCostPrice = !showCostPrice"
                        >
                            <EyeOff v-if="showCostPrice" class="h-3.5 w-3.5" />
                            <Eye v-else class="h-3.5 w-3.5" />
                        </button>
                    </div>
                    <div class="mt-0.5 text-lg font-bold leading-tight text-slate-900">{{ priceText }}</div>
                    <div class="mt-2 flex items-center justify-between border-t border-dashed border-slate-200 pt-2 text-xs text-slate-500">
                        <span>Giá vốn</span>
                        <span v-if="showCostPrice" class="font-semibold text-amber-700">{{ hasVariants ? 'Xem tab Biến thể' : formatCurrency(product.cost_price) }}</span>
                        <span v-else class="font-mono text-slate-400">••••••••</span>
                    </div>
                    <div v-if="showCostPrice && margin !== null" class="mt-1 flex items-center justify-between text-xs text-slate-500">
                        <span>Biên lợi nhuận</span>
                        <span class="font-semibold" :class="margin >= 0 ? 'text-emerald-700' : 'text-rose-600'">{{ margin.toFixed(1) }}%</span>
                    </div>
                </div>

                <!-- Tồn kho -->
                <div class="rounded-xl border border-slate-200 bg-white p-3.5">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Tồn kho</span>
                        <span class="rounded-full border px-2 py-0.5 text-[11px] font-semibold" :class="stockState.cls">{{ stockState.label }}</span>
                    </div>
                    <div class="mt-0.5 text-2xl font-bold text-indigo-600">
                        {{ totalStock }} <span class="text-sm font-medium text-slate-500">{{ unitName }}</span>
                    </div>
                    <div v-if="hasImei" class="mt-2 grid grid-cols-3 gap-1.5 text-center text-[11px]">
                        <div class="rounded-lg bg-emerald-50 py-1.5"><div class="text-sm font-bold text-emerald-700">{{ imeiCounts.in_stock }}</div><div class="text-emerald-600">Trong kho</div></div>
                        <div class="rounded-lg bg-slate-100 py-1.5"><div class="text-sm font-bold text-slate-700">{{ imeiCounts.sold }}</div><div class="text-slate-500">Đã bán</div></div>
                        <div class="rounded-lg bg-amber-50 py-1.5"><div class="text-sm font-bold text-amber-700">{{ imeiCounts.other }}</div><div class="text-amber-600">Khác</div></div>
                    </div>
                    <div v-if="stockState.key === 'low'" class="mt-2 flex items-start gap-1.5 rounded-lg bg-amber-50 p-2 text-[11px] text-amber-800">
                        <TriangleAlert class="mt-px h-3.5 w-3.5 shrink-0" />
                        Tồn kho đã chạm ngưỡng cảnh báo ({{ product.alert_stock }}). Cân nhắc nhập thêm.
                    </div>
                </div>
            </aside>

            <!-- ========== NỘI DUNG THEO TAB ========== -->
            <section class="min-w-0 bg-white">
                <div class="sticky top-0 z-10 flex gap-1 overflow-x-auto border-b border-slate-200 bg-white px-4 pt-2">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        type="button"
                        class="-mb-px flex items-center gap-1.5 whitespace-nowrap border-b-2 px-3 py-2.5 text-sm font-semibold transition"
                        :class="activeTab === tab.key ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:text-slate-800'"
                        @click="activeTab = tab.key"
                    >
                        <component :is="tab.icon" class="h-4 w-4" />
                        {{ tab.label }}
                        <span v-if="tab.count !== undefined" class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[11px] font-bold text-slate-600">{{ tab.count }}</span>
                    </button>
                </div>

                <!-- TAB: TỔNG QUAN -->
                <div v-if="activeTab === 'overview'" class="space-y-5 p-5">
                    <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                        <div><dt class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Mã sản phẩm / SKU</dt><dd class="mt-0.5 font-mono text-sm font-semibold text-slate-800">{{ product.sku || '—' }}</dd></div>
                        <div><dt class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Model</dt><dd class="mt-0.5 font-mono text-sm font-semibold text-slate-800">{{ product.model || '—' }}</dd></div>
                        <div><dt class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Danh mục</dt><dd class="mt-0.5 text-sm font-semibold text-slate-800">{{ product.category?.name || '—' }}</dd></div>
                        <div><dt class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Thương hiệu</dt><dd class="mt-0.5 text-sm font-semibold text-slate-800">{{ product.brand?.name || '—' }}</dd></div>
                        <div><dt class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Đơn vị tính</dt><dd class="mt-0.5 text-sm font-semibold text-slate-800">{{ unitName }}</dd></div>
                        <div v-if="product.has_unit_conversion">
                            <dt class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Quy đổi</dt>
                            <dd class="mt-0.5 text-sm font-semibold text-slate-800">1 {{ product.conversion_unit?.short_name || product.conversion_unit?.name || 'ĐV' }} = {{ product.conversion_factor }} {{ unitName }}</dd>
                        </div>
                        <div><dt class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Bảo hành</dt><dd class="mt-0.5 text-sm font-semibold text-slate-800">{{ product.warranty_days ? `${product.warranty_days} ngày` : 'Không' }}</dd></div>
                        <div v-if="Number(product.alert_stock) > 0"><dt class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Ngưỡng cảnh báo tồn</dt><dd class="mt-0.5 text-sm font-semibold text-slate-800">≤ {{ product.alert_stock }} {{ unitName }}</dd></div>
                        <div><dt class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Ngày tạo</dt><dd class="mt-0.5 text-sm font-semibold text-slate-800">{{ formatDate(product.created_at) || '—' }}</dd></div>
                    </dl>

                    <div v-if="attributeGroups.length" class="rounded-xl border border-slate-200 bg-slate-50 p-3.5">
                        <div class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Thuộc tính</div>
                        <div class="space-y-2 text-xs">
                            <div v-for="group in attributeGroups" :key="group.name" class="flex flex-wrap items-center gap-1.5">
                                <span class="w-20 shrink-0 font-semibold text-slate-500">{{ group.name }}</span>
                                <span v-for="value in group.values" :key="value" class="rounded border border-slate-200 bg-white px-2 py-0.5 font-medium text-slate-700">{{ value }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="product.description" class="rounded-xl border border-indigo-100 bg-indigo-50/50 p-3.5 text-xs">
                        <span class="mb-1 flex items-center gap-1.5 font-bold text-indigo-900"><Info class="h-3.5 w-3.5 text-indigo-600" /> Mô tả sản phẩm</span>
                        <p class="whitespace-pre-line leading-relaxed text-slate-600">{{ product.description }}</p>
                    </div>
                </div>

                <!-- TAB: BIẾN THỂ -->
                <div v-else-if="activeTab === 'variants'" class="overflow-x-auto">
                    <table class="w-full min-w-[520px] border-collapse text-left text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50 font-semibold uppercase tracking-wider text-slate-500">
                                <th class="px-4 py-3">Biến thể</th>
                                <th class="px-4 py-3">SKU</th>
                                <th v-if="showCostPrice" class="px-4 py-3 text-right">Giá vốn</th>
                                <th class="px-4 py-3 text-right">Giá bán</th>
                                <th class="px-4 py-3 text-center">Tồn</th>
                                <th v-if="hasImei" class="px-4 py-3 text-right">IMEI</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="variant in product.variants" :key="variant.id" class="text-slate-700 transition hover:bg-slate-50">
                                <td class="px-4 py-3 font-semibold text-slate-800">{{ variantName(variant) }}</td>
                                <td class="px-4 py-3 font-mono text-slate-500">{{ variant.sku || '—' }}</td>
                                <td v-if="showCostPrice" class="px-4 py-3 text-right font-medium tabular-nums text-amber-700">{{ formatCurrency(variant.cost_price) }}</td>
                                <td class="px-4 py-3 text-right font-semibold tabular-nums text-slate-900">{{ formatCurrency(variant.sell_price) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span v-if="variantStock(variant) > 0" class="rounded-full bg-emerald-100 px-2 py-0.5 font-bold text-emerald-800">{{ variantStock(variant) }}</span>
                                    <span v-else class="rounded-full bg-rose-100 px-2 py-0.5 font-bold text-rose-700">Hết</span>
                                </td>
                                <td v-if="hasImei" class="px-4 py-3 text-right">
                                    <button
                                        type="button"
                                        class="ml-auto inline-flex items-center gap-1 rounded-lg bg-slate-100 px-2.5 py-1 font-semibold text-slate-700 transition hover:bg-indigo-600 hover:text-white"
                                        @click="viewVariantImeis(variant)"
                                    >
                                        <Barcode class="h-3.5 w-3.5" /> {{ variantImeiCount(variant) }} <ChevronRight class="h-3.5 w-3.5" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- TAB: IMEI -->
                <div v-else-if="activeTab === 'imei'">
                    <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 bg-slate-50 p-3">
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                            <input
                                v-model="imeiSearch"
                                type="text"
                                placeholder="Tìm IMEI / Serial..."
                                class="w-48 rounded-lg border border-slate-300 bg-white py-1.5 pl-8 pr-3 text-xs placeholder-slate-400 focus:border-indigo-500 focus:outline-none"
                            />
                        </div>
                        <select v-model="imeiFilterStatus" class="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-700 focus:border-indigo-500 focus:outline-none">
                            <option value="all">Tất cả trạng thái</option>
                            <option value="in_stock">Chỉ trong kho</option>
                            <option value="sold">Đã bán</option>
                            <option value="other">Sửa chữa / đã trả</option>
                        </select>
                        <button
                            v-if="filterVariant"
                            type="button"
                            class="inline-flex items-center gap-1 rounded-full border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-100"
                            title="Bỏ lọc biến thể"
                            @click="variantFilterId = null"
                        >
                            {{ variantName(filterVariant) }} <X class="h-3 w-3" />
                        </button>
                        <span class="ml-auto text-xs font-medium text-slate-500">
                            <strong>{{ filteredImeiList.length }}</strong> / {{ activeImeiList.length }} IMEI
                        </span>
                    </div>

                    <div class="max-h-[26rem] overflow-auto">
                        <table class="w-full min-w-[600px] border-collapse text-left text-xs">
                            <thead class="sticky top-0 z-10 border-b border-slate-200 bg-slate-100 font-semibold uppercase tracking-wider text-slate-600">
                                <tr>
                                    <th class="w-12 px-4 py-2.5 text-center">STT</th>
                                    <th class="px-4 py-2.5">IMEI / Serial</th>
                                    <th class="px-4 py-2.5">Ngày nhập</th>
                                    <th v-if="showCostPrice" class="px-4 py-2.5 text-right">Giá vốn</th>
                                    <th class="px-4 py-2.5 text-right">Giá bán</th>
                                    <th class="px-4 py-2.5 text-center">Trạng thái</th>
                                    <th class="px-4 py-2.5 text-right"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <template v-if="filteredImeiList.length">
                                    <tr v-for="(item, index) in filteredImeiList" :key="item.id ?? index" class="text-slate-700 transition hover:bg-slate-50">
                                        <td class="px-4 py-2.5 text-center font-mono text-slate-400">{{ index + 1 }}</td>
                                        <td class="px-4 py-2.5">
                                            <div class="font-mono font-bold tracking-wide text-slate-900">{{ item.imei || item.serial || '—' }}<span v-if="item.is_used" class="ml-1.5 inline-flex shrink-0 items-center rounded-md border border-amber-300 bg-amber-100 px-1.5 py-0.5 align-middle text-[10px] font-bold not-italic text-amber-800 font-sans">CŨ</span></div>
                                            <div v-if="item.imei && item.serial" class="font-mono text-[11px] text-slate-400">SN: {{ item.serial }}</div>
                                            <div v-if="item.color || item.storage" class="text-[11px] text-slate-400">{{ [item.color, item.storage].filter(Boolean).join(' · ') }}</div>
                                        </td>
                                        <td class="px-4 py-2.5 text-slate-500">
                                            {{ formatDate(item.imported_at || item.created_at) || '—' }}
                                            <div v-if="item.sold_at" class="text-[11px] text-slate-400">Bán: {{ formatDate(item.sold_at) }}</div>
                                        </td>
                                        <td v-if="showCostPrice" class="px-4 py-2.5 text-right font-medium tabular-nums text-amber-700">{{ formatCurrency(item.cost_price) }}</td>
                                        <td class="px-4 py-2.5 text-right font-semibold tabular-nums text-slate-900">{{ formatCurrency(item.sell_price) }}</td>
                                        <td class="px-4 py-2.5 text-center">
                                            <span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-[11px] font-semibold" :class="statusMeta(item.status).cls">
                                                <component :is="statusMeta(item.status).icon" class="h-3 w-3" />
                                                {{ statusMeta(item.status).label }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2.5 text-right">
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-1 rounded px-2 py-1 text-[11px] text-slate-600 transition hover:bg-slate-200 hover:text-slate-900"
                                                title="Copy IMEI"
                                                @click="handleCopy(item.imei || item.serial)"
                                            >
                                                <template v-if="copiedImei === (item.imei || item.serial)">
                                                    <Check class="h-3 w-3 text-emerald-600" /><span class="font-semibold text-emerald-600">Đã copy</span>
                                                </template>
                                                <template v-else><Copy class="h-3 w-3" /><span>Copy</span></template>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                                <tr v-else>
                                    <td :colspan="showCostPrice ? 7 : 6" class="py-10 text-center text-slate-400">
                                        <Search class="mx-auto mb-1 h-6 w-6 text-slate-300" />
                                        <p class="text-xs font-semibold">{{ activeImeiList.length ? 'Không tìm thấy IMEI phù hợp' : 'Chưa có IMEI nào' }}</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>

        <!-- ================= FOOTER ================= -->
        <template #footer>
            <div class="flex flex-wrap items-center justify-end gap-2.5">
                <button
                    type="button"
                    :disabled="!product || !canPrint"
                    :title="product && !canPrint ? 'Không có mã để in tem' : ''"
                    class="flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                    @click="printBarcode"
                >
                    <Printer class="h-4 w-4 text-slate-500" /> In mã vạch
                </button>
                <button
                    v-if="onEdit"
                    type="button"
                    :disabled="!product"
                    class="flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-md shadow-indigo-600/20 transition hover:bg-indigo-500 disabled:opacity-50"
                    @click="onEdit(product)"
                >
                    <Pencil class="h-4 w-4" /> Chỉnh sửa sản phẩm
                </button>
                <button
                    type="button"
                    class="rounded-xl bg-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-300"
                    @click="emit('close')"
                >
                    Đóng
                </button>
            </div>
        </template>
    </BaseModal>
</template>