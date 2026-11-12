<script setup>
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Calculator, ChevronDown, Filter, Layers, Save, Smartphone, Tag, X } from 'lucide-vue-next'
import BaseModal from '@/Components/UI/BaseModal.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import { formatCurrency } from '@/utils/format'

const props = defineProps({ products: { type: Array, required: true } })
const emit = defineEmits(['close', 'updated'])

const batchPrice = ref('')
const imeiBulkMode = ref('fixed')
const imeiBulkValue = ref('')
const variantBulkMode = ref('fixed')
const variantBulkValue = ref('')
const variantStockFilter = ref('')
const imeiSearch = ref('')
const imeiStateFilter = ref('')
const filtersOpen = ref(false)
const variantStockOptions = [
    { label: 'Còn hàng', value: 'in_stock' },
    { label: 'Hết hàng', value: 'out_of_stock' },
]
const imeiStateOptions = [
    { label: 'Còn hàng · Chưa bán', value: 'unsold' },
    { label: 'Hết hàng · Đã bán', value: 'sold' },
]
const isImeiInStock = (status) => status === 'in_stock' || status === 0 || status === '0'
const isImeiSold = (status) => status === 'sold' || status === 1 || status === '1'
const imeiStatusLabel = (status) => isImeiInStock(status)
    ? 'Còn hàng · Chưa bán'
    : isImeiSold(status)
        ? 'Hết hàng · Đã bán'
        : status === 2 || status === '2'
            ? 'Đang sửa'
            : status === 3 || status === '3'
                ? 'Đã trả'
                : 'Không khả dụng'
const batchError = ref('')

const numericPrice = (value, fallback = 0) => {
    if (value === null || value === undefined || value === '') return fallback
    const parsed = Number(value)
    return Number.isFinite(parsed) ? parsed : fallback
}

const inheritedPrice = (value, fallback = 0) => {
    const price = numericPrice(value, 0)
    return price > 0 ? price : fallback
}

const attributeText = (attributes) => (attributes ?? [])
    .map((attribute) => {
        const name = String(attribute?.name ?? '').trim()
        const rawValue = Array.isArray(attribute?.value) ? attribute.value.join(', ') : attribute?.value
        const value = String(rawValue ?? '').trim()
        return [name, value].filter(Boolean).join(': ')
    })
    .filter(Boolean)
    .join(' - ')

const attributeSummary = (attributes) => (attributes ?? [])
    .map((attribute) => {
        const rawValue = Array.isArray(attribute?.value) ? attribute.value.join(', ') : attribute?.value
        return String(rawValue ?? '').trim()
    })
    .filter(Boolean)
    .join(' · ')

const buildUpdates = () => props.products.flatMap((product) => {
    const defaultPrice = numericPrice(product.sell_price)
    const defaultCost = numericPrice(product.cost_price)
    const variants = (product.variants ?? []).filter((variant) => variant.is_active !== false)
    const variantMap = new Map(variants.map((variant) => [variant.id, variant]))
    const productName = product.name || `Sản phẩm #${product.id}`
    const rows = [{
        key: `product:${product.id}`, type: 'product', id: product.id, product_id: product.id,
        product_name: productName, label: 'Giá mặc định', sell_price: defaultPrice,
        original_sell_price: defaultPrice, cost_price: defaultCost, editable: true, selected: false,
    }]

    variants.forEach((variant) => {
        const price = inheritedPrice(variant.sell_price, defaultPrice)
        rows.push({
            key: `variant:${variant.id}`, type: 'variant', id: variant.id, product_id: product.id,
            product_name: productName, label: attributeSummary(variant.attributes) || variant.sku || `Biến thể #${variant.id}`, detail: variant.sku,
            attributes: variant.attributes ?? [],
            stock: numericPrice(variant.stock),
            sell_price: price, original_sell_price: price,
            cost_price: inheritedPrice(variant.cost_price, defaultCost), editable: true, selected: false,
        })
    })

    ;(product.imeis ?? []).forEach((imei) => {
        const variant = variantMap.get(imei.variant_id)
        const variantPrice = inheritedPrice(variant?.sell_price, defaultPrice)
        const variantCost = inheritedPrice(variant?.cost_price, defaultCost)
        const price = inheritedPrice(imei.sell_price, variantPrice)
        const cost = inheritedPrice(imei.cost_price, variantCost)
        rows.push({
            key: `imei:${imei.id}`, type: 'imei', id: imei.id, product_id: product.id,
            product_name: productName, label: imei.imei || imei.serial || `IMEI #${imei.id}`,
            detail: attributeSummary(variant?.attributes) || variant?.sku || '',
            attributes: variant?.attributes ?? [], sell_price: price,
            original_sell_price: price, cost_price: cost, editable: imei.status === 'in_stock',
            selected: false, status: imei.status,
            statusLabel: imeiStatusLabel(imei.status),
        })
    })
    return rows
})

