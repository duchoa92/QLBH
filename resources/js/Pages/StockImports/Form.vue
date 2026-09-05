<script setup>
/*
|--------------------------------------------------------------------------
| IMPORTS
|--------------------------------------------------------------------------
*/
import { computed, ref, watch, nextTick, onMounted, onBeforeUnmount } from 'vue'
import api from '@/Services/api'
import BaseModal from '@/Components/UI/BaseModal.vue'
import { filterByKeywords, highlightText } from '@/utils/searchHelper'

import {
    Plus,
    Trash2,
    Package,
    Search,
    X,
    FileUp,
    QrCode,
    Pencil
} from 'lucide-vue-next'

import FloatingInput from '@/Components/UI/FloatingInput.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'

/*
|--------------------------------------------------------------------------
| EMITS
|--------------------------------------------------------------------------
*/
const emit = defineEmits(['close', 'select'])

/*
|--------------------------------------------------------------------------
| REFS - UI STATE
|--------------------------------------------------------------------------
*/
const imeiInput = ref(null)

const dropdownVariantRef = ref(null)
const dropdownImeiVariantRef = ref(null)

const showVariantDropdown = ref(false)
const variantSearch = ref('')

const keyword = ref('')
const searchResults = ref([])
const searching = ref(false)
const showResults = ref(false)

let searchTimeout = null

/*
|--------------------------------------------------------------------------
| OUTSIDE CLICK HANDLER
|--------------------------------------------------------------------------
*/
const handleClickOutside = (e) => {
    if (
        (dropdownVariantRef.value && dropdownVariantRef.value.contains(e.target)) ||
        (dropdownImeiVariantRef.value && dropdownImeiVariantRef.value.contains(e.target))
    ) {
        return
    }
    showVariantDropdown.value = false
}

onMounted(() => document.addEventListener('click', handleClickOutside))
onBeforeUnmount(() => document.removeEventListener('click', handleClickOutside))

/*
|--------------------------------------------------------------------------
| ENTRY MODAL STATE
|--------------------------------------------------------------------------
*/
const selectedItems = ref([])

const entryModal = ref({
    open: false,
    mode: null,
    item: null,
    imei_input: '',
    variant_input: null,
    quantity_input: 1,
    cost_price_input: 0,
    sell_price_input: 0,
})


const itemQuantity = (item) => {
    if (item.type === 'simple') {
        return Number(item.quantity || 0)
    }

    if (item.type === 'imei' || item.type === 'imei_variant') {
        return (item.imeis || []).length
    }

    if (item.type === 'variant') {
        return (item.rows || []).reduce(
            (sum, row) => sum + Number(row.quantity || 0),
            0
        )
    }

    return 0
}

const itemCostPrice = (item) => {
    if (item.type === 'simple') return Number(item.cost_price || 0)

    const rows = item.type === 'variant'
        ? item.rows || []
        : item.imeis || []

    if (rows.length) {
        const prices = rows.map(r => Number(r.cost_price || 0))
        return prices.every(p => p === prices[0]) ? prices[0] : null
    }

    return Number(item.cost_price || 0)
}

const itemSellPrice = (item) => {
    if (item.type === 'simple') return Number(item.sell_price || 0)

    const rows = item.type === 'variant'
        ? item.rows || []
        : item.imeis || []

    if (rows.length) {
        const prices = rows.map(r => Number(r.sell_price || 0))
        return prices.every(p => p === prices[0]) ? prices[0] : null
    }

    return Number(item.sell_price || 0)
}

const resetEntryModal = () => {
    entryModal.value = {
        open: false,
        mode: null,
        item: null,
        imei_input: '',
        variant_input: null,
        quantity_input: 1,
        cost_price_input: 0,
        sell_price_input: 0,
    }

    variantSearch.value = ''
    showVariantDropdown.value = false
}


/*
|--------------------------------------------------------------------------
| FORMAT
|--------------------------------------------------------------------------
*/
const formatPrice = (value) =>
    Number(value || 0).toLocaleString('vi-VN')

