<script setup>
import { computed, ref, watch } from 'vue'
import { X, Check, Search, Cpu, Barcode, AlertCircle } from 'lucide-vue-next'
import { productService } from '@/Modules/POS/Product/Services/productService'

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
})

const emit = defineEmits(['close', 'confirm'])

const selectedVariant = ref(null)
const selectedImei = ref(null)
const imeis = ref([])
const loadingImeis = ref(false)
const imeiKeyword = ref('')

const needsImei = computed(() =>
    props.product?.product_type === 'imei'
    || Boolean(props.product?.manage_stock_by_serial)
)

const hasVariants = computed(() =>
    Boolean(props.product?.variants?.length)
)

const needsVariant = computed(() =>
    hasVariants.value && !needsImei.value
)

const canConfirm = computed(() => {
    if (needsVariant.value && !selectedVariant.value) return false
    if (needsImei.value && !selectedImei.value) return false
    return true
})

const formatMoney = (value) =>
    Number(value || 0).toLocaleString('vi-VN')

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

const priceSourceLabel = (item) => {
    if (item?.price_source === 'imei' || item?.sell_price > 0) return 'Giá máy'
    if (item?.price_source === 'variant' || item?.variant?.sell_price > 0) return 'Giá biến thể'
    return 'Giá sản phẩm'
}

const expectedProfit = (item) => {
    const costPrice = Number(item?.cost_price || 0)
    if (costPrice <= 0) return null
    return imeiSellPrice(item) - costPrice
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
        || item.serial?.toLowerCase().includes(keyword)
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
        if (!props.show) return

        selectedVariant.value = null
        selectedImei.value = null
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
                    <div class="p-2 bg-indigo-50 text-indigo-600 rounded-xl">
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
                        class="w-full pl-9 pr-4 py-2 text-xs font-medium rounded-xl border border-slate-200 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition-all"
                    />
                    <Search class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" />
                </div>

                <!-- BƯỚC 1: CHỌN BIẾN THỂ -->
                <div v-if="needsVariant">
                    <div class="mb-2 text-xs font-bold uppercase tracking-wider text-slate-400">
                        Chọn phiên bản
                    </div>

                    <div class="grid grid-cols-2 gap-2.5">
                        <button
                            v-for="variant in product.variants"
                            :key="variant.id"
                            type="button"
                            class="flex flex-col justify-between rounded-xl border p-3 text-left transition-all relative overflow-hidden"
                            :class="selectedVariant?.id === variant.id
                                ? 'border-indigo-600 bg-indigo-50/40 text-indigo-900 ring-2 ring-indigo-600/20'
                                : 'border-slate-200/80 hover:border-slate-300 bg-white'"
                            @click="selectVariant(variant)"
                        >
                            <div class="flex w-full items-start justify-between">
                                <span class="font-bold text-xs text-slate-800 line-clamp-2">
                                    {{ variantLabel(variant) }}
                                </span>
                                <span
                                    v-if="selectedVariant?.id === variant.id"
                                    class="p-0.5 bg-indigo-600 text-white rounded-full shrink-0 ml-1"
                                >
                                    <Check class="w-3 h-3" />
                                </span>
                            </div>

                            <div class="mt-2 flex items-center justify-between">
                                <span class="text-xs font-extrabold text-indigo-600">
                                    {{ formatMoney(
                                        (variant.sell_price > 0 ? variant.sell_price : null)
                                        ?? (variant.price > 0 ? variant.price : null)
                                        ?? product.price
                                    ) }}đ
                                </span>

                                <span class="text-[10px] font-semibold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded">
                                    Tồn: {{ variant.stock ?? 0 }}
                                </span>
                            </div>
                        </button>
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
                        <div class="w-5 h-5 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
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
                            class="flex w-full items-center justify-between gap-3 p-3 text-left transition-colors"
                            :class="selectedImei?.id === item.id ? 'bg-indigo-50/50' : 'hover:bg-slate-50'"
                            @click="selectImei(item)"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <Barcode class="w-4 h-4 text-slate-400 shrink-0" />
                                    <span class="font-mono text-xs font-bold text-slate-900 tracking-wide">
                                        {{ imeiDisplayCode(item) }}
                                    </span>
                                </div>

                                <div v-if="item.variant" class="mt-1 text-xs font-bold text-indigo-600">
                                    {{ variantLabel(item.variant) }}
                                </div>

                                <div v-if="item.serial || item.color || item.storage" class="mt-0.5 text-[11px] font-medium text-slate-500">
                                    {{ [item.serial, item.color, item.storage].filter(Boolean).join(' · ') }}
                                </div>

                                <div class="mt-1.5 flex flex-wrap items-center gap-2">
                                    <span class="text-xs font-extrabold text-rose-600">
                                        {{ formatMoney(imeiSellPrice(item)) }}đ
                                    </span>

                                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-500">
                                        {{ priceSourceLabel(item) }}
                                    </span>

                                    <span
                                        v-if="expectedProfit(item) !== null"
                                        class="text-[10px] font-semibold"
                                        :class="expectedProfit(item) >= 0 ? 'text-emerald-600' : 'text-rose-600'"
                                    >
                                        Lãi: {{ formatMoney(expectedProfit(item)) }}đ
                                    </span>
                                </div>
                            </div>

                            <div
                                class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border transition-all"
                                :class="selectedImei?.id === item.id
                                    ? 'border-indigo-600 bg-indigo-600 text-white'
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
                    class="rounded-xl bg-indigo-600 px-5 py-2 text-xs font-bold text-white shadow-md shadow-indigo-200 transition-all hover:bg-indigo-700 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none"
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
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
</style>