const form = useForm({ updates: buildUpdates() })
const isSingleProduct = computed(() => props.products.length === 1)
const product = computed(() => props.products[0] ?? {})
const singleMode = computed(() => {
    if (!isSingleProduct.value) return 'bulk'
    if (form.updates.some((update) => update.type === 'imei')) return 'imei'
    if (form.updates.some((update) => update.type === 'variant')) return 'variant'
    return 'product'
})
const singleUpdates = computed(() => form.updates.filter((update) => update.type === singleMode.value))
const variantAttributeOptions = computed(() => {
    const options = new Map()
    singleUpdates.value.forEach((update) => {
        ;(update.attributes ?? []).forEach((attribute) => {
            const name = String(attribute?.name ?? '').trim()
            const rawValue = Array.isArray(attribute?.value) ? attribute.value : [attribute?.value]
            const values = rawValue.map((value) => String(value ?? '').trim()).filter(Boolean)
            if (!name || !values.length) return
            if (!options.has(name)) options.set(name, new Set())
            values.forEach((value) => options.get(name).add(value))
        })
    })
    return [...options.entries()].map(([name, values]) => ({
        name,
        values: [...values].sort((a, b) => a.localeCompare(b, 'vi')),
        options: [...values].sort((a, b) => a.localeCompare(b, 'vi')).map((value) => ({ label: value, value })),
    }))
})
const filterGridClass = (count) => {
    if (count <= 1) return 'grid-cols-1'
    if (count === 2) return 'grid-cols-2'
    if (count === 3) return 'grid-cols-2 sm:grid-cols-3'
    if (count === 4) return 'grid-cols-2 sm:grid-cols-4'
    return 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-5'
}
const variantFilters = ref({})
const matchesVariantFilters = (update) => variantAttributeOptions.value.every(({ name }) => {
        const selected = variantFilters.value[name]
        if (!selected || selected === 'all') return true
        const attribute = (update.attributes ?? []).find((item) => String(item?.name ?? '').trim() === name)
        const rawValue = Array.isArray(attribute?.value) ? attribute.value : [attribute?.value]
        return rawValue.some((value) => String(value ?? '').trim() === selected)
    })