const itemUnit = (item) => item.unit_name || item.unit || 'Cái'

/*
|--------------------------------------------------------------------------
| VARIANT HELPERS
|--------------------------------------------------------------------------
*/
const variantAttributes = (variant) => {
    if (!variant?.attributes) return []

    if (typeof variant.attributes === 'object' && !Array.isArray(variant.attributes)) {
        return Object.entries(variant.attributes).map(([key, value]) => ({ key, value }))
    }

    if (Array.isArray(variant.attributes)) {
        return variant.attributes.map((a, i) => ({
            key: a.name || a.key || `Attr ${i}`,
            value: a.value || a
        }))
    }

    return []
}

const variantLabel = (variant) => {
    const attrs = variantAttributes(variant)
    return attrs.length ? attrs.map(a => a.value).join(' - ') : (variant?.sku || '')
}

// Lọc biến thể theo từ khóa gần đúng
const filteredVariants = computed(() => {
    const variants = entryModal.value.item?.available_variants || entryModal.value.item?.variants || []
    return filterByKeywords(variants, variantSearch.value, (v) => variantLabel(v))
})

/*
|--------------------------------------------------------------------------
| SELECT VARIANT + Tìm tự động khi gõ
|--------------------------------------------------------------------------
*/
const selectEntryVariant = () => {
    const modal = entryModal.value
    const item = modal.item
    if (!item) return

    const variants = item.available_variants || item.variants || []

    let variant = variants.find(v => Number(v.id) === Number(modal.variant_input))

    if (!variant && variantSearch.value) {
        const searchNormalized = removeVietnameseTones(variantSearch.value)
        const keywords = searchNormalized.split(/\s+/).filter(Boolean)

        // Tìm biến thể thỏa mãn tất cả các từ gõ vào
        variant = variants.find(v => {
            const labelNormalized = removeVietnameseTones(variantLabel(v))
            return keywords.every(kw => labelNormalized.includes(kw))
        })
    }

    if (!variant) return

    modal.variant_input = variant.id

    const cost = Number(variant.cost_price ?? variant.import_price ?? item.cost_price ?? 0)
    const sell = Number(variant.sell_price ?? variant.retail_price ?? variant.price ?? item.sell_price ?? 0)

    entryModal.value.cost_price_input = cost
    entryModal.value.sell_price_input = sell
}

const selectVariant = (variant) => {
    if (!variant) return

    entryModal.value.variant_input = variant.id
    variantSearch.value = variantLabel(variant)
    showVariantDropdown.value = false

    nextTick(() => {
        selectEntryVariant()
    })
}

watch(variantSearch, () => {
    if (entryModal.value.open) selectEntryVariant()
})

watch(() => entryModal.value.variant_input, () => {
    if (entryModal.value.open) selectEntryVariant()
})

/*
|--------------------------------------------------------------------------
| SEARCH PRODUCT
|--------------------------------------------------------------------------
*/
const searchProducts = async () => {
    const value = keyword.value.trim()

    if (!value) {
        searchResults.value = []
        showResults.value = false
        return
    }

    searching.value = true
    showResults.value = true

    try {
        const res = await api.get('/api/products', {
            params: { keyword: value }
        })
        searchResults.value = res.data || []
    } catch (error) {
        // In chi tiết lỗi ra console để debug thay vì để Uncaught Promise
        console.error('Lỗi khi tải danh sách sản phẩm:', error)
        searchResults.value = []
    } finally {
        searching.value = false
    }
}

watch(keyword, () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(searchProducts, 300)
})

watch(() => entryModal.value.variant_input, () => {
    selectEntryVariant()
})

