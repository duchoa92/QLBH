<script setup>
import { computed, ref, watch, onBeforeUnmount } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { X, Check, Search, Cpu, Barcode, AlertCircle } from 'lucide-vue-next'
import { productService } from '@/Modules/POS/Product/Services/productService'
import { formatCurrency } from '@/utils/format'

/*
|--------------------------------------------------------------------------
| Modal chọn biến thể / IMEI khi thêm sản phẩm vào giỏ hàng POS
|--------------------------------------------------------------------------
*/

const props = defineProps({
    show: Boolean,
    product: Object,
    cart: {
        type: Array,
        default: () => [],
    },
    currentImei: { type: Object, default: null },
    currentVariant: { type: Object, default: null },
    extraImeis: { type: Array, default: () => [] },
    quantityAware: { type: Boolean, default: false },
    selectionQuantity: { type: Number, default: 1 },
})

const emit = defineEmits(['close', 'confirm'])
const page = usePage()
const costPriceSuggestionEnabled = computed(() => page.props.settings?.pos_cost_price_suggestion_enabled !== false
    && page.props.settings?.pos_cost_price_suggestion_enabled !== 0
    && page.props.settings?.pos_cost_price_suggestion_enabled !== '0')
const allowNegativeStock = computed(() => [true, 1, '1', 'true', 'on'].includes(page.props.settings?.allow_negative_stock))

const selectedVariant = ref(null)
const selectedImei = ref(null)
const imeis = ref([])
const loadingImeis = ref(false)
const imeiKeyword = ref('')
const showingCostPriceFor = ref('')
let costPriceHoldTimer = null
let costPriceHoldTriggered = false
let suppressCostPriceClick = false

const needsImei = computed(() =>
    props.product?.product_type === 'imei'
    || Boolean(props.product?.manage_stock_by_serial)
)

const hasVariants = computed(() =>
    Boolean(props.product?.variants?.length || props.product?.requires_variant_selection)
)

const needsVariant = computed(() =>
    hasVariants.value && !needsImei.value
)

const requiredVariantQuantity = computed(() => Math.max(1, Number(props.selectionQuantity || 1)))
const cartQuantityForVariant = variant => props.cart.reduce((total, item) => (
    Number(item.id) === Number(props.product?.id)
    && Number(item.variant_id || 0) === Number(variant?.id || 0)
        ? total + Number(item.quantity || 0)
        : total
), 0)
const remainingVariantStock = variant => {
    const allVariantBatches = (props.product?.stock_batches || [])
        .filter(batch => Number(batch.variant_id || 0) === Number(variant?.id || 0))

    // Với hàng theo lô, tồn trong modal phải khớp đúng với từng lô chưa bị
    // giữ ở giỏ. Nhờ vậy lô giá thấp dùng hết thì biến thể không còn chọn
    // nhầm theo tổng tồn cũ.
    if (allVariantBatches.length) {
        return availableVariantBatches(variant)
            .reduce((total, batch) => total + Number(batch.remaining_quantity || 0), 0)
    }

    return Number(variant?.stock || 0) - cartQuantityForVariant(variant)
}
const hasVariantStock = variant => allowNegativeStock.value || remainingVariantStock(variant) >= requiredVariantQuantity.value

const canConfirm = computed(() => {
    if (needsVariant.value && !selectedVariant.value) return false
    if (needsVariant.value && !hasVariantStock(selectedVariant.value)) return false
    if (needsImei.value && !selectedImei.value) return false
    return true
})

const formatMoney = formatCurrency

const attributeText = (attribute) => {
    if (attribute === null || attribute === undefined || attribute === '') return ''
    if (typeof attribute !== 'object') return String(attribute)
    return attribute.value ?? attribute.label ?? attribute.name ?? attribute.title ?? attribute.text ?? ''
}

const variantLabel = (variant) => {
    const attributes = Object.values(variant?.attributes || {})
        .map(attributeText)
        .filter(Boolean)

    return attributes.join(' / ')
        || variant?.sku
        || (variant?.id ? `#${variant.id}` : '')
}

