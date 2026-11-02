<script setup>
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Calculator, Layers, Save, Smartphone, Tag, X } from 'lucide-vue-next'
import BaseModal from '@/Components/UI/BaseModal.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import { formatCurrency } from '@/utils/format'

const props = defineProps({ products: { type: Array, required: true } })
const emit = defineEmits(['close', 'updated'])

const markupPercent = ref('')
const unifiedPrice = ref('')
const batchPrice = ref('')
const variantMode = ref('individual')
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
            product_name: productName, label: attributeText(variant.attributes) || variant.sku || `Biến thể #${variant.id}`,
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
            detail: attributeText(variant?.attributes) || variant?.sku || '', sell_price: price,
            original_sell_price: price, cost_price: cost, editable: imei.status === 'in_stock',
            selected: false, status: imei.status === 'in_stock' ? 'Còn kho' : 'Đã bán',
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
const suggestedPrice = computed(() => Math.round(averageCost.value * (1 + numericPrice(markupPercent.value) / 100)))

const applyMarkup = () => {
    const percent = Number(markupPercent.value)
    if (!Number.isFinite(percent) || percent < -100) {
        batchError.value = 'Nhập phần trăm lợi nhuận hợp lệ.'
        return
    }
    const targets = singleUpdates.value.filter((update) => update.editable && update.cost_price > 0)
    if (!targets.length) {
        batchError.value = 'Các dòng hiện tại chưa có giá vốn để tính giá.'
        return
    }
    targets.forEach((update) => { update.sell_price = Math.round(update.cost_price * (1 + percent / 100)) })
    batchError.value = ''
}

const applyUnifiedPrice = () => {
    const price = Number(unifiedPrice.value)
    if (!Number.isFinite(price) || price < 0) return
    singleUpdates.value.forEach((update) => { update.sell_price = price })
}

