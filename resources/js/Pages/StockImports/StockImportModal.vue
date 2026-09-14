<script setup>
import {
    computed,
    ref,
    watch,
    nextTick,
    onMounted,
    onBeforeUnmount,
} from 'vue'

import BaseModal from '@/Components/UI/BaseModal.vue'
import { filterByKeywords, highlightText } from '@/utils/searchHelper'
import StockImportProductModal from './StockImportProductModal.vue'

import {
    Plus,
    Trash2,
    QrCode,
} from 'lucide-vue-next'

import FloatingInput from '@/Components/UI/FloatingInput.vue'

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },
})

const emit = defineEmits([
    'close',
    'completed',
])

/*
|--------------------------------------------------------------------------
| REFS
|--------------------------------------------------------------------------
*/

const imeiInput = ref(null)

const dropdownVariantRef = ref(null)
const dropdownImeiVariantRef = ref(null)

const showVariantDropdown = ref(false)
const variantSearch = ref('')
const isSelecting = ref(false)



const showProductModal = ref(false)
const productModalItem = ref(null)
const openProductConfig = (item) => {
    productModalItem.value = item
    showProductModal.value = true
}

const closeProductConfig = () => {
    showProductModal.value = false
    productModalItem.value = null
}
/*
|--------------------------------------------------------------------------
| OUTSIDE CLICK
|--------------------------------------------------------------------------
*/

const handleClickOutside = (e) => {
    if (
        (dropdownVariantRef.value &&
            dropdownVariantRef.value.contains(e.target)) ||
        (dropdownImeiVariantRef.value &&
            dropdownImeiVariantRef.value.contains(e.target))
    ) {
        return
    }

    showVariantDropdown.value = false
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
})

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside)
})

/*
|--------------------------------------------------------------------------
| ENTRY MODAL
|--------------------------------------------------------------------------
*/

const entryModal = ref({
    open: true,
    mode: null,
    item: props.item,
    imei_input: '',
    variant_input: null,
    quantity_input: 1,
    cost_price_input: 0,
    sell_price_input: 0,
})

/*
|--------------------------------------------------------------------------
| FORMAT
|--------------------------------------------------------------------------
*/

const formatPrice = (value) =>
    Number(value || 0).toLocaleString('vi-VN')

/*
|--------------------------------------------------------------------------
| VARIANT HELPERS
|--------------------------------------------------------------------------
*/

const variantAttributes = (variant) => {
    if (!variant?.attributes) return []

    if (
        typeof variant.attributes === 'object' &&
        !Array.isArray(variant.attributes)
    ) {
        return Object.entries(variant.attributes).map(
            ([key, value]) => ({
                key,
                value,
            })
        )
    }

    if (Array.isArray(variant.attributes)) {
        return variant.attributes.map((a, i) => ({
            key:
                a.name ||
                a.key ||
                `Attr ${i}`,
            value:
                a.value ||
                a,
        }))
    }

    return []
}

const variantLabel = (variant) => {
    const attrs = variantAttributes(variant)

    return attrs.length
        ? attrs.map(a => a.value).join(' - ')
        : (variant?.sku || '')
}

const filteredVariants = computed(() => {
    const variants =
        entryModal.value.item?.available_variants ||
        entryModal.value.item?.variants ||
        []

    return filterByKeywords(
        variants,
        variantSearch.value,
        (v) => variantLabel(v)
    )
})

/*
|--------------------------------------------------------------------------
| REMOVE VIETNAMESE TONES
|--------------------------------------------------------------------------
*/

const removeVietnameseTones = (str) => {
    if (!str) return ''

    return str
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'D')
        .toLowerCase()
}

/*
|--------------------------------------------------------------------------
| SELECT ENTRY VARIANT
|--------------------------------------------------------------------------
*/

const selectEntryVariant = () => {
    const modal = entryModal.value
    const item = modal.item

    if (!item) return

    const variants =
        item.available_variants ||
        item.variants ||
        []

    if (
        !variantSearch.value.trim() &&
        !modal.variant_input
    ) {
        return
    }

    let variant = variants.find(
        v =>
            Number(v.id) ===
            Number(modal.variant_input)
    )

    if (!variant && variantSearch.value) {
        const searchNormalized =
            removeVietnameseTones(
                variantSearch.value
            )

        const keywords =
            searchNormalized
                .split(/\s+/)
                .filter(Boolean)

        variant = variants.find(v => {
            const labelNormalized =
                removeVietnameseTones(
                    variantLabel(v)
                )

            return keywords.every(
                kw =>
                    labelNormalized.includes(kw)
            )
        })
    }

    if (!variant) return

    modal.variant_input = variant.id

    const cost = Number(
        variant.cost_price ??
        variant.import_price ??
        item.cost_price ??
        0
    )

    const sell = Number(
        variant.sell_price ??
        variant.retail_price ??
        variant.price ??
        item.sell_price ??
        0
    )

    entryModal.value.cost_price_input = cost
    entryModal.value.sell_price_input = sell
}