const filteredVariants = computed(() => singleUpdates.value.filter((update) => {
    if (!matchesVariantFilters(update)) return false
    if (variantStockFilter.value === 'in_stock') return update.stock > 0
    if (variantStockFilter.value === 'out_of_stock') return update.stock <= 0
    return true
}))
const filteredImeis = computed(() => {
    const query = imeiSearch.value.trim().toLocaleLowerCase()
    return singleUpdates.value.filter((update) => {
        const imeiMatches = !query || String(update.label).toLocaleLowerCase().includes(query)
        const stateMatches = !imeiStateFilter.value || imeiStateFilter.value === 'all'
            || (imeiStateFilter.value === 'unsold' && isImeiInStock(update.status))
            || (imeiStateFilter.value === 'sold' && isImeiSold(update.status))
        return imeiMatches && stateMatches && matchesVariantFilters(update)
    })
})
const activeFilterCount = computed(() => singleMode.value === 'imei'
    ? Object.values(variantFilters.value).filter((value) => value && value !== 'all').length + Number(Boolean(imeiSearch.value.trim())) + Number(Boolean(imeiStateFilter.value && imeiStateFilter.value !== 'all'))
    : Object.values(variantFilters.value).filter((value) => value && value !== 'all').length + Number(Boolean(variantStockFilter.value && variantStockFilter.value !== 'all'))
)
const clearVariantFilters = () => {
    variantFilters.value = {}
    variantStockFilter.value = ''
}
const clearImeiFilters = () => {
    imeiSearch.value = ''
    imeiStateFilter.value = ''
    clearVariantFilters()
}
const changedUpdates = computed(() => form.updates.filter((update) =>
    update.editable && numericPrice(update.sell_price) !== numericPrice(update.original_sell_price)
))
const inStockImeis = computed(() => singleUpdates.value.filter((update) => update.editable).length)
const averageCost = computed(() => {
    const updates = singleUpdates.value.filter((update) => update.editable && update.cost_price > 0)
    return updates.length ? updates.reduce((sum, update) => sum + update.cost_price, 0) / updates.length : 0
})

const profit = (update) => numericPrice(update.sell_price) - numericPrice(update.cost_price)
const profitPercent = (update) => update.cost_price > 0 ? (profit(update) / update.cost_price) * 100 : null
const priceDifference = (update) => numericPrice(update.sell_price) - numericPrice(update.original_sell_price)
const setSingleMarkup = (markup) => {
    const update = singleUpdates.value[0]
    if (update?.cost_price > 0) update.sell_price = Math.round(update.cost_price * (1 + markup / 100))
}
const applyImeiBulk = () => {
    if (imeiBulkValue.value === '') {
        batchError.value = 'Nhập giá trị cập nhật hợp lệ.'
        return
    }
    const value = Number(imeiBulkValue.value)
    if (!Number.isFinite(value) || imeiBulkMode.value === 'fixed' && value < 0 || imeiBulkMode.value === 'percent' && value < -100) {
        batchError.value = 'Nhập giá trị cập nhật hợp lệ.'
        return
    }
    filteredImeis.value.filter((update) => update.editable).forEach((update) => {
        const current = numericPrice(update.sell_price)
        if (imeiBulkMode.value === 'fixed') update.sell_price = value
        else if (imeiBulkMode.value === 'percent') update.sell_price = Math.max(0, Math.round(current * (1 + value / 100)))
        else update.sell_price = Math.max(0, current + value)
    })
    batchError.value = ''
}

const applyVariantBulk = () => {
    if (variantBulkValue.value === '') {
        batchError.value = 'Nhập giá trị cập nhật hợp lệ.'
        return
    }
    const value = Number(variantBulkValue.value)
    if (!Number.isFinite(value) || variantBulkMode.value === 'fixed' && value < 0 || variantBulkMode.value === 'percent' && value < -100) {
        batchError.value = 'Nhập giá trị cập nhật hợp lệ.'
        return
    }
    filteredVariants.value.filter((update) => update.editable).forEach((update) => {
        const current = numericPrice(update.sell_price)
        if (variantBulkMode.value === 'fixed') update.sell_price = value
        else if (variantBulkMode.value === 'percent') update.sell_price = Math.max(0, Math.round(current * (1 + value / 100)))
        else update.sell_price = Math.max(0, current + value)
    })
    batchError.value = ''
}

const applyBatchPrice = () => {
    if (batchPrice.value === '') {
        batchError.value = 'Nhập giá bán hợp lệ.'
        return
    }
    const price = Number(batchPrice.value)
    if (!Number.isFinite(price) || price < 0) {
        batchError.value = 'Nhập giá bán hợp lệ.'
        return
    }
    const selected = form.updates.filter((update) => update.editable && update.selected)
    const targets = selected.length ? selected : form.updates.filter((update) => update.editable)
    targets.forEach((update) => { update.sell_price = price })
    batchError.value = ''
}

