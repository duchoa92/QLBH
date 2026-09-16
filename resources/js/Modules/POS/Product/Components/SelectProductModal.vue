<script setup>
import { computed, ref, watch } from 'vue'
import { X, Check } from 'lucide-vue-next'
import { productService } from '@/Modules/POS/Product/Services/productService'

/*
|--------------------------------------------------------------------------
| Modal chọn biến thể / IMEI khi thêm sản phẩm vào giỏ hàng POS
|--------------------------------------------------------------------------
|
| - Sản phẩm quản lý theo IMEI (product_type === 'imei' hoặc
|   manage_stock_by_serial) -> chọn IMEI trước, biến thể đi theo IMEI.
| - Sản phẩm có biến thể nhưng không quản lý IMEI -> bắt buộc chọn 1 biến thể.
|
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

    if (needsVariant.value && !selectedVariant.value) {
        return false
    }

    if (needsImei.value && !selectedImei.value) {
        return false
    }

    return true
})

const formatMoney = (value) =>
    Number(value || 0).toLocaleString('vi-VN')

const attributeText = (attribute) => {
    if (attribute === null || attribute === undefined || attribute === '') {
        return ''
    }

    if (typeof attribute !== 'object') {
        return String(attribute)
    }

    return attribute.value
        ?? attribute.label
        ?? attribute.name
        ?? attribute.title
        ?? attribute.text
        ?? ''
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
    if (item?.price_source === 'imei' || item?.sell_price > 0) {
        return 'Giá máy'
    }

    if (item?.price_source === 'variant' || item?.variant?.sell_price > 0) {
        return 'Giá biến thể'
    }

    return 'Giá sản phẩm'
}

const expectedProfit = (item) => {
    const costPrice = Number(item?.cost_price || 0)

    if (costPrice <= 0) {
        return null
    }

    return imeiSellPrice(item) - costPrice
}

const filteredImeis = computed(() => {
    const selectedImeiIds = new Set(
        props.cart
            .map(item => item.imei_id)
            .filter(Boolean)
    )

    const selectedImeiCodes = new Set(
        props.cart
            .map(item => item.imei || item.serial || item.display_code)
            .filter(Boolean)
    )

    const availableImeis = imeis.value.filter(item =>
        !selectedImeiIds.has(item.id)
        && !selectedImeiCodes.has(item.imei || item.serial || item.display_code)
    )

    if (!imeiKeyword.value.trim()) {
        return availableImeis
    }

    const keyword = imeiKeyword.value.trim().toLowerCase()

    return availableImeis.filter(item =>
        imeiDisplayCode(item).toLowerCase().includes(keyword)
        || item.serial?.toLowerCase().includes(keyword)
        || variantLabel(item.variant)?.toLowerCase().includes(keyword)
    )
})

const loadImeis = async () => {

    if (!props.product) {
        return
    }

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
    return props.product?.variants?.find(variant => variant.id === variantId)
        ?? null
}

const selectImei = (imei) => {
    selectedImei.value = imei
    selectedVariant.value =
        imei.variant
        ?? findVariantById(imei.variant_id)
        ?? null
}

// Reset state mỗi khi mở modal cho sản phẩm mới
watch(
    () => [props.show, props.product?.id],
    () => {

        if (!props.show) {
            return
        }

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

    if (!canConfirm.value) {
        return
    }

    emit('confirm', {
        variant: selectedVariant.value,
        imei: selectedImei.value,
    })
}
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/50 p-4"
        @click.self="emit('close')"
    >
        <div class="flex max-h-[85vh] w-full max-w-md flex-col overflow-hidden rounded-xl bg-white shadow-xl">

            <!-- HEADER -->
            <div class="flex items-start justify-between border-b border-slate-200 p-4">
                <div>
                    <h2 class="text-base font-black text-slate-950">
                        Chọn sản phẩm
                    </h2>
                    <p class="mt-0.5 text-sm font-semibold text-slate-600">
                        {{ product?.name }}
                    </p>
                </div>

                <button
                    type="button"
                    class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                    @click="emit('close')"
                >
                    <X :size="18" />
                </button>
            </div>

            <!-- BODY -->
            <div class="flex-1 space-y-5 overflow-y-auto p-4">

                <!-- BƯỚC 1: BIẾN THỂ -->
                <div v-if="needsVariant">
                    <div class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">
                        Chọn phiên bản
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <button
                            v-for="variant in product.variants"
                            :key="variant.id"
                            type="button"
                            class="flex flex-col items-start rounded-lg border px-3 py-2 text-left text-sm transition"
                            :class="selectedVariant?.id === variant.id
                                ? 'border-blue-600 bg-blue-50 ring-1 ring-blue-600'
                                : 'border-slate-200 hover:border-slate-300'"
                            @click="selectVariant(variant)"
                        >
                            <span class="flex w-full items-center justify-between font-bold text-slate-800">
                                {{ variantLabel(variant) }}
                                <Check
                                    v-if="selectedVariant?.id === variant.id"
                                    :size="14"
                                    class="text-blue-600"
                                />
                            </span>

                            <span class="mt-1 text-xs font-semibold text-blue-600">
                                {{ formatMoney(
                                    (variant.sell_price > 0 ? variant.sell_price : null)
                                    ?? (variant.price > 0 ? variant.price : null)
                                    ?? product.price
                                ) }} đ
                            </span>

                            <span class="text-[11px] text-slate-500">
                                Tồn: {{ variant.stock ?? 0 }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- BƯỚC 1: IMEI -->
                <div v-if="needsImei">

                    <div class="mb-2 flex items-center justify-between">
                        <div class="text-xs font-bold uppercase tracking-wide text-slate-500">
                            Chọn IMEI / số serial
                        </div>

                        <div
                            v-if="imeis.length"
                            class="text-[11px] font-semibold text-slate-400"
                        >
                            {{ filteredImeis.length }}/{{ imeis.length }} máy khả dụng
                        </div>
                    </div>

                    <div
                        v-if="loadingImeis"
                        class="flex items-center justify-center rounded-lg border border-dashed border-slate-300 bg-slate-50 py-6 text-sm text-slate-500"
                    >
                        Đang tải danh sách IMEI...
                    </div>

                    <div
                        v-else-if="filteredImeis.length"
                        class="max-h-64 overflow-y-auto rounded-lg border border-slate-200 bg-white"
                    >
                        <button
                            v-for="item in filteredImeis"
                            :key="item.id"
                            type="button"
                            class="flex min-h-16 w-full items-center justify-between gap-3 border-b border-slate-100 px-3 py-3 text-left text-sm transition last:border-b-0"
                            :class="selectedImei?.id === item.id
                                ? 'bg-blue-50'
                                : 'bg-white hover:bg-slate-50'"
                            @click="selectImei(item)"
                        >
                            <span class="min-w-0 flex-1">
                                <span class="block break-all font-mono text-sm font-black text-slate-950">
                                    {{ imeiDisplayCode(item) }}
                                </span>

                                <span
                                    v-if="item.variant"
                                    class="mt-1 block truncate text-xs font-bold text-blue-700"
                                >
                                    {{ variantLabel(item.variant) }}
                                </span>

                                <span
                                    v-if="item.serial || item.color || item.storage"
                                    class="mt-1 block truncate text-xs font-semibold text-slate-500"
                                >
                                    {{ [item.serial, item.color, item.storage].filter(Boolean).join(' · ') }}
                                </span>

                                <span class="mt-2 flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-black text-emerald-700">
                                        {{ formatMoney(imeiSellPrice(item)) }} đ
                                    </span>

                                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-bold text-slate-500">
                                        {{ priceSourceLabel(item) }}
                                    </span>

                                    <span
                                        v-if="expectedProfit(item) !== null"
                                        class="text-[11px] font-semibold"
                                        :class="expectedProfit(item) >= 0 ? 'text-slate-500' : 'text-red-600'"
                                    >
                                        Lãi tạm: {{ formatMoney(expectedProfit(item)) }} đ
                                    </span>
                                </span>
                            </span>

                            <span
                                class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border"
                                :class="selectedImei?.id === item.id
                                    ? 'border-blue-600 bg-blue-600 text-white'
                                    : 'border-slate-300 text-transparent'"
                            >
                                <Check :size="14" />
                            </span>
                        </button>
                    </div>

                    <div
                        v-else
                        class="flex items-center justify-center rounded-lg border border-dashed border-slate-300 bg-slate-50 py-6 text-sm text-slate-500"
                    >
                        Không còn IMEI nào trong kho
                    </div>

                </div>

            </div>

            <!-- FOOTER -->
            <div class="flex justify-end gap-2 border-t border-slate-100 p-4">
                <button
                    type="button"
                    class="rounded-md border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-50"
                    @click="emit('close')"
                >
                    Huỷ
                </button>

                <button
                    type="button"
                    class="rounded-md bg-blue-600 px-5 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!canConfirm"
                    @click="confirm"
                >
                    Thêm vào giỏ
                </button>
            </div>

        </div>
    </div>
</template>