const selectProduct = (product) => {
    const item = JSON.parse(JSON.stringify(product))

    let type = 'simple'
    const hasIMEI = item.manage_stock_by_serial || item.has_imei
    const hasVariant = item.variants?.length || item.has_variants

    if (hasIMEI && hasVariant) type = 'imei_variant'
    else if (hasIMEI) type = 'imei'
    else if (hasVariant) type = 'variant'

    let target = selectedItems.value.find(
        i => Number(i.product_id) === Number(item.id) && i.type === type
    )

    if (!target) {
        // GIÁ NHẬP: Chỉ lấy cost_price hoặc import_price, TUYỆT ĐỐI không lấy item.price
        const costPrice = Number(item.cost_price ?? item.import_price ?? 0)

        // GIÁ BÁN: Ưu tiên sell_price, nếu không có thì lấy price
        const sellPrice = Number(item.sell_price ?? item.price ?? 0)

        target = {
            type,
            product_id: item.id,
            name: item.name,
            unit: item.unit_name ?? item.unit ?? 'Cái',

            quantity: type === 'simple' ? 1 : 0,

            cost_price: costPrice,
            sell_price: sellPrice,

            cost_price_input: costPrice,
            sell_price_input: sellPrice,

            imeis: [],
            rows: [],

            available_variants: item.variants || [],
            variants: item.variants || []
        }

        selectedItems.value.push(target)
    }

    showResults.value = false
    keyword.value = ''

    openEntryModal(target)
}

/*
|--------------------------------------------------------------------------
| OPEN MODAL (FIX PRICE INIT)
|--------------------------------------------------------------------------
*/
const openEntryModal = (item) => {
    let mode = 'normal'
    if (item.type === 'imei' || item.type === 'imei_variant') mode = 'imei'
    else if (item.type === 'variant') mode = 'variant'

    const variants = item.available_variants || item.variants || []

    const defaultVariant =
        (mode === 'variant' || item.type === 'imei_variant') && variants.length
            ? variants[0]
            : null

    // Nạp giá nhập chuẩn: Ưu tiên Biến thể -> Sản phẩm -> 0 (KHÔNG lấy sell_price / price)
    const defaultCost = defaultVariant
        ? Number(defaultVariant.cost_price ?? defaultVariant.import_price ?? item.cost_price ?? 0)
        : Number(item.cost_price ?? item.import_price ?? 0)

    const defaultSell = defaultVariant
        ? Number(defaultVariant.sell_price ?? defaultVariant.retail_price ?? defaultVariant.price ?? item.sell_price ?? 0)
        : Number(item.sell_price ?? item.price ?? 0)

    entryModal.value = {
        open: true,
        mode,
        item,
        imei_input: '',
        variant_input: defaultVariant ? defaultVariant.id : null,
        quantity_input: 1,
        cost_price_input: defaultCost,
        sell_price_input: defaultSell,
    }

    if (defaultVariant) {
        variantSearch.value = ''
        nextTick(() => {
            variantSearch.value = variantLabel(defaultVariant)
            selectEntryVariant()
        })
    } else {
        variantSearch.value = ''
    }
}

/*
|--------------------------------------------------------------------------
| ADD ENTRY (IMEI / VARIANT)
|--------------------------------------------------------------------------
*/
const addEntry = () => {
    const modal = entryModal.value
    const item = modal.item
    if (!item) return

    if (modal.mode === 'variant') {
        const qty = Number(modal.quantity_input || 0)
        if (!qty) return

        const variants = item.available_variants || item.variants || []

        let variant = variants.find(v => Number(v.id) === Number(modal.variant_input))

        if (!variant) return

        if (!item.rows) item.rows = []

        const exist = item.rows.find(r => r.variant_id === variant.id)

        if (exist) {
            exist.quantity += qty
        } else {
            item.rows.push({
                variant_id: variant.id,
                variant,
                quantity: qty,
                cost_price: Number(modal.cost_price_input),
                sell_price: Number(modal.sell_price_input)
            })
        }
        return
    }

    if (modal.mode === 'imei') {
        const imei = String(modal.imei_input || '').trim()
        if (!imei) return

        if (!item.imeis) item.imeis = []

        const duplicate = item.imeis.some(r =>
            (typeof r === 'string' ? r : r.imei).toLowerCase() === imei.toLowerCase()
        )

        if (duplicate) return

        item.imeis.push({
            imei,
            cost_price: Number(modal.cost_price_input),
            sell_price: Number(modal.sell_price_input),
            variant_id: modal.variant_input || null
        })

        modal.imei_input = ''
        nextTick(() => imeiInput.value?.focus?.())
    }
}