const imeiDisplayCode = (item) =>
    item?.display_code
    || item?.imei
    || item?.serial
    || (item?.id ? `IMEI #${item.id}` : 'Chưa có mã IMEI')

const imeiSellPrice = (item) =>
    Number(
        item?.effective_sell_price
        ?? item?.price
        ?? (item?.sell_price > 0 ? item.sell_price : null)
        ?? (item?.variant?.sell_price > 0 ? item.variant.sell_price : null)
        ?? props.product?.sell_price
        ?? props.product?.price
        ?? 0
    )

const availableVariantBatches = (variant) => {
    const productId = Number(props.product?.product_id ?? props.product?.id)
    const variantId = Number(variant?.id || 0)
    const batches = (props.product?.stock_batches || [])
        .filter(batch => Number(batch.variant_id || 0) === variantId)
        .slice()
        .sort((left, right) => Number(left.id) - Number(right.id))
    let legacyClaim = props.cart
        .filter(item => Number(item.product_id ?? item.id) === productId
            && Number(item.variant_id || 0) === variantId
            && !item.batch_id)
        .reduce((total, item) => total + Number(item.quantity || 0), 0)

    return batches.reduce((available, batch) => {
        const explicitClaim = props.cart
            .filter(item => Number(item.product_id ?? item.id) === productId && Number(item.batch_id) === Number(batch.id))
            .reduce((total, item) => total + Number(item.quantity || 0), 0)
        const legacyForBatch = Math.min(legacyClaim, Number(batch.remaining_quantity || 0))
        legacyClaim -= legacyForBatch
        const remainingQuantity = Number(batch.remaining_quantity || 0) - explicitClaim - legacyForBatch

        if (remainingQuantity > 0) available.push({ ...batch, remaining_quantity: remainingQuantity })
        return available
    }, [])
}

const variantSellPrice = (variant) => {
    const prices = availableVariantBatches(variant)
        .map(batch => Number(batch.sell_price || 0))
        .filter(price => price > 0)
    if (prices.length) return Math.min(...prices)

    return Number(
        (variant?.sell_price > 0 ? variant.sell_price : null)
        ?? (variant?.price > 0 ? variant.price : null)
        ?? props.product?.sell_price
        ?? props.product?.price
        ?? 0
    )
}

const variantPriceLabel = (variant) => {
    const prices = availableVariantBatches(variant)
        .map(batch => Number(batch.sell_price || 0))
        .filter(price => price > 0)
    if (prices.length) {
        const min = Math.min(...prices)
        const max = Math.max(...prices)
        return min === max ? formatMoney(min) : `${formatMoney(min)} - ${formatMoney(max)}`
    }

    return formatMoney(variantSellPrice(variant))
}

const itemCostPrice = (item) => Number(
    (item?.cost_price > 0 ? item.cost_price : null)
    ?? (item?.variant?.cost_price > 0 ? item.variant.cost_price : null)
    ?? props.product?.cost_price
    ?? 0
)

const variantCostPriceLabel = (variant) => {
    const costs = availableVariantBatches(variant)
        .map(batch => Number(batch.cost_price || 0))
        .filter(cost => cost > 0)

    if (costs.length) {
        const min = Math.min(...costs)
        const max = Math.max(...costs)
        return min === max ? formatMoney(min) : `${formatMoney(min)} - ${formatMoney(max)}`
    }

    return formatMoney((variant?.cost_price > 0 ? variant.cost_price : null) ?? props.product?.cost_price ?? 0)
}
const priceKey = (type, item) => `${type}:${item?.id ?? ''}`
const isShowingCostPrice = (type, item) => costPriceSuggestionEnabled.value && showingCostPriceFor.value === priceKey(type, item)
const showCostPrice = (type, item, event) => {
    if (!costPriceSuggestionEnabled.value) return
    clearTimeout(costPriceHoldTimer)
    costPriceHoldTriggered = false
    suppressCostPriceClick = false
    const target = event.currentTarget
    const pointerId = event.pointerId
    const key = priceKey(type, item)
    costPriceHoldTimer = setTimeout(() => {
        showingCostPriceFor.value = key
        costPriceHoldTriggered = true
        target?.setPointerCapture?.(pointerId)
    }, 450)
}
const stopShowingCostPrice = (event) => {
    clearTimeout(costPriceHoldTimer)
    if (event?.type === 'pointerup' && costPriceHoldTriggered) {
        suppressCostPriceClick = true
        showingCostPriceFor.value = ''
        return
    }
    if (event?.type === 'pointercancel') {
        suppressCostPriceClick = false
        showingCostPriceFor.value = ''
    }
}
const shouldSuppressCostPriceClick = (event) => {
    if (!suppressCostPriceClick) return false
    event.preventDefault()
    event.stopPropagation()
    suppressCostPriceClick = false
    return true
}
const handleVariantClick = (variant, event) => {
    if (shouldSuppressCostPriceClick(event)) return
    selectVariant(variant)
}
const handleImeiClick = (item, event) => {
    if (shouldSuppressCostPriceClick(event)) return
    selectImei(item)
}
onBeforeUnmount(() => clearTimeout(costPriceHoldTimer))