const selectVariant = (variant) => {
    if (!variant) return

    isSelecting.value = true

    entryModal.value.variant_input =
        variant.id

    variantSearch.value =
        variantLabel(variant)

    showVariantDropdown.value = false

    nextTick(() => {
        selectEntryVariant()

        setTimeout(() => {
            isSelecting.value = false
        }, 100)
    })
}

const onFocusVariant = () => {
    if (!isSelecting.value) {
        showVariantDropdown.value = true
    }
}

/*
|--------------------------------------------------------------------------
| WATCH VARIANT
|--------------------------------------------------------------------------
*/

watch(
    () => entryModal.value.variant_input,
    () => {
        if (entryModal.value.open) {
            selectEntryVariant()
        }
    }
)

watch(variantSearch, () => {
    if (entryModal.value.open) {
        showVariantDropdown.value = true
    }
})

/*
|--------------------------------------------------------------------------
| ADD ENTRY
|--------------------------------------------------------------------------
*/

const addEntry = () => {
    const modal = entryModal.value
    const item = modal.item

    if (!item) return

    /*
    |--------------------------------------------------------------------------
    | VARIANT
    |--------------------------------------------------------------------------
    */

    if (modal.mode === 'variant') {
        const qty =
            Number(
                modal.quantity_input || 0
            )

        if (!qty) return

        const variants =
            item.available_variants ||
            item.variants ||
            []

        const variant =
            variants.find(
                v =>
                    Number(v.id) ===
                    Number(
                        modal.variant_input
                    )
            )

        if (!variant) return

        if (!item.rows) {
            item.rows = []
        }

        const exist =
            item.rows.find(
                r =>
                    Number(r.variant_id) ===
                    Number(variant.id)
            )

        if (exist) {
            exist.quantity += qty
        } else {
            item.rows.push({
                variant_id: variant.id,
                variant,
                quantity: qty,
                cost_price:
                    Number(
                        modal.cost_price_input
                    ),
                sell_price:
                    Number(
                        modal.sell_price_input
                    ),
            })
        }

        return
    }

    /*
    |--------------------------------------------------------------------------
    | IMEI
    |--------------------------------------------------------------------------
    */

    if (modal.mode === 'imei') {
        const imei =
            String(
                modal.imei_input || ''
            ).trim()

        if (!imei) return

        if (!item.imeis) {
            item.imeis = []
        }

        const duplicate =
            item.imeis.some(row => {
                const value =
                    typeof row === 'string'
                        ? row
                        : row.imei

                return (
                    String(value)
                        .toLowerCase() ===
                    imei.toLowerCase()
                )
            })

        if (duplicate) {
            return
        }

        item.imeis.push({
            imei,
            cost_price:
                Number(
                    modal.cost_price_input
                ),
            sell_price:
                Number(
                    modal.sell_price_input
                ),
            variant_id:
                modal.variant_input ||
                null,
        })

        modal.imei_input = ''

        nextTick(() => {
            imeiInput.value?.focus?.()
        })
    }
}

/*
|--------------------------------------------------------------------------
| REMOVE
|--------------------------------------------------------------------------
*/

const removeImei = (item, index) => {
    item.imeis.splice(index, 1)
}

const removeVariantRow = (item, index) => {
    item.rows.splice(index, 1)
}

/*
|--------------------------------------------------------------------------
| COMPLETE
|--------------------------------------------------------------------------
*/

const complete = () => {
    emit('completed', entryModal.value.item)
    emit('close')
}

/*
|--------------------------------------------------------------------------
| INIT
|--------------------------------------------------------------------------
*/