const submit = () => {
    if (!changedUpdates.value.length) {
        batchError.value = 'Chưa có giá nào thay đổi.'
        return
    }
    form.transform(() => ({
        updates: changedUpdates.value.map(({ type, id, sell_price }) => ({ type, id, sell_price: numericPrice(sell_price) })),
    })).patch(route('products.bulk-price'), {
        preserveScroll: true,
        onSuccess: () => {
            emit('updated')
            emit('close')
        },
    })
}
</script>

<template>
    <BaseModal size="xl" body-class="p-0" header-class="bg-slate-50 px-5 py-4" @close="emit('close')">
        <template #header>
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg" :class="singleMode === 'imei' ? 'bg-emerald-100 text-emerald-700' : singleMode === 'variant' ? 'bg-blue-100 text-blue-700' : 'bg-violet-100 text-violet-700'">
                    <Smartphone v-if="singleMode === 'imei'" :size="19" />
                    <Layers v-else-if="singleMode === 'variant'" :size="19" />
                    <Tag v-else :size="19" />
                </div>
                <div class="min-w-0">
                    <h2 class="truncate text-base font-bold text-slate-900">Cập nhật giá bán: {{ isSingleProduct ? product.name : 'Nhiều sản phẩm' }}</h2>
                    <p v-if="isSingleProduct" class="mt-0.5 text-xs text-slate-500">
                        <template v-if="singleMode === 'imei'">Mã SP: {{ product.sku || product.id }} · Tồn kho: {{ inStockImeis }} máy · Giá nhập TB: {{ formatCurrency(averageCost) }}</template>
                        <template v-else-if="singleMode === 'variant'">Phân loại: Sản phẩm có biến thể</template>
                        <template v-else>Loại: Sản phẩm đơn thể / Phụ kiện</template>
                    </p>
                    <p class="mt-0.5 text-[11px] text-slate-500">Giá mới áp dụng cho toàn bộ hàng còn trong kho; giá vốn và lịch sử lô không thay đổi.</p>
                </div>
            </div>
            <button type="button" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-400 hover:bg-white hover:text-slate-700" aria-label="Đóng" @click="emit('close')"><X :size="19" /></button>
        </template>

        <!-- Sản phẩm IMEI: bố cục thẻ theo từng máy -->
        <template v-if="isSingleProduct && singleMode === 'imei'">
            <div class="border-b border-slate-200 bg-slate-50 px-5 py-2.5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-800"><Calculator :size="15" class="text-amber-500" />Cập nhật nhanh <span class="font-normal text-slate-500">{{ filteredImeis.length }}/{{ singleUpdates.length }} IMEI</span></div>
                    <div class="flex flex-wrap items-center gap-1.5">
                        <select v-model="imeiBulkMode" class="h-9 rounded-lg border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700 focus:border-emerald-500 focus:ring-emerald-500"><option value="fixed">Đặt giá chung (đ)</option><option value="percent">Tăng/giảm theo %</option><option value="amount">Tăng/giảm số tiền (đ)</option></select>
                        <div class="relative w-32">
                            <input v-if="imeiBulkMode === 'percent'" v-model="imeiBulkValue" type="number" min="-100" class="h-9 w-full rounded-lg border border-slate-300 bg-white px-2.5 pr-7 text-right text-xs font-semibold focus:border-emerald-500 focus:ring-emerald-500" placeholder="VD: 10" />
                            <FloatingInput v-else v-model.number="imeiBulkValue" type="number" format-thousands :floating-label="false" input-class="!h-9 !w-full !rounded-lg !border-slate-300 !bg-white !px-2.5 !py-1.5 !pr-7 !text-right !text-xs !font-semibold focus:!border-emerald-500" :placeholder-text="imeiBulkMode === 'fixed' ? 'Giá bán' : 'Số tiền +/-'" />
                            <span class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-[11px] font-semibold text-slate-500">{{ imeiBulkMode === 'percent' ? '%' : 'đ' }}</span>
                        </div>
                        <button type="button" class="h-9 rounded-lg bg-slate-800 px-2.5 text-xs font-semibold text-white hover:bg-slate-900" @click="applyImeiBulk">Áp dụng</button>
                        <div class="relative">
                            <button type="button" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-100" :aria-expanded="filtersOpen" @click="filtersOpen = !filtersOpen"><Filter :size="14" />Bộ lọc<ChevronDown :size="14" class="transition-transform" :class="filtersOpen && 'rotate-180'" /></button>
                            <button v-if="activeFilterCount" type="button" class="absolute -right-2 -top-2 flex h-5 w-5 items-center justify-center rounded-full border-2 border-slate-50 bg-rose-600 text-white shadow-sm hover:bg-rose-700" aria-label="Xóa bộ lọc IMEI" title="Xóa bộ lọc" @click.stop="clearImeiFilters"><X :size="11" /></button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="space-y-3 p-5">
                <div v-if="filtersOpen" class="grid items-end gap-1.5 rounded-xl border border-slate-200 bg-slate-50/70 p-2" :class="filterGridClass(variantAttributeOptions.length + 2)">
                    <FloatingInput v-model="imeiSearch" label="Tìm IMEI / Serial" input-class="!h-9 !w-full !px-3 !pt-2.5 !pb-1 !text-xs" />
                    <FloatingSelect v-for="filter in variantAttributeOptions" :key="filter.name" v-model="variantFilters[filter.name]" :options="filter.options" :label="filter.name" :placeholder-text="filter.name" compact hide-label input-class="!h-9 !w-full" />
                    <FloatingSelect v-model="imeiStateFilter" :options="imeiStateOptions" label="Tình trạng hàng" placeholder-text="Tình trạng hàng" compact hide-label input-class="!h-9 !w-full" />
                </div>
                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="w-full min-w-[760px] border-collapse text-left">
                        <thead class="bg-slate-50 text-[11px] font-bold uppercase tracking-wide text-slate-500"><tr><th class="px-3 py-3">Mã IMEI / trạng thái</th><th class="px-3 py-3 text-right">Giá nhập</th><th class="px-3 py-3 text-right">Giá bán hiện tại</th><th class="px-3 py-3 text-right">Giá bán mới</th><th class="px-3 py-3 text-right">Lợi nhuận</th></tr></thead>
                        <tbody class="divide-y divide-slate-100 text-xs">
                            <tr v-if="!filteredImeis.length"><td colspan="5" class="px-3 py-8 text-center text-sm text-slate-500">Không tìm thấy IMEI phù hợp.</td></tr>
                            <tr v-for="update in filteredImeis" :key="update.key" class="hover:bg-slate-50" :class="!update.editable && 'opacity-55'">
                                <td class="px-3 py-3"><div class="font-mono font-bold text-slate-800">IMEI: {{ update.label }}</div><span class="mt-1 inline-block rounded px-2 py-0.5 text-[10px] font-semibold" :class="update.editable ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'">{{ update.statusLabel }}</span><div v-if="update.detail" class="mt-1 text-[10px] text-slate-500">{{ update.detail }}</div></td>
                                <td class="px-3 py-3 text-right font-medium text-slate-600">{{ formatCurrency(update.cost_price) }}</td>
                                <td class="px-3 py-3 text-right text-slate-400 line-through">{{ formatCurrency(update.original_sell_price) }}</td>
                                <td class="px-3 py-3 text-right"><div class="inline-flex items-center gap-1"><FloatingInput v-model.number="update.sell_price" type="number" format-thousands :floating-label="false" :disabled="!update.editable" input-class="!h-9 !w-32 !rounded-lg !border-slate-300 !bg-emerald-50/30 !px-2 !py-1 !text-right !text-sm !font-bold !text-emerald-700 focus:!border-emerald-500 disabled:!bg-slate-100" /><span class="text-slate-400">đ</span></div></td>
                                <td class="px-3 py-3 text-right"><span class="inline-flex flex-col rounded-md border px-2.5 py-1 font-semibold" :class="profit(update) >= 0 ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700'"><span>{{ formatCurrency(profit(update)) }}</span><span class="text-[10px]">{{ profitPercent(update) === null ? '—' : `${profitPercent(update).toFixed(1)}%` }}</span></span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>

        <!-- Sản phẩm có biến thể: bố cục thẻ theo từng phiên bản -->
        <template v-else-if="isSingleProduct && singleMode === 'variant'">
            <div class="border-b border-slate-200 bg-slate-50 px-5 py-2.5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-800"><Calculator :size="15" class="text-amber-500" />Cập nhật nhanh <span class="font-normal text-slate-500">{{ filteredVariants.length }}/{{ singleUpdates.length }} biến thể</span></div>
                    <div class="flex flex-wrap items-center gap-1.5">
                        <select v-model="variantBulkMode" class="h-9 rounded-lg border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700 focus:border-emerald-500 focus:ring-emerald-500"><option value="fixed">Đặt giá chung (đ)</option><option value="percent">Tăng/giảm theo %</option><option value="amount">Tăng/giảm số tiền (đ)</option></select>
                        <div class="relative w-32">
                            <input v-if="variantBulkMode === 'percent'" v-model="variantBulkValue" type="number" min="-100" class="h-9 w-full rounded-lg border border-slate-300 bg-white px-2.5 pr-7 text-right text-xs font-semibold focus:border-emerald-500 focus:ring-emerald-500" placeholder="VD: 10" />
                            <FloatingInput v-else v-model.number="variantBulkValue" type="number" format-thousands :floating-label="false" input-class="!h-9 !w-full !rounded-lg !border-slate-300 !bg-white !px-2.5 !py-1.5 !pr-7 !text-right !text-xs !font-semibold focus:!border-emerald-500" :placeholder-text="variantBulkMode === 'fixed' ? 'Giá bán' : 'Số tiền +/-'" />
                            <span class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-[11px] font-semibold text-slate-500">{{ variantBulkMode === 'percent' ? '%' : 'đ' }}</span>
                        </div>
                        <button type="button" class="h-9 rounded-lg bg-slate-800 px-2.5 text-xs font-semibold text-white hover:bg-slate-900" @click="applyVariantBulk">Áp dụng</button>
                        <div class="relative">
                            <button type="button" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-100" :aria-expanded="filtersOpen" @click="filtersOpen = !filtersOpen"><Filter :size="14" />Bộ lọc<ChevronDown :size="14" class="transition-transform" :class="filtersOpen && 'rotate-180'" /></button>
                            <button v-if="activeFilterCount" type="button" class="absolute -right-2 -top-2 flex h-5 w-5 items-center justify-center rounded-full border-2 border-slate-50 bg-rose-600 text-white shadow-sm hover:bg-rose-700" aria-label="Xóa bộ lọc biến thể" title="Xóa bộ lọc" @click.stop="clearVariantFilters"><X :size="11" /></button>
                        </div>
                    </div>
                </div>
                <div v-if="filtersOpen" class="mt-2 grid items-end gap-1.5 rounded-xl border border-slate-200 bg-white p-2" :class="filterGridClass(variantAttributeOptions.length + 1)">
                    <FloatingSelect v-for="filter in variantAttributeOptions" :key="filter.name" v-model="variantFilters[filter.name]" :options="filter.options" :label="filter.name" :placeholder-text="filter.name" compact hide-label input-class="!h-9 !w-full" />
                    <FloatingSelect v-model="variantStockFilter" :options="variantStockOptions" label="Tình trạng kho" placeholder-text="Tình trạng kho" compact hide-label input-class="!h-9 !w-full" />
                </div>
            </div>
            <div class="max-h-[55vh] overflow-auto px-4 pb-4 pt-2 sm:px-5">
                <div class="overflow-x-auto rounded-xl border border-slate-200"><table class="w-full min-w-[760px] border-collapse text-left">
                    <thead class="bg-slate-50 text-[11px] font-bold uppercase tracking-wide text-slate-500"><tr><th class="px-3 py-3">Biến thể</th><th class="px-3 py-3 text-right">Giá nhập</th><th class="px-3 py-3 text-right">Giá cũ</th><th class="px-3 py-3 text-right">Giá bán mới (VNĐ)</th><th class="px-3 py-3 text-right">Lợi nhuận dự kiến</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 text-xs"><tr v-if="!filteredVariants.length"><td colspan="5" class="px-3 py-8 text-center text-sm text-slate-500">Không có biến thể phù hợp với bộ lọc.</td></tr><tr v-for="update in filteredVariants" :key="update.key" class="hover:bg-slate-50">
                        <td class="px-3 py-3"><div class="font-bold text-slate-800" :title="attributeText(update.attributes)">{{ update.label }}</div><span class="font-mono text-[10px] text-slate-400">SKU: {{ update.detail || '—' }}</span><span class="ml-2 inline-block rounded px-1.5 py-0.5 text-[10px] font-semibold" :class="update.stock > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'">{{ update.stock > 0 ? `Còn hàng (${update.stock})` : 'Hết hàng' }}</span></td>
                        <td class="px-3 py-3 text-right font-medium text-slate-600">{{ formatCurrency(update.cost_price) }}</td><td class="px-3 py-3 text-right text-slate-400 line-through">{{ formatCurrency(update.original_sell_price) }}</td>
                        <td class="px-3 py-3 text-right"><div class="inline-flex items-center gap-1"><FloatingInput v-model.number="update.sell_price" type="number" format-thousands :floating-label="false" input-class="!h-9 !w-32 !rounded-lg !border-slate-300 !bg-emerald-50/30 !px-2 !py-1 !text-right !text-sm !font-bold !text-emerald-700 focus:!border-emerald-500" /><span class="text-slate-400">đ</span></div></td>
                        <td class="px-3 py-3 text-right"><span class="inline-flex flex-col rounded-md border px-2.5 py-1 font-semibold" :class="profit(update) >= 0 ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700'"><span>{{ formatCurrency(profit(update)) }}</span><span class="text-[10px]">{{ profitPercent(update) === null ? '—' : `(${profitPercent(update).toFixed(1)}%)` }}</span></span></td>
                    </tr></tbody>
                </table></div>
            </div>
        </template>

        <!-- Sản phẩm đơn thể -->
        <template v-else-if="isSingleProduct">
            <div class="space-y-5 p-5">
                <div class="grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 sm:grid-cols-2">
                    <p class="text-xs text-slate-500">Giá nhập vào:<strong class="mt-1 block text-sm text-slate-900">{{ formatCurrency(singleUpdates[0].cost_price) }}</strong></p>
                    <p class="text-xs text-slate-500">Giá bán hiện tại:<strong class="mt-1 block text-sm text-slate-900">{{ formatCurrency(singleUpdates[0].original_sell_price) }}</strong></p>
                </div>
                <label class="block text-xs font-bold text-slate-800">GIÁ BÁN MỚI (VNĐ)
                    <div class="relative mt-2"><FloatingInput v-model.number="singleUpdates[0].sell_price" type="number" format-thousands :floating-label="false" input-class="!h-14 !w-full !rounded-xl !border-2 !border-emerald-500 !bg-slate-50 !px-4 !py-3 !pr-12 !text-left !text-lg !font-bold !text-slate-900 focus:!ring-emerald-200" /><span class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 font-bold text-slate-400">đ</span></div>
                </label>
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="text-slate-500">Gợi ý nhanh theo giá vốn:</span>
                    <button v-for="markup in [30, 50, 100]" :key="markup" type="button" :disabled="singleUpdates[0].cost_price <= 0" class="rounded-md bg-slate-100 px-2.5 py-1.5 font-semibold text-slate-700 hover:bg-emerald-100 hover:text-emerald-800 disabled:cursor-not-allowed disabled:opacity-50" @click="setSingleMarkup(markup)">+{{ markup }}%</button>
                </div>
                <div class="rounded-lg border border-emerald-100 bg-emerald-50 p-4 text-xs">
                    <p class="font-bold text-emerald-950">Phân tích lợi nhuận thực tế</p>
                    <div class="mt-2 space-y-1 text-slate-700"><p>Chênh lệch giá cũ: <strong :class="priceDifference(singleUpdates[0]) >= 0 ? 'text-emerald-700' : 'text-amber-700'">{{ priceDifference(singleUpdates[0]) >= 0 ? 'Tăng ' : 'Giảm ' }}{{ formatCurrency(Math.abs(priceDifference(singleUpdates[0]))) }}</strong></p><p>Tỷ suất lợi nhuận mới: <strong :class="profit(singleUpdates[0]) >= 0 ? 'text-emerald-700' : 'text-red-600'">{{ formatCurrency(profit(singleUpdates[0])) }}<template v-if="profitPercent(singleUpdates[0]) !== null"> (Markup {{ profitPercent(singleUpdates[0]).toFixed(1) }}%)</template></strong></p></div>
                </div>
            </div>
        </template>

        <!-- Cập nhật nhiều sản phẩm -->
        <template v-else>
            <div class="space-y-4 p-5">
                <div class="flex flex-col gap-2 rounded-lg border border-slate-200 bg-slate-50 p-3 sm:flex-row sm:items-center">
                    <label for="batch-price" class="shrink-0 text-sm font-semibold text-slate-700">Đặt giá chung</label>
                    <div class="relative min-w-0 flex-1"><FloatingInput id="batch-price" v-model.number="batchPrice" type="number" format-thousands :floating-label="false" input-class="!h-10 !w-full !rounded-lg !border-slate-300 !bg-white !px-3 !py-2 !pr-8 !text-right !text-sm !font-semibold focus:!border-emerald-500" placeholder-text="Giá bán" /><span class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-500">đ</span></div>
                    <button type="button" class="h-10 rounded-lg bg-emerald-600 px-4 text-sm font-semibold text-white hover:bg-emerald-700" @click="applyBatchPrice">Áp dụng</button>
                </div>
                <div class="overflow-hidden rounded-lg border border-slate-200">
                    <div v-for="update in form.updates" :key="update.key" class="grid grid-cols-[2rem_minmax(0,1fr)_8rem] items-center gap-2 border-b border-slate-100 px-3 py-2 last:border-b-0" :class="!update.editable && 'bg-slate-50 opacity-60'">
                        <input v-model="update.selected" type="checkbox" :disabled="!update.editable" class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 disabled:cursor-not-allowed" />
                        <div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-800">{{ update.product_name }}</p><p class="truncate text-xs text-slate-500">{{ update.type === 'imei' ? `IMEI: ${update.label}` : update.type === 'variant' ? `Biến thể: ${update.label}` : update.label }}</p></div>
                        <div class="relative"><FloatingInput v-model.number="update.sell_price" type="number" format-thousands :floating-label="false" :disabled="!update.editable" input-class="!h-9 !w-full !rounded-lg !border-slate-300 !px-2 !py-1 !pr-7 !text-right !text-sm !font-semibold disabled:!bg-slate-100" /><span class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-xs text-slate-400">đ</span></div>
                    </div>
                </div>
            </div>
        </template>

        <p v-if="batchError || form.errors.updates" class="px-5 pb-3 text-xs text-red-600">{{ batchError || form.errors.updates }}</p>

        <template #footer>
            <div class="flex justify-end gap-2">
                <ActionButton variant="secondary" @click="emit('close')">Hủy</ActionButton>
                <ActionButton :disabled="form.processing || !changedUpdates.length" @click="submit"><Save :size="16" />{{ form.processing ? 'Đang lưu...' : 'Lưu thay đổi' }}</ActionButton>
            </div>
        </template>
    </BaseModal>
</template>