const displayVariantStock = (variant) => {
    const remainingStock = remainingVariantStock(variant)
    const quantity = allowNegativeStock.value ? remainingStock : Math.max(0, remainingStock)
    const unit = props.product?.unit_name || ''

    return `${quantity}${unit ? ` ${unit}` : ''}`
}
const filteredImeis = computed(() => {
    const selectedImeiIds = new Set(
        props.cart.map(item => item.imei_id).filter(Boolean)
    )

    const selectedImeiCodes = new Set(
        props.cart.map(item => item.imei || item.serial || item.display_code).filter(Boolean)
    )

    const availableImeis = imeis.value.filter(item =>
        !selectedImeiIds.has(item.id)
        && !selectedImeiCodes.has(item.imei || item.serial || item.display_code)
    )

    if (!imeiKeyword.value.trim()) return availableImeis

    const keyword = imeiKeyword.value.trim().toLowerCase()

    return availableImeis.filter(item =>
        imeiDisplayCode(item).toLowerCase().includes(keyword)
        || String(item.serial || '').toLowerCase().includes(keyword)
        || String(item.color || '').toLowerCase().includes(keyword)
        || String(item.storage || '').toLowerCase().includes(keyword)
        || String(item.extra_info || '').toLowerCase().includes(keyword)
        || variantLabel(item.variant)?.toLowerCase().includes(keyword)
    )
})

const loadImeis = async () => {
    if (!props.product) return

    loadingImeis.value = true
    selectedImei.value = null
    imeis.value = []

    try {
        imeis.value = await productService.imeis(props.product.id)
        for (const imei of props.extraImeis) {
            if (!imeis.value.some(item => Number(item.id) === Number(imei.id))) imeis.value.unshift(imei)
        }
        if (props.currentImei && !imeis.value.some(item => Number(item.id) === Number(props.currentImei.id))) {
            imeis.value.unshift(props.currentImei)
        }
    } catch (error) {
        console.error(error)
    } finally {
        loadingImeis.value = false
    }
}

const selectVariant = (variant) => {
    selectedVariant.value = variant
}

const findVariantById = (variantId) => {
    return props.product?.variants?.find(variant => variant.id === variantId) ?? null
}

const selectImei = (imei) => {
    selectedImei.value = imei
    selectedVariant.value =
        imei.variant
        ?? findVariantById(imei.variant_id)
        ?? null
}

watch(
    () => [props.show, props.product?.id],
    () => {
        clearTimeout(costPriceHoldTimer)
        showingCostPriceFor.value = ''
        if (!props.show) return

        selectedVariant.value = null
        selectedVariant.value = props.currentVariant || null
        selectedImei.value = props.currentImei || null
        imeis.value = []
        imeiKeyword.value = ''

        if (needsImei.value) {
            loadImeis()
        }
    },
    { immediate: true }
)