const init = () => {
    const item = props.item

    let mode = 'normal'

    if (
        item.type === 'imei' ||
        item.type === 'imei_variant'
    ) {
        mode = 'imei'
    } else if (
        item.type === 'variant'
    ) {
        mode = 'variant'
    }

    const variants =
        item.available_variants ||
        item.variants ||
        []

    const defaultVariant =
        (
            mode === 'variant' ||
            item.type === 'imei_variant'
        ) && variants.length
            ? variants[0]
            : null

    const defaultCost =
        defaultVariant
            ? Number(
                defaultVariant.cost_price ??
                defaultVariant.import_price ??
                item.cost_price ??
                0
            )
            : Number(
                item.cost_price ??
                item.import_price ??
                0
            )

    const defaultSell =
        defaultVariant
            ? Number(
                defaultVariant.sell_price ??
                defaultVariant.retail_price ??
                defaultVariant.price ??
                item.sell_price ??
                0
            )
            : Number(
                item.sell_price ??
                item.price ??
                0
            )

    entryModal.value = {
        open: true,
        mode,
        item,
        imei_input: '',
        variant_input: null,
        quantity_input: 1,
        cost_price_input: defaultCost,
        sell_price_input: defaultSell,
    }

    variantSearch.value = ''
    showVariantDropdown.value = false
}

init()
</script>