/*
|--------------------------------------------------------------------------
| REMOVE
|--------------------------------------------------------------------------
*/
const removeItem = (i) => selectedItems.value.splice(i, 1)
const removeImei = (item, i) => item.imeis.splice(i, 1)
const removeVariantRow = (item, i) => item.rows.splice(i, 1)

/*
|--------------------------------------------------------------------------
| TOTALS
|--------------------------------------------------------------------------
*/
const totalQuantity = computed(() =>
    selectedItems.value.reduce((t, i) => {
        if (i.type === 'simple') return t + Number(i.quantity)
        return t + (i.rows?.reduce((s, r) => s + r.quantity, 0) || 0) + (i.imeis?.length || 0)
    }, 0)
)

const total = computed(() =>
    selectedItems.value.reduce((t, i) => {
        if (i.type === 'simple') {
            return t + i.quantity * i.cost_price
        }

        const rows = i.rows || []
        const imeis = i.imeis || []

        const variantTotal = rows.reduce((s, r) => s + r.quantity * r.cost_price, 0)
        const imeiTotal = imeis.reduce((s, r) => s + (r.cost_price || 0), 0)

        return t + variantTotal + imeiTotal
    }, 0)
)

/*
|--------------------------------------------------------------------------
| CONFIRM
|--------------------------------------------------------------------------
*/
const canConfirm = computed(() => selectedItems.value.length > 0)

const confirm = () => {
    if (!canConfirm.value) return

    emit('select', selectedItems.value)
}

const importFile = () => alert('Chức năng đang phát triển')
</script>