const confirm = () => {
    if (!canConfirm.value) return

    emit('confirm', {
        variant: selectedVariant.value,
        imei: selectedImei.value,
    })
}
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm animate-fade-in"
        @click.self="emit('close')"
    >
        <div class="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white shadow-2xl border border-slate-100">

            <!-- HEADER -->
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 bg-slate-50/50">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-blue-50 text-blue-600 rounded-xl">
                        <Cpu class="w-5 h-5" />
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Tùy chọn sản phẩm</h2>
                        <p class="text-xs font-semibold text-slate-500 truncate max-w-[280px]">
                            {{ product?.name }}
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors"
                    @click="emit('close')"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <!-- BODY -->
            <div class="flex-1 space-y-5 overflow-y-auto p-5 custom-scrollbar">

                <!-- TÌM KIẾM IMEI (NẾU CẦN) -->
                <div v-if="needsImei" class="relative">
                    <input
                        v-model="imeiKeyword"
                        type="text"
                        placeholder="Tìm kiếm mã IMEI / Seri / Biến thể..."
                        class="w-full pl-9 pr-4 py-2 text-xs font-normal rounded-xl border border-slate-200 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-all"
                    />
                    <Search class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" />
                </div>

                <!-- BƯỚC 1: CHỌN BIẾN THỂ -->
                <div v-if="needsVariant">
                    <div class="mb-2 text-xs font-bold uppercase tracking-wider text-slate-400">
                        Chọn biến thể
                    </div>

                    <div class="grid grid-cols-2 gap-2.5">
                        <button
                            v-for="variant in product.variants"
                            :key="variant.id"
                            type="button"
                            class="cost-price-hold-area flex flex-col justify-between rounded-xl border p-3 text-left transition-all relative overflow-hidden"
                            :class="[
                                selectedVariant?.id === variant.id
                                    ? 'border-blue-600 bg-blue-50/40 text-blue-900 ring-2 ring-blue-600/20'
                                    : 'border-slate-200/80 hover:border-slate-300 bg-white',
                                { 'cursor-not-allowed opacity-50': !hasVariantStock(variant) },
                            ]"
                            :title="costPriceSuggestionEnabled ? 'Nhấn giữ lựa chọn này để xem giá nhập' : undefined"
                            :disabled="!hasVariantStock(variant)"
                            @pointerdown="costPriceSuggestionEnabled && showCostPrice('variant', variant, $event)"
                            @pointerup="stopShowingCostPrice($event)"
                            @pointercancel="stopShowingCostPrice($event)"
                            @lostpointercapture="stopShowingCostPrice($event)"
                            @contextmenu.prevent.stop
                            @click="handleVariantClick(variant, $event)"
                        >
                            <div class="flex w-full items-start justify-between">
                                <span class="font-bold text-xs text-slate-800 line-clamp-2">
                                    {{ variantLabel(variant) }}
                                </span>
                                <span
                                    v-if="selectedVariant?.id === variant.id"
                                    class="p-0.5 bg-blue-600 text-white rounded-full shrink-0 ml-1"
                                >
                                    <Check class="w-3 h-3" />
                                </span>
                            </div>

                            <div class="mt-2 flex items-center justify-between">
                                <span
                                    class="select-none text-xs font-extrabold"
                                    :class="isShowingCostPrice('variant', variant) ? 'text-amber-700' : 'text-rose-600'"
                                >
                                    {{ isShowingCostPrice('variant', variant) ? variantCostPriceLabel(variant) : variantPriceLabel(variant) }}
                                </span>

                                <span class="text-right text-[10px] font-semibold rounded px-1.5 py-0.5" :class="!hasVariantStock(variant) ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-600'">
                                    Tồn: {{ displayVariantStock(variant) }}
                                </span>
                            </div>
                        </button>
                        <p v-if="!product.variants?.length" class="col-span-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">Các biến thể của sản phẩm hiện đã hết hàng.</p>
                    </div>
                </div>

                <!-- BƯỚC 2: CHỌN IMEI -->
                <div v-if="needsImei">
                    <div class="mb-2 flex items-center justify-between">
                        <div class="text-xs font-bold uppercase tracking-wider text-slate-400">
                            Danh sách IMEI / Serial khả dụng
                        </div>

                        <div v-if="imeis.length" class="text-[11px] font-bold text-slate-500">
                            {{ filteredImeis.length }}/{{ imeis.length }} máy
                        </div>
                    </div>

                    <div
                        v-if="loadingImeis"
                        class="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50/50 py-8 text-xs text-slate-400 gap-2"
                    >
                        <div class="w-5 h-5 border-2 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
                        <span>Đang tải danh sách IMEI...</span>
                    </div>

                    <div
                        v-else-if="filteredImeis.length"
                        class="max-h-60 overflow-y-auto rounded-xl border border-slate-200/80 bg-white custom-scrollbar divide-y divide-slate-100"
                    >
                        <button
                            v-for="item in filteredImeis"
                            :key="item.id"
                            type="button"
                            class="cost-price-hold-area flex w-full items-center justify-between gap-3 p-3 text-left transition-colors"
                            :class="selectedImei?.id === item.id ? 'bg-blue-50/50' : 'hover:bg-slate-50'"
                            :title="costPriceSuggestionEnabled ? 'Nhấn giữ lựa chọn này để xem giá nhập' : undefined"
                            @pointerdown="costPriceSuggestionEnabled && showCostPrice('imei', item, $event)"
                            @pointerup="stopShowingCostPrice($event)"
                            @pointercancel="stopShowingCostPrice($event)"
                            @lostpointercapture="stopShowingCostPrice($event)"
                            @contextmenu.prevent.stop
                            @click="handleImeiClick(item, $event)"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <Barcode class="w-4 h-4 text-slate-400 shrink-0" />
                                    <span class="font-mono text-xs font-bold text-slate-900 tracking-wide">
                                        {{ imeiDisplayCode(item) }}
                                    </span>
                                    <span v-if="item.is_used" class="ml-1.5 inline-flex shrink-0 items-center rounded-md border border-amber-300 bg-amber-100 px-1.5 py-0.5 align-middle text-[10px] font-bold not-italic text-amber-800 font-sans">CŨ</span>
                                </div>

                                <div v-if="item.variant" class="mt-1 text-xs font-bold text-blue-600">
                                    {{ variantLabel(item.variant) }}
                                </div>

                                <div v-if="item.serial || item.color || item.storage" class="mt-0.5 text-[11px] font-medium text-slate-500">
                                    {{ [item.serial, item.color, item.storage].filter(Boolean).join(' · ') }}
                                </div>

                                <div class="mt-1.5">
                                    <span
                                        class="select-none text-xs font-extrabold"
                                        :class="isShowingCostPrice('imei', item) ? 'text-amber-700' : 'text-rose-600'"
                                    >
                                        {{ formatMoney(isShowingCostPrice('imei', item) ? itemCostPrice(item) : imeiSellPrice(item)) }}
                                    </span>
                                </div>
                            </div>

                            <div
                                class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border transition-all"
                                :class="selectedImei?.id === item.id
                                    ? 'border-blue-600 bg-blue-600 text-white'
                                    : 'border-slate-300 text-transparent'"
                            >
                                <Check class="w-3 h-3" />
                            </div>
                        </button>
                    </div>

                    <div
                        v-else
                        class="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50/50 py-8 text-xs text-slate-400 gap-1"
                    >
                        <AlertCircle class="w-6 h-6 text-slate-300" />
                        <span class="font-semibold text-slate-500">Không tìm thấy mã IMEI nào</span>
                    </div>

                </div>

            </div>

            <!-- FOOTER -->
            <div class="flex justify-end gap-2 border-t border-slate-100 p-4 bg-slate-50/50">
                <button
                    type="button"
                    class="rounded-xl border border-slate-200/80 bg-white px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 transition-colors"
                    @click="emit('close')"
                >
                    Hủy bỏ
                </button>

                <button
                    type="button"
                    class="rounded-xl bg-blue-600 px-5 py-2 text-xs font-bold text-white shadow-md shadow-blue-200 transition-all hover:bg-blue-700 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none"
                    :disabled="!canConfirm"
                    @click="confirm"
                >
                    Thêm vào giỏ
                </button>
            </div>

        </div>
    </div>
</template>

<style scoped>
.cost-price-hold-area,
.cost-price-hold-area * {
    -webkit-touch-callout: none;
    -webkit-user-select: none;
    -webkit-user-drag: none;
    user-select: none;
}

.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
</style>