const applyBatchPrice = () => {
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
                        <template v-else-if="singleMode === 'variant'">Phân loại: Sản phẩm nhiều phiên bản và thuộc tính</template>
                        <template v-else>Loại: Sản phẩm đơn thể / Phụ kiện</template>
                    </p>
                </div>
            </div>
            <button type="button" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-400 hover:bg-white hover:text-slate-700" aria-label="Đóng" @click="emit('close')"><X :size="19" /></button>
        </template>

        <!-- Sản phẩm IMEI: bố cục thẻ theo từng máy -->
        <template v-if="isSingleProduct && singleMode === 'imei'">
            <div class="border-b border-emerald-100 bg-emerald-50 px-5 py-3">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-2 text-sm font-semibold text-emerald-950"><Calculator :size="16" />Tính giá nhanh theo % lợi nhuận</div>
                    <div class="flex items-center gap-2 text-xs">
                        <span class="text-slate-600">Lợi nhuận mong muốn:</span>
                        <input v-model="markupPercent" type="number" min="-100" class="h-8 w-20 rounded border-emerald-300 bg-white text-center font-bold text-emerald-800 focus:border-emerald-500 focus:ring-emerald-500" @change="applyMarkup" />
                        <span class="font-bold text-emerald-700">%</span>
                        <button type="button" class="font-semibold text-emerald-700 hover:text-emerald-900" @click="applyMarkup">Áp dụng gợi ý: {{ formatCurrency(suggestedPrice) }}</button>
                    </div>
                </div>
            </div>
            <div class="space-y-3 p-5">
                <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-wide text-slate-400"><span>Danh sách máy trong kho (theo IMEI)</span><span>{{ inStockImeis }} máy</span></div>
                <div v-for="update in singleUpdates" :key="update.key" class="rounded-lg border border-slate-200 bg-slate-50 p-3.5" :class="!update.editable && 'opacity-60'">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex min-w-0 items-center gap-2">
                            <span class="truncate rounded border border-slate-200 bg-white px-2 py-1 font-mono text-xs font-bold text-slate-900">IMEI: {{ update.label }}</span>
                            <span class="rounded border px-2 py-1 text-[11px] font-semibold" :class="update.editable ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-100 text-slate-500'">{{ update.detail || update.status }}</span>
                        </div>
                        <p class="text-xs text-slate-500">Giá nhập: <strong class="text-slate-800">{{ formatCurrency(update.cost_price) }}</strong></p>
                    </div>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2 sm:items-center">
                        <p class="text-xs text-slate-500">Giá bán hiện tại: <span class="text-slate-400 line-through">{{ formatCurrency(update.original_sell_price) }}</span></p>
                        <label class="flex items-center justify-end gap-2 text-xs font-semibold text-slate-700">Giá bán mới:<input v-model="update.sell_price" type="number" min="0" :disabled="!update.editable" class="h-8 w-36 rounded border-slate-300 bg-white text-right font-bold text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 disabled:cursor-not-allowed disabled:bg-slate-100" /><span>đ</span></label>
                    </div>
                    <div class="mt-3 flex flex-wrap justify-between gap-2 border-t border-slate-200 pt-2 text-[11px]">
                        <span class="text-slate-500">Lợi nhuận: <strong :class="profit(update) >= 0 ? 'text-emerald-700' : 'text-red-600'">{{ formatCurrency(profit(update)) }}<template v-if="profitPercent(update) !== null"> ({{ profitPercent(update).toFixed(1) }}%)</template></strong></span>
                        <span v-if="priceDifference(update)" class="font-semibold" :class="priceDifference(update) > 0 ? 'text-emerald-700' : 'text-amber-700'">{{ priceDifference(update) > 0 ? 'Tăng ' : 'Giảm ' }}{{ formatCurrency(Math.abs(priceDifference(update))) }}</span>
                    </div>
                </div>
            </div>
        </template>

        <!-- Sản phẩm có biến thể: bố cục thẻ theo từng phiên bản -->
        <template v-else-if="isSingleProduct && singleMode === 'variant'">
            <div class="border-b border-slate-200 bg-slate-50 px-5 py-4">
                <p class="text-xs font-bold text-slate-700">Chế độ cập nhật giá:</p>
                <div class="mt-2 flex flex-wrap items-center gap-4 text-xs font-semibold text-slate-800">
                    <label class="flex cursor-pointer items-center gap-2"><input v-model="variantMode" type="radio" value="individual" class="text-emerald-600 focus:ring-emerald-500" />Điều chỉnh riêng từng biến thể</label>
                    <label class="flex cursor-pointer items-center gap-2"><input v-model="variantMode" type="radio" value="unified" class="text-emerald-600 focus:ring-emerald-500" />Đặt chung 1 mức giá cho tất cả</label>
                </div>
                <label v-if="variantMode === 'unified'" class="mt-3 flex items-center gap-2 text-xs font-semibold text-slate-700">Giá áp dụng chung:<input v-model="unifiedPrice" type="number" min="0" class="h-8 w-40 rounded border-slate-300 bg-white text-right font-bold focus:border-emerald-500 focus:ring-emerald-500" @input="applyUnifiedPrice" /><span>đ</span></label>
            </div>
            <div class="space-y-3 p-5">
                <div v-for="update in singleUpdates" :key="update.key" class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <span class="inline-flex w-fit items-center gap-1 rounded border border-slate-200 bg-white px-2.5 py-1 text-xs font-bold text-slate-900">Phiên bản: <span class="text-blue-700">{{ update.label }}</span></span>
                        <p class="text-xs text-slate-500">Giá nhập: <strong class="text-slate-800">{{ formatCurrency(update.cost_price) }}</strong></p>
                    </div>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2 sm:items-center">
                        <p class="text-xs text-slate-500">Giá bán cũ: <span class="text-slate-400 line-through">{{ formatCurrency(update.original_sell_price) }}</span></p>
                        <label class="flex items-center justify-end gap-2 text-xs font-semibold text-slate-700">Giá bán mới:<input v-model="update.sell_price" type="number" min="0" class="h-8 w-36 rounded border-slate-300 bg-white text-right font-bold text-slate-900 focus:border-emerald-500 focus:ring-emerald-500" /><span>đ</span></label>
                    </div>
                    <p class="mt-3 border-t border-slate-200 pt-2 text-right text-[11px] font-semibold" :class="profit(update) >= 0 ? 'text-emerald-700' : 'text-red-600'">Lãi: {{ formatCurrency(profit(update)) }}<template v-if="profitPercent(update) !== null"> ({{ profitPercent(update).toFixed(1) }}%)</template></p>
                </div>
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
                    <div class="relative mt-2"><input v-model="singleUpdates[0].sell_price" type="number" min="0" class="h-14 w-full rounded-xl border-2 border-emerald-500 bg-slate-50 pr-10 text-left text-lg font-bold text-slate-900 focus:ring-emerald-200" /><span class="absolute right-4 top-1/2 -translate-y-1/2 font-bold text-slate-400">đ</span></div>
                </label>
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
                    <input id="batch-price" v-model="batchPrice" type="number" min="0" class="h-9 min-w-0 flex-1 rounded border-slate-300 bg-white text-right text-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="Giá bán" />
                    <button type="button" class="h-9 rounded bg-emerald-600 px-3 text-xs font-semibold text-white hover:bg-emerald-700" @click="applyBatchPrice">Áp dụng</button>
                </div>
                <div class="overflow-hidden rounded-lg border border-slate-200">
                    <div v-for="update in form.updates" :key="update.key" class="grid grid-cols-[2rem_minmax(0,1fr)_8rem] items-center gap-2 border-b border-slate-100 px-3 py-2 last:border-b-0" :class="!update.editable && 'bg-slate-50 opacity-60'">
                        <input v-model="update.selected" type="checkbox" :disabled="!update.editable" class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 disabled:cursor-not-allowed" />
                        <div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-800">{{ update.product_name }}</p><p class="truncate text-xs text-slate-500">{{ update.type === 'imei' ? `IMEI: ${update.label}` : update.type === 'variant' ? `Biến thể: ${update.label}` : update.label }}</p></div>
                        <input v-model="update.sell_price" type="number" min="0" :disabled="!update.editable" class="h-8 w-full rounded border-slate-300 text-right text-sm font-semibold disabled:bg-slate-100" />
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