<template>
    <BaseModal title="Chọn hàng nhập" size="xl" @close="emit('close')">
        <div class="flex min-h-0 flex-col gap-4">
            <!-- TÌM KIẾM SẢN PHẨM & NÚT NHẬP FILE -->
            <div class="relative">
                <div class="flex items-stretch gap-3">
                    <div class="relative flex-1">
                        <FloatingInput
                            v-model="keyword"
                            type="text"
                            label="Tìm tên sản phẩm, SKU hoặc barcode"
                        />
                        <div class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center gap-1.5 text-slate-400">
                            <button
                                v-if="keyword"
                                type="button"
                                class="hover:text-slate-600 p-1"
                                @click="keyword = ''"
                            >
                                <X class="h-4 w-4" />
                            </button>
                            <Search class="h-5 w-5" />
                        </div>
                    </div>

                    <button
                        type="button"
                        @click="importFile"
                        class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-slate-800 px-4 text-sm font-semibold text-white shadow-sm hover:bg-slate-900 transition-colors"
                    >
                        <FileUp class="h-4 w-4" />
                        <span>Nhập file</span>
                    </button>
                </div>

                <div
                    v-if="showResults"
                    class="absolute left-0 right-0 top-full z-50 mt-1 max-h-80 overflow-auto rounded-xl border border-slate-200 bg-white shadow-2xl"
                >
                    <div v-if="searching" class="px-4 py-4 text-center text-sm text-slate-500">
                        Đang tìm sản phẩm...
                    </div>

                    <button
                        v-for="product in searchResults"
                        :key="product.id"
                        type="button"
                        class="flex w-full items-center gap-3 border-b border-slate-100 px-4 py-3 text-left hover:bg-slate-50"
                        @click="selectProduct(product)"
                    >
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-100">
                            <img
                                v-if="product.image_url"
                                :src="product.image_url"
                                class="h-full w-full object-cover"
                            />
                            <Package v-else class="h-5 w-5 text-slate-400" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-semibold text-slate-900">
                                {{ product.name }}
                            </div>
                            <div class="mt-0.5 flex gap-3 text-xs text-slate-500">
                                <span>SKU: {{ product.sku || '—' }}</span>
                                <span>Tồn: {{ product.stock ?? 0 }}</span>
                            </div>
                        </div>

                        <div class="text-sm font-bold text-slate-900">
                            {{ formatPrice(product.cost_price ?? product.price ?? 0) }} đ
                        </div>
                    </button>

                    <div v-if="!searching && !searchResults.length" class="px-4 py-6 text-center text-sm text-slate-400">
                        Không tìm thấy sản phẩm
                    </div>
                </div>
            </div>

            <!-- DANH SÁCH SẢN PHẨM SẼ NHẬP -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                    <div class="font-semibold text-slate-900">
                        Danh sách sản phẩm
                    </div>
                    <div class="text-sm font-semibold text-blue-600">
                        {{ totalQuantity }} sản phẩm
                    </div>
                </div>

                <div v-if="!selectedItems.length" class="py-12 text-center text-sm text-slate-400">
                    Chưa có sản phẩm nào
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[750px] text-sm">
                        <thead>
                            <tr class="border-b bg-slate-50/70 text-xs font-semibold text-slate-500">
                                <th class="px-4 py-3 text-left">Sản phẩm</th>
                                <th class="w-24 px-3 py-3 text-center">Đơn vị</th>
                                <th class="w-28 px-3 py-3 text-center">Số lượng</th>
                                <th class="w-36 px-3 py-3 text-right">Giá nhập</th>
                                <th class="w-36 px-3 py-3 text-right">Giá bán</th>
                                <th class="w-24 px-3 py-3 text-center">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="(item, itemIndex) in selectedItems"
                                :key="`${item.type}-${item.product_id}`"
                                class="hover:bg-slate-50/50"
                            >
                                <td class="px-4 py-3">
                                    <div class="flex min-w-0 items-center gap-2.5">
                                        <Package class="h-5 w-5 shrink-0 text-blue-600" />
                                        <div class="min-w-0 flex-1">
                                            <div class="truncate font-semibold text-slate-900">
                                                {{ item.name }}
                                            </div>

                                            <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                                <span
                                                    v-if="item.type === 'variant' || item.type === 'imei_variant'"
                                                    class="inline-flex items-center rounded-md bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10"
                                                >
                                                    {{ (item.rows || []).length }} biến thể
                                                </span>

                                                <span
                                                    v-if="item.type === 'imei' || item.type === 'imei_variant'"
                                                    class="inline-flex items-center rounded-md bg-orange-50 px-2 py-0.5 text-[11px] font-medium text-orange-700 ring-1 ring-inset ring-orange-700/10"
                                                >
                                                    {{ (item.imeis || []).length }} IMEI
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-3 py-3 text-center text-slate-600">
                                    {{ itemUnit(item) }}
                                </td>

                                <td class="px-3 py-3 text-center font-semibold text-slate-900">
                                    {{ itemQuantity(item) }}
                                </td>

                                <td class="px-3 py-3 text-right">
                                    <span v-if="itemCostPrice(item) !== null" class="font-medium text-slate-700">
                                        {{ formatPrice(itemCostPrice(item)) }}
                                    </span>
                                    <span v-else class="text-xs text-slate-400">Nhiều giá</span>
                                </td>

                                <td class="px-3 py-3 text-right">
                                    <span v-if="itemSellPrice(item) !== null" class="font-medium text-slate-700">
                                        {{ formatPrice(itemSellPrice(item)) }}
                                    </span>
                                    <span v-else class="text-xs text-slate-400">Nhiều giá</span>
                                </td>

                                <td class="px-3 py-3 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <button
                                            type="button"
                                            @click="openEntryModal(item)"
                                            title="Chỉnh sửa cấu hình"
                                            class="rounded-lg p-1.5 text-blue-600 hover:bg-blue-50 transition-colors"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </button>

                                        <button
                                            type="button"
                                            @click="removeItem(itemIndex)"
                                            title="Xóa sản phẩm"
                                            class="rounded-lg p-1.5 text-red-500 hover:bg-red-50 transition-colors"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="selectedItems.length" class="flex items-center justify-between border-t bg-slate-50/50 px-4 py-3">
                    <span class="font-semibold text-slate-700">Tổng tiền</span>
                    <span class="text-lg font-bold text-green-600">
                        {{ formatPrice(total) }}
                    </span>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="flex items-center justify-between gap-3">
                <div class="text-sm text-slate-500">
                    <span class="font-semibold text-slate-700">{{ selectedItems.length }}</span> dòng /
                    <span class="font-semibold text-slate-700">{{ totalQuantity }}</span> sản phẩm
                </div>

                <div class="ml-auto flex gap-2">
                    <button
                        type="button"
                        @click="emit('close')"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        Hủy
                    </button>

                    <button
                        type="button"
                        @click="confirm"
                        :disabled="!canConfirm"
                        class="rounded-lg bg-slate-800 px-5 py-2 text-sm font-semibold text-white hover:bg-slate-900 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Lưu kho
                    </button>
                </div>
            </div>
        </template>

        <!-- BASE MODAL CẤU HÌNH CHI TIẾT -->
        <BaseModal
            v-if="entryModal.open"
            :title="
                entryModal.mode === 'variant'
                    ? `Cấu hình biến thể - ${entryModal.item?.name || ''}`
                    : entryModal.mode === 'imei'
                    ? `Cấu hình IMEI - ${entryModal.item?.name || ''}`
                    : `Cấu hình sản phẩm - ${entryModal.item?.name || ''}`
            "
            size="lg"
            @close="resetEntryModal"
        >
            <div class="space-y-4 min-h-[280px]">
                <div class="relative z-10 flex items-center gap-2.5 pb-2">
                    <div v-if="entryModal.mode === 'normal'" class="w-28 shrink-0">
                        <FloatingInput
                            v-model.number="entryModal.item.quantity"
                            type="number"
                            min="1"
                            label="Số lượng"
                        />
                    </div>

                    <div v-if="entryModal.mode === 'variant'" class="flex-1 min-w-[200px]">
                        <div ref="dropdownVariantRef" class="w-full relative">
                            <FloatingInput
                                v-model="variantSearch"
                                @focus="showVariantDropdown = true"
                                type="text"
                                label="Gõ để tìm biến thể..."
                            />
                            <div
                                v-if="showVariantDropdown"
                                class="absolute left-0 right-0 top-full z-50 max-h-48 overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-xl"
                            >
                                <div
                                    v-for="variant in filteredVariants"
                                    :key="variant.id"
                                    @click="selectVariant(variant)"
                                    class="cursor-pointer rounded-lg px-3 py-2 text-xs font-medium text-slate-700 hover:bg-blue-50 hover:text-blue-600 transition-colors"
            
                                >
                                    <span v-html="highlightText(variantLabel(variant), variantSearch)"></span>
                                </div>
                                <div v-if="!filteredVariants.length" class="px-3 py-2 text-xs text-slate-400 text-center">
                                    Không tìm thấy
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="entryModal.mode === 'imei'" class="flex-1 min-w-[160px]">
                        <FloatingInput
                            v-model="entryModal.imei_input"
                            @keydown.enter.prevent="addEntry"
                            type="text"
                            label="Quét hoặc nhập IMEI"
                            ref="imeiInput"
                        />
                    </div>

                    <div v-if="entryModal.mode === 'imei' && entryModal.item?.type === 'imei_variant'" class="relative flex-1 min-w-[180px]">
                        <div ref="dropdownImeiVariantRef" class="w-full relative">
                            <FloatingInput
                                v-model="variantSearch"
                                @focus="showVariantDropdown = true"
                                type="text"
                                label="Gõ để tìm biến thể..."
                            />
                            <div
                                v-if="showVariantDropdown"
                                class="absolute left-0 right-0 top-full z-50 mt-1 max-h-48 overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-xl"
                            >
                                <div
                                    v-for="variant in filteredVariants"
                                    :key="variant.id"
                                    @click="selectVariant(variant)"
                                    class="cursor-pointer rounded-lg px-3 py-2 text-xs font-medium text-slate-700 hover:bg-blue-50 hover:text-blue-600 transition-colors"
                                >
                                    <span v-html="highlightText(variantLabel(variant), variantSearch)"></span>
                                </div>
                                <div v-if="!filteredVariants.length" class="px-3 py-2 text-xs text-slate-400 text-center">
                                    Không tìm thấy
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="entryModal.mode === 'variant'" class="w-24 shrink-0">
                        <FloatingInput
                            v-model.number="entryModal.quantity_input"
                            type="number"
                            min="1"
                            label="Số lượng"
                        />
                    </div>

                    <div class="w-28 shrink-0">
                        <FloatingInput
                            v-if="entryModal.mode !== 'normal'"
                            v-model.number="entryModal.cost_price_input"
                            type="number"
                            label="Giá nhập"
                        />
                        <FloatingInput
                            v-else
                            v-model.number="entryModal.item.cost_price"
                            type="number"
                            label="Giá nhập"
                        />
                    </div>

                    <div class="w-28 shrink-0">
                        <FloatingInput
                            v-if="entryModal.mode !== 'normal'"
                            v-model.number="entryModal.sell_price_input"
                            type="number"
                            label="Giá bán"
                        />
                        <FloatingInput
                            v-else
                            v-model.number="entryModal.item.sell_price"
                            type="number"
                            label="Giá bán"
                        />
                    </div>

                    <button
                        v-if="entryModal.mode !== 'normal'"
                        type="button"
                        @click="addEntry"
                        class="h-10 w-10 shrink-0 rounded-xl bg-emerald-600 text-white flex items-center justify-center hover:bg-emerald-700 font-bold transition-colors shadow-sm"
                    >
                        <Plus class="h-5 w-5" />
                    </button>
                </div>

                <!-- BẢNG IMEI -->
                <div v-if="entryModal.mode === 'imei'">
                    <div v-if="entryModal.item?.imeis?.length" class="mt-4 max-h-60 overflow-auto rounded-xl border border-slate-200">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-slate-50 text-xs font-semibold text-slate-600">
                                    <th class="px-3 py-2 text-left">IMEI / Serial</th>
                                    <th v-if="entryModal.item?.type === 'imei_variant'" class="px-3 py-2 text-left">Biến thể</th>
                                    <th class="px-3 py-2 text-right">Giá nhập</th>
                                    <th class="px-3 py-2 text-right">Giá bán</th>
                                    <th class="w-10"></th>
                                </tr>
                            </thead>
                            
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(row, index) in entryModal.item.imeis" :key="index">
                                    <td class="px-3 py-2 font-mono text-xs font-medium text-slate-800">
                                        {{ typeof row === 'string' ? row : row.imei }}
                                    </td>

                                    <td v-if="entryModal.item?.type === 'imei_variant'" class="px-3 py-2">
                                        <select v-model="row.variant_id" class="h-8 w-full rounded-lg border border-slate-200 px-2 text-xs outline-none focus:border-blue-500">
                                            <option
                                                v-for="variant in entryModal.item.available_variants || entryModal.item.variants"
                                                :key="variant.id"
                                                :value="variant.id"
                                            >
                                                {{ variantLabel(variant) }}
                                            </option>
                                        </select>
                                    </td>

                                    <td class="px-3 py-2 text-right">
                                        <input
                                            v-if="typeof row !== 'string'"
                                            v-model.number="row.cost_price"
                                            type="number"
                                            class="h-8 w-28 rounded-lg border border-slate-200 px-2 text-right text-xs outline-none focus:border-blue-500"
                                        />
                                        <span v-else class="text-xs text-slate-700">
                                            {{ formatPrice(entryModal.item.cost_price) }}
                                        </span>
                                    </td>

                                    <td class="px-3 py-2 text-right">
                                        <input
                                            v-if="typeof row !== 'string'"
                                            v-model.number="row.sell_price"
                                            type="number"
                                            class="h-8 w-28 rounded-lg border border-slate-200 px-2 text-right text-xs outline-none focus:border-blue-500"
                                        />
                                        <span v-else class="text-xs text-slate-700">
                                            {{ formatPrice(entryModal.item.sell_price) }}
                                        </span>
                                    </td>

                                    <td class="px-2 text-center">
                                        <button
                                            type="button"
                                            @click="removeImei(entryModal.item, index)"
                                            class="p-1 text-slate-400 hover:text-red-500 transition-colors"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-else class="mt-4 flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50/50 py-10 text-center">
                        <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-blue-50 text-blue-600">
                            <QrCode class="h-6 w-6" />
                        </div>
                        <p class="text-sm font-semibold text-slate-700">Chưa có mã IMEI / Serial nào</p>
                        <p class="mt-1 max-w-sm text-xs text-slate-400">
                            Hãy quét mã vạch bằng máy quét hoặc nhập chuỗi IMEI/Serial rồi bấm nút <span class="font-bold text-emerald-600 font-mono">+</span> (hoặc nhấn <kbd class="rounded border bg-white px-1 py-0.5 text-[10px] font-semibold shadow-sm">Enter</kbd>) để thêm vào danh sách.
                        </p>
                    </div>
                </div>

                <!-- BẢNG BIẾN THỂ -->
                <div v-if="entryModal.mode === 'variant'">
                    <div v-if="entryModal.item?.rows?.length" class="mt-4 max-h-60 overflow-auto rounded-xl border border-slate-200">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-slate-50 text-xs font-semibold text-slate-600">
                                    <th class="px-3 py-2 text-left">Biến thể</th>
                                    <th class="px-3 py-2 text-center w-24">Số lượng</th>
                                    <th class="px-3 py-2 text-right w-32">Giá nhập</th>
                                    <th class="px-3 py-2 text-right w-32">Giá bán</th>
                                    <th class="w-10"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(row, index) in entryModal.item.rows" :key="row.variant_id">
                                    <td class="px-3 py-2 font-medium text-slate-800">
                                        {{ variantLabel(row.variant) }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <input
                                            v-model.number="row.quantity"
                                            type="number"
                                            min="1"
                                            class="h-8 w-full rounded-lg border border-slate-200 text-center text-xs outline-none focus:border-blue-500"
                                        />
                                    </td>
                                    <td class="px-3 py-2">
                                        <input
                                            v-model.number="row.cost_price"
                                            type="number"
                                            class="h-8 w-full rounded-lg border border-slate-200 px-2 text-right text-xs outline-none focus:border-blue-500"
                                        />
                                    </td>
                                    <td class="px-3 py-2">
                                        <input
                                            v-model.number="row.sell_price"
                                            type="number"
                                            class="h-8 w-full rounded-lg border border-slate-200 px-2 text-right text-xs outline-none focus:border-blue-500"
                                        />
                                    </td>
                                    <td class="px-2 text-center">
                                        <button
                                            type="button"
                                            @click="removeVariantRow(entryModal.item, index)"
                                            class="p-1 text-slate-400 hover:text-red-500 transition-colors"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-else class="mt-4 flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50/50 py-10 text-center">
                        <p class="text-sm font-semibold text-slate-700">Chưa chọn biến thể nào</p>
                        <p class="mt-1 max-w-sm text-xs text-slate-400">
                            Vui lòng chọn biến thể ở danh sách thả xuống phía trên và bấm <span class="font-bold text-emerald-600 font-mono">+</span> để thêm.
                        </p>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        @click="resetEntryModal"
                        class="rounded-lg bg-slate-800 px-5 py-2 text-sm font-semibold text-white hover:bg-slate-900 transition-colors"
                    >
                        Hoàn tất
                    </button>
                </div>
            </template>
        </BaseModal>
    </BaseModal>
</template>