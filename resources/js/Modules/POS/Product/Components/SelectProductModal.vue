<script setup>
import { computed, ref, watch } from 'vue'
import { X, Search, Smartphone, Check } from 'lucide-vue-next'
import { productService } from '@/Modules/POS/Product/Services/productService'

/*
|--------------------------------------------------------------------------
| Modal chọn biến thể / IMEI khi thêm sản phẩm vào giỏ hàng POS
|--------------------------------------------------------------------------
|
| - Sản phẩm có biến thể (product.variants) -> bắt buộc chọn 1 biến thể.
| - Sản phẩm quản lý theo IMEI (product_type === 'imei' hoặc
|   manage_stock_by_serial) -> tải danh sách IMEI còn trong kho (lọc theo
|   biến thể vừa chọn, nếu có) và bắt buộc chọn 1 IMEI.
|
*/

const props = defineProps({
    show: Boolean,
    product: Object,
})

const emit = defineEmits(['close', 'confirm'])

const selectedVariant = ref(null)
const selectedImei = ref(null)
const imeis = ref([])
const loadingImeis = ref(false)
const imeiKeyword = ref('')

const needsVariant = computed(() =>
    Boolean(props.product?.variants?.length)
)

const needsImei = computed(() =>
    props.product?.product_type === 'imei'
    || Boolean(props.product?.manage_stock_by_serial)
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

const variantLabel = (variant) =>
    Object.values(variant.attributes || {})
        .filter(Boolean)
        .join(' / ') || variant.sku || `#${variant.id}`

const filteredImeis = computed(() => {

    if (!imeiKeyword.value.trim()) {
        return imeis.value
    }

    const keyword = imeiKeyword.value.trim().toLowerCase()

    return imeis.value.filter(item =>
        item.imei?.toLowerCase().includes(keyword)
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

        imeis.value = await productService.imeis(
            props.product.id,
            selectedVariant.value?.id ?? null
        )

    } catch (error) {

        console.error(error)

    } finally {

        loadingImeis.value = false
    }
}

const selectVariant = (variant) => {

    selectedVariant.value = variant

    if (needsImei.value) {
        loadImeis()
    }
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

        // Không cần chọn biến thể trước -> tải luôn IMEI (nếu có)
        if (needsImei.value && !needsVariant.value) {
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

                <!-- BƯỚC 2: IMEI -->
                <div v-if="needsImei">

                    <div class="mb-2 flex items-center justify-between">
                        <div class="text-xs font-bold uppercase tracking-wide text-slate-500">
                            Chọn IMEI / số serial
                        </div>

                        <div
                            v-if="imeis.length"
                            class="text-[11px] font-semibold text-slate-400"
                        >
                            {{ filteredImeis.length }}/{{ imeis.length }} máy
                        </div>
                    </div>

                    <!-- Chưa chọn biến thể trước -->
                    <div
                        v-if="needsVariant && !selectedVariant"
                        class="flex items-center gap-2 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-3 text-sm text-slate-500"
                    >
                        <Smartphone :size="16" />
                        Vui lòng chọn phiên bản trước
                    </div>

                    <template v-else>

                        <div class="relative mb-2">
                            <Search
                                :size="14"
                                class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"
                            />
                            <input
                                v-model="imeiKeyword"
                                type="text"
                                placeholder="Tìm theo IMEI..."
                                class="w-full rounded-md border border-slate-300 py-2 pl-8 pr-3 text-sm"
                            >
                        </div>

                        <div
                            v-if="loadingImeis"
                            class="flex items-center justify-center rounded-lg border border-dashed border-slate-300 bg-slate-50 py-6 text-sm text-slate-500"
                        >
                            Đang tải danh sách IMEI...
                        </div>

                        <div
                            v-else-if="filteredImeis.length"
                            class="max-h-48 space-y-1.5 overflow-y-auto pr-1"
                        >
                            <button
                                v-for="item in filteredImeis"
                                :key="item.id"
                                type="button"
                                class="flex w-full items-center justify-between rounded-lg border px-3 py-2 text-left text-sm transition"
                                :class="selectedImei?.id === item.id
                                    ? 'border-blue-600 bg-blue-50 ring-1 ring-blue-600'
                                    : 'border-slate-200 hover:border-slate-300'"
                                @click="selectedImei = item"
                            >
                                <span>
                                    <span class="font-mono font-bold text-slate-800">
                                        {{ item.imei }}
                                    </span>
                                    <span
                                        v-if="item.color || item.storage"
                                        class="ml-2 text-xs text-slate-500"
                                    >
                                        {{ [item.color, item.storage].filter(Boolean).join(' · ') }}
                                    </span>
                                </span>

                                <Check
                                    v-if="selectedImei?.id === item.id"
                                    :size="14"
                                    class="shrink-0 text-blue-600"
                                />
                            </button>
                        </div>

                        <div
                            v-else
                            class="flex items-center justify-center rounded-lg border border-dashed border-slate-300 bg-slate-50 py-6 text-sm text-slate-500"
                        >
                            Không còn IMEI nào trong kho
                        </div>

                    </template>

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