<template>
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
        @close="emit('close')"
    >
        <div class="space-y-4 min-h-[280px]">

            <!-- INPUT -->
            <div class="relative z-10 flex items-center gap-2.5 pb-2">

                <!-- SIMPLE -->
                <div
                    v-if="entryModal.mode === 'normal'"
                    class="w-28 shrink-0"
                >
                    <FloatingInput
                        v-model.number="
                            entryModal.item.quantity
                        "
                        type="number"
                        min="1"
                        label="Số lượng"
                    />
                </div>

                <!-- VARIANT -->
                <div
                    v-if="entryModal.mode === 'variant'"
                    class="flex-1 min-w-[200px]"
                >
                    <div
                        ref="dropdownVariantRef"
                        class="w-full relative"
                    >
                        <FloatingInput
                            v-model="variantSearch"
                            @focus="onFocusVariant"
                            @input="
                                showVariantDropdown = true
                            "
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
                                @click="
                                    selectVariant(variant)
                                "
                                class="cursor-pointer rounded-lg px-3 py-2 text-xs font-medium text-slate-700 hover:bg-blue-50 hover:text-blue-600 transition-colors"
                            >
                                <span
                                    v-html="
                                        highlightText(
                                            variantLabel(variant),
                                            variantSearch
                                        )
                                    "
                                ></span>
                            </div>

                            <div
                                v-if="
                                    !filteredVariants.length
                                "
                                class="px-3 py-2 text-xs text-slate-400 text-center"
                            >
                                Không tìm thấy
                            </div>
                        </div>
                    </div>
                </div>

                <!-- IMEI -->
                <div
                    v-if="entryModal.mode === 'imei'"
                    class="flex-1 min-w-[160px]"
                >
                    <FloatingInput
                        v-model="
                            entryModal.imei_input
                        "
                        @keydown.enter.prevent="addEntry"
                        type="text"
                        label="Quét hoặc nhập IMEI"
                        ref="imeiInput"
                    />
                </div>

                <!-- IMEI + VARIANT -->
                <div
                    v-if="
                        entryModal.mode === 'imei' &&
                        entryModal.item?.type ===
                            'imei_variant'
                    "
                    class="relative flex-1 min-w-[180px]"
                >
                    <div
                        ref="dropdownImeiVariantRef"
                        class="w-full relative"
                    >
                        <FloatingInput
                            v-model="variantSearch"
                            @focus="onFocusVariant"
                            @input="
                                showVariantDropdown = true
                            "
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
                                @click="
                                    selectVariant(variant)
                                "
                                class="cursor-pointer rounded-lg px-3 py-2 text-xs font-medium text-slate-700 hover:bg-blue-50 hover:text-blue-600 transition-colors"
                            >
                                <span
                                    v-html="
                                        highlightText(
                                            variantLabel(variant),
                                            variantSearch
                                        )
                                    "
                                ></span>
                            </div>

                            <div
                                v-if="
                                    !filteredVariants.length
                                "
                                class="px-3 py-2 text-xs text-slate-400 text-center"
                            >
                                Không tìm thấy
                            </div>
                        </div>
                    </div>
                </div>

                <!-- QUANTITY -->
                <div
                    v-if="entryModal.mode === 'variant'"
                    class="w-24 shrink-0"
                >
                    <FloatingInput
                        v-model.number="
                            entryModal.quantity_input
                        "
                        type="number"
                        min="1"
                        label="Số lượng"
                    />
                </div>

                <!-- COST -->
                <div class="w-28 shrink-0">
                    <FloatingInput
                        v-if="
                            entryModal.mode !== 'normal'
                        "
                        v-model.number="
                            entryModal.cost_price_input
                        "
                        type="number"
                        label="Giá nhập"
                    />

                    <FloatingInput
                        v-else
                        v-model.number="
                            entryModal.item.cost_price
                        "
                        type="number"
                        label="Giá nhập"
                    />
                </div>

                <!-- SELL -->
                <div class="w-28 shrink-0">
                    <FloatingInput
                        v-if="
                            entryModal.mode !== 'normal'
                        "
                        v-model.number="
                            entryModal.sell_price_input
                        "
                        type="number"
                        label="Giá bán"
                    />

                    <FloatingInput
                        v-else
                        v-model.number="
                            entryModal.item.sell_price
                        "
                        type="number"
                        label="Giá bán"
                    />
                </div>

                <!-- ADD -->
                <button
                    v-if="
                        entryModal.mode !== 'normal'
                    "
                    type="button"
                    @click="addEntry"
                    class="h-10 w-10 shrink-0 rounded-xl bg-emerald-600 text-white flex items-center justify-center hover:bg-emerald-700 font-bold transition-colors shadow-sm"
                >
                    <Plus class="h-5 w-5" />
                </button>
            </div>

            <!-- IMEI TABLE -->
            <div v-if="entryModal.mode === 'imei'">

                <div
                    v-if="
                        entryModal.item?.imeis?.length
                    "
                    class="mt-4 max-h-60 overflow-auto rounded-xl border border-slate-200"
                >
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="bg-slate-50 text-xs font-semibold text-slate-600"
                            >
                                <th class="px-3 py-2 text-left">
                                    IMEI / Serial
                                </th>

                                <th
                                    v-if="
                                        entryModal.item?.type ===
                                        'imei_variant'
                                    "
                                    class="px-3 py-2 text-left"
                                >
                                    Biến thể
                                </th>

                                <th class="px-3 py-2 text-right">
                                    Giá nhập
                                </th>

                                <th class="px-3 py-2 text-right">
                                    Giá bán
                                </th>

                                <th class="w-10"></th>
                            </tr>
                        </thead>

                        <tbody
                            class="divide-y divide-slate-100"
                        >
                            <tr
                                v-for="(
                                    row, index
                                ) in entryModal.item.imeis"
                                :key="index"
                            >
                                <td
                                    class="px-3 py-2 font-mono text-xs font-medium text-slate-800"
                                >
                                    {{
                                        typeof row ===
                                        'string'
                                            ? row
                                            : row.imei
                                    }}
                                </td>

                                <td
                                    v-if="
                                        entryModal.item?.type ===
                                        'imei_variant'
                                    "
                                    class="px-3 py-2"
                                >
                                    <select
                                        v-model="
                                            row.variant_id
                                        "
                                        class="h-8 w-full rounded-lg border border-slate-200 px-2 text-xs outline-none focus:border-blue-500"
                                    >
                                        <option
                                            v-for="variant in entryModal.item.available_variants || entryModal.item.variants"
                                            :key="variant.id"
                                            :value="variant.id"
                                        >
                                            {{
                                                variantLabel(
                                                    variant
                                                )
                                            }}
                                        </option>
                                    </select>
                                </td>

                                <td
                                    class="px-3 py-2 text-right"
                                >
                                    <input
                                        v-if="
                                            typeof row !==
                                            'string'
                                        "
                                        v-model.number="
                                            row.cost_price
                                        "
                                        type="number"
                                        class="h-8 w-28 rounded-lg border border-slate-200 px-2 text-right text-xs outline-none focus:border-blue-500"
                                    />

                                    <span
                                        v-else
                                        class="text-xs text-slate-700"
                                    >
                                        {{
                                            formatPrice(
                                                entryModal.item.cost_price
                                            )
                                        }}
                                    </span>
                                </td>

                                <td
                                    class="px-3 py-2 text-right"
                                >
                                    <input
                                        v-if="
                                            typeof row !==
                                            'string'
                                        "
                                        v-model.number="
                                            row.sell_price
                                        "
                                        type="number"
                                        class="h-8 w-28 rounded-lg border border-slate-200 px-2 text-right text-xs outline-none focus:border-blue-500"
                                    />

                                    <span
                                        v-else
                                        class="text-xs text-slate-700"
                                    >
                                        {{
                                            formatPrice(
                                                entryModal.item.sell_price
                                            )
                                        }}
                                    </span>
                                </td>

                                <td class="px-2 text-center">
                                    <button
                                        type="button"
                                        @click="
                                            removeImei(
                                                entryModal.item,
                                                index
                                            )
                                        "
                                        class="p-1 text-slate-400 hover:text-red-500 transition-colors"
                                    >
                                        <Trash2
                                            class="h-4 w-4"
                                        />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-else
                    class="mt-4 flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50/50 py-10 text-center"
                >
                    <div
                        class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-blue-50 text-blue-600"
                    >
                        <QrCode class="h-6 w-6" />
                    </div>

                    <p
                        class="text-sm font-semibold text-slate-700"
                    >
                        Chưa có mã IMEI / Serial nào
                    </p>

                    <p
                        class="mt-1 max-w-sm text-xs text-slate-400"
                    >
                        Hãy quét mã vạch bằng máy quét
                        hoặc nhập chuỗi IMEI/Serial rồi
                        bấm nút
                        <span
                            class="font-bold text-emerald-600 font-mono"
                        >
                            +
                        </span>
                        để thêm.
                    </p>
                </div>
            </div>

            <!-- VARIANT TABLE -->
            <div v-if="entryModal.mode === 'variant'">

                <div
                    v-if="
                        entryModal.item?.rows?.length
                    "
                    class="mt-4 max-h-60 overflow-auto rounded-xl border border-slate-200"
                >
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="bg-slate-50 text-xs font-semibold text-slate-600"
                            >
                                <th class="px-3 py-2 text-left">
                                    Biến thể
                                </th>

                                <th
                                    class="px-3 py-2 text-center w-24"
                                >
                                    Số lượng
                                </th>

                                <th
                                    class="px-3 py-2 text-right w-32"
                                >
                                    Giá nhập
                                </th>

                                <th
                                    class="px-3 py-2 text-right w-32"
                                >
                                    Giá bán
                                </th>

                                <th class="w-10"></th>
                            </tr>
                        </thead>

                        <tbody
                            class="divide-y divide-slate-100"
                        >
                            <tr
                                v-for="(
                                    row, index
                                ) in entryModal.item.rows"
                                :key="row.variant_id"
                            >
                                <td
                                    class="px-3 py-2 font-medium text-slate-800"
                                >
                                    {{
                                        variantLabel(
                                            row.variant
                                        )
                                    }}
                                </td>

                                <td class="px-3 py-2">
                                    <input
                                        v-model.number="
                                            row.quantity
                                        "
                                        type="number"
                                        min="1"
                                        class="h-8 w-full rounded-lg border border-slate-200 text-center text-xs outline-none focus:border-blue-500"
                                    />
                                </td>

                                <td class="px-3 py-2">
                                    <input
                                        v-model.number="
                                            row.cost_price
                                        "
                                        type="number"
                                        class="h-8 w-full rounded-lg border border-slate-200 px-2 text-right text-xs outline-none focus:border-blue-500"
                                    />
                                </td>

                                <td class="px-3 py-2">
                                    <input
                                        v-model.number="
                                            row.sell_price
                                        "
                                        type="number"
                                        class="h-8 w-full rounded-lg border border-slate-200 px-2 text-right text-xs outline-none focus:border-blue-500"
                                    />
                                </td>

                                <td class="px-2 text-center">
                                    <button
                                        type="button"
                                        @click="
                                            removeVariantRow(
                                                entryModal.item,
                                                index
                                            )
                                        "
                                        class="p-1 text-slate-400 hover:text-red-500 transition-colors"
                                    >
                                        <Trash2
                                            class="h-4 w-4"
                                        />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-else
                    class="mt-4 flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50/50 py-10 text-center"
                >
                    <p
                        class="text-sm font-semibold text-slate-700"
                    >
                        Chưa chọn biến thể nào
                    </p>

                    <p
                        class="mt-1 max-w-sm text-xs text-slate-400"
                    >
                        Vui lòng chọn biến thể ở danh
                        sách thả xuống phía trên và bấm
                        <span
                            class="font-bold text-emerald-600 font-mono"
                        >
                            +
                        </span>
                        để thêm.
                    </p>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <button
                    type="button"
                    @click="complete"
                    class="rounded-lg bg-slate-800 px-5 py-2 text-sm font-semibold text-white hover:bg-slate-900 transition-colors"
                >
                    Hoàn tất
                </button>
            </div>
        </template>
    </BaseModal>
    <StockImportProductModal
        v-if="showProductModal && productModalItem"
        :item="productModalItem"
        @close="closeProductConfig"
        @completed="closeProductConfig"
    />
</template>