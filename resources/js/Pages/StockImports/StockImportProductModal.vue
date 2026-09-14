<script setup>
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue'

import BaseModal from '@/Components/UI/BaseModal.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import { filterByKeywords, highlightText } from '@/utils/searchHelper'

import {
    Plus,
    Trash2,
    QrCode,
} from 'lucide-vue-next'

/*
|--------------------------------------------------------------------------
| PROPS / EMITS
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| MODAL STATE
|--------------------------------------------------------------------------
*/

const entryModal = ref({
    mode: null,
    item: null,

    imei_input: '',
    variant_input: null,

    quantity_input: 1,
    conversion_factor_input: 1,

    cost_price_input: 0,
    sell_price_input: 0,
})

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

const formatPrice = (value) =>
    Number(value || 0).toLocaleString('vi-VN')

const removeVietnameseTones = (value = '') =>
    String(value)
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'D')

const variantAttributes = (variant) => {
    if (!variant?.attributes) {
        return []
    }

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
            key: a.name || a.key || `Attr ${i}`,
            value: a.value || a,
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

/*
|--------------------------------------------------------------------------
| VARIANT SEARCH
|--------------------------------------------------------------------------
*/

const filteredVariants = computed(() => {
    const variants =
        entryModal.value.item?.available_variants ||
        entryModal.value.item?.variants ||
        []

    return filterByKeywords(
        variants,
        variantSearch.value,
        variantLabel
    )
})

/*
|--------------------------------------------------------------------------
| SELECT VARIANT
|--------------------------------------------------------------------------
*/

const selectEntryVariant = () => {
    const modal = entryModal.value
    const item = modal.item

    if (!item) {
        return
    }

    const variants =
        item.available_variants ||
        item.variants ||
        []

    let variant = variants.find(
        v => Number(v.id) === Number(modal.variant_input)
    )

    if (!variant && variantSearch.value) {
        const searchNormalized =
            removeVietnameseTones(variantSearch.value)

        const keywords = searchNormalized
            .split(/\s+/)
            .filter(Boolean)

        variant = variants.find(v => {
            const labelNormalized =
                removeVietnameseTones(variantLabel(v))

            return keywords.every(
                keyword =>
                    labelNormalized.includes(keyword)
            )
        })
    }

    if (!variant) {
        return
    }

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
    if (!variant) {
        return
    }

    entryModal.value.variant_input = variant.id

    variantSearch.value = variantLabel(variant)

    showVariantDropdown.value = false

    nextTick(() => {
        selectEntryVariant()
    })
}

watch(variantSearch, () => {
    if (entryModal.value.item) {
        selectEntryVariant()
    }
})

watch(
    () => entryModal.value.variant_input,
    () => {
        if (entryModal.value.item) {
            selectEntryVariant()
        }
    }
)

/*
|--------------------------------------------------------------------------
| OPEN
|--------------------------------------------------------------------------
*/

const openEntryModal = (item) => {
    let mode = 'normal'

    if (
        item.type === 'imei' ||
        item.type === 'imei_variant'
    ) {
        mode = 'imei'
    } else if (item.type === 'variant') {
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

    const defaultCost = defaultVariant
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

    const defaultSell = defaultVariant
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
        mode,
        item,

        imei_input: '',
        variant_input:
            defaultVariant?.id ?? null,

        quantity_input: 1,
        conversion_factor_input:
            Number(item.conversion_factor || 1),

        cost_price_input: defaultCost,
        sell_price_input: defaultSell,
    }

    variantSearch.value = ''
    showVariantDropdown.value = false

    if (defaultVariant) {
        nextTick(() => {
            variantSearch.value =
                variantLabel(defaultVariant)

            selectEntryVariant()
        })
    }
}

/*
|--------------------------------------------------------------------------
| RESET
|--------------------------------------------------------------------------
*/

const resetEntryModal = () => {
    entryModal.value = {
        mode: null,
        item: null,

        imei_input: '',
        variant_input: null,

        quantity_input: 1,
        conversion_factor_input: 1,

        cost_price_input: 0,
        sell_price_input: 0,
    }

    variantSearch.value = ''
    showVariantDropdown.value = false
}

/*
|--------------------------------------------------------------------------
| ADD ENTRY
|--------------------------------------------------------------------------
*/

const addEntry = () => {
    const modal = entryModal.value
    const item = modal.item

    if (!item) {
        return
    }

    /*
    |--------------------------------------------------------------------------
    | VARIANT
    |--------------------------------------------------------------------------
    */

    if (modal.mode === 'variant') {
        const qty = Number(
            modal.quantity_input || 0
        )

        if (!qty) {
            return
        }

        const variants =
            item.available_variants ||
            item.variants ||
            []

        const variant = variants.find(
            v =>
                Number(v.id) ===
                Number(modal.variant_input)
        )

        if (!variant) {
            return
        }

        if (!item.rows) {
            item.rows = []
        }

        item.conversion_factor = Number(
            modal.conversion_factor_input || 1
        )

        const exist = item.rows.find(
            row =>
                Number(row.variant_id) ===
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

        if (!imei) {
            return
        }

        if (!item.imeis) {
            item.imeis = []
        }

        const duplicate =
            item.imeis.some(row => {
                const value =
                    typeof row === 'string'
                        ? row
                        : row.imei

                return String(value)
                    .toLowerCase() ===
                    imei.toLowerCase()
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
                modal.variant_input || null,
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
| OUTSIDE CLICK
|--------------------------------------------------------------------------
*/

const handleClickOutside = (event) => {
    if (
        (
            dropdownVariantRef.value &&
            dropdownVariantRef.value.contains(
                event.target
            )
        ) ||
        (
            dropdownImeiVariantRef.value &&
            dropdownImeiVariantRef.value.contains(
                event.target
            )
        )
    ) {
        return
    }

    showVariantDropdown.value = false
}

/*
|--------------------------------------------------------------------------
| COMPLETE
|--------------------------------------------------------------------------
*/

const complete = () => {
    emit('completed', props.item)
}

const close = () => {
    resetEntryModal()
    emit('close')
}

/*
|--------------------------------------------------------------------------
| LIFECYCLE
|--------------------------------------------------------------------------
*/

onMounted(() => {
    document.addEventListener(
        'click',
        handleClickOutside
    )

    openEntryModal(props.item)
})

onBeforeUnmount(() => {
    document.removeEventListener(
        'click',
        handleClickOutside
    )
})
</script>

<template>
    <BaseModal
        :title="
            entryModal.mode === 'variant'
                ? `Cấu hình biến thể - ${entryModal.item?.name || ''}`
                : entryModal.mode === 'imei'
                    ? `Cấu hình IMEI - ${entryModal.item?.name || ''}`
                    : `Cấu hình sản phẩm - ${entryModal.item?.name || ''}`
        "
        size="lg"
        @close="close"
    >
        <div class="min-h-[280px] space-y-4">

            <!-- HEADER CẤU HÌNH -->
            <div
                class="relative z-10 flex items-center gap-2.5 pb-2"
            >

                <!-- SẢN PHẨM THƯỜNG -->
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

                <div
                    v-if="entryModal.mode === 'normal'"
                    class="w-24 shrink-0"
                >
                    <FloatingInput
                        v-model.number="
                            entryModal.item.conversion_factor
                        "
                        type="number"
                        min="1"
                        label="Quy đổi"
                    />
                </div>

                <!-- BIẾN THỂ -->
                <div
                    v-if="entryModal.mode === 'variant'"
                    class="min-w-[200px] flex-1"
                >
                    <div
                        ref="dropdownVariantRef"
                        class="relative w-full"
                    >
                        <FloatingInput
                            v-model="variantSearch"
                            type="text"
                            label="Gõ để tìm biến thể..."
                            @focus="
                                showVariantDropdown = true
                            "
                        />

                        <div
                            v-if="showVariantDropdown"
                            class="absolute left-0 right-0 top-full z-50 max-h-48 overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-xl"
                        >
                            <div
                                v-for="variant in filteredVariants"
                                :key="variant.id"
                                class="cursor-pointer rounded-lg px-3 py-2 text-xs font-medium text-slate-700 transition-colors hover:bg-blue-50 hover:text-blue-600"
                                @click="
                                    selectVariant(variant)
                                "
                            >
                                <span
                                    v-html="
                                        highlightText(
                                            variantLabel(
                                                variant
                                            ),
                                            variantSearch
                                        )
                                    "
                                />
                            </div>

                            <div
                                v-if="
                                    !filteredVariants.length
                                "
                                class="px-3 py-2 text-center text-xs text-slate-400"
                            >
                                Không tìm thấy
                            </div>
                        </div>
                    </div>
                </div>

                <!-- IMEI -->
                <div
                    v-if="entryModal.mode === 'imei'"
                    class="min-w-[160px] flex-1"
                >
                    <FloatingInput
                        ref="imeiInput"
                        v-model="
                            entryModal.imei_input
                        "
                        type="text"
                        label="Quét hoặc nhập IMEI"
                        @keydown.enter.prevent="addEntry"
                    />
                </div>

                <!-- IMEI + VARIANT -->
                <div
                    v-if="
                        entryModal.mode === 'imei' &&
                        entryModal.item?.type ===
                            'imei_variant'
                    "
                    class="relative min-w-[180px] flex-1"
                >
                    <div
                        ref="dropdownImeiVariantRef"
                        class="relative w-full"
                    >
                        <FloatingInput
                            v-model="variantSearch"
                            type="text"
                            label="Gõ để tìm biến thể..."
                            @focus="
                                showVariantDropdown = true
                            "
                        />

                        <div
                            v-if="showVariantDropdown"
                            class="absolute left-0 right-0 top-full z-50 mt-1 max-h-48 overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-xl"
                        >
                            <div
                                v-for="variant in filteredVariants"
                                :key="variant.id"
                                class="cursor-pointer rounded-lg px-3 py-2 text-xs font-medium text-slate-700 transition-colors hover:bg-blue-50 hover:text-blue-600"
                                @click="
                                    selectVariant(variant)
                                "
                            >
                                <span
                                    v-html="
                                        highlightText(
                                            variantLabel(
                                                variant
                                            ),
                                            variantSearch
                                        )
                                    "
                                />
                            </div>

                            <div
                                v-if="
                                    !filteredVariants.length
                                "
                                class="px-3 py-2 text-center text-xs text-slate-400"
                            >
                                Không tìm thấy
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SỐ LƯỢNG BIẾN THỂ -->
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

                <div
                    v-if="entryModal.mode === 'variant'"
                    class="w-24 shrink-0"
                >
                    <FloatingInput
                        v-model.number="
                            entryModal.conversion_factor_input
                        "
                        type="number"
                        min="1"
                        label="Quy đổi"
                    />
                </div>

                <!-- GIÁ NHẬP -->
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

                <!-- GIÁ BÁN -->
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
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-sm transition-colors hover:bg-emerald-700"
                    @click="addEntry"
                >
                    <Plus class="h-5 w-5" />
                </button>
            </div>

            <!-- =====================================================
                 BẢNG IMEI
            ====================================================== -->

            <div
                v-if="entryModal.mode === 'imei'"
            >
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
                                <th
                                    class="px-3 py-2 text-left"
                                >
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

                                <th
                                    class="px-3 py-2 text-right"
                                >
                                    Giá nhập
                                </th>

                                <th
                                    class="px-3 py-2 text-right"
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
                                    row,
                                    index
                                ) in entryModal.item.imeis"
                                :key="index"
                            >
                                <td
                                    class="px-3 py-2 font-mono text-xs font-medium text-slate-800"
                                >
                                    {{
                                        typeof row === 'string'
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
                                            v-for="
                                                variant in
                                                entryModal.item
                                                    .available_variants ||
                                                entryModal.item
                                                    .variants
                                            "
                                            :key="
                                                variant.id
                                            "
                                            :value="
                                                variant.id
                                            "
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
                                                entryModal
                                                    .item
                                                    .cost_price
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
                                                entryModal
                                                    .item
                                                    .sell_price
                                            )
                                        }}
                                    </span>
                                </td>

                                <td
                                    class="px-2 text-center"
                                >
                                    <button
                                        type="button"
                                        class="p-1 text-slate-400 transition-colors hover:text-red-500"
                                        @click="
                                            removeImei(
                                                entryModal.item,
                                                index
                                            )
                                        "
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
                        hoặc nhập chuỗi IMEI/Serial
                        rồi bấm nút
                        <span
                            class="font-mono font-bold text-emerald-600"
                        >
                            +
                        </span>
                        hoặc nhấn
                        <kbd
                            class="rounded border bg-white px-1 py-0.5 text-[10px] font-semibold shadow-sm"
                        >
                            Enter
                        </kbd>
                        để thêm.
                    </p>
                </div>
            </div>

            <!-- =====================================================
                 BẢNG BIẾN THỂ
            ====================================================== -->

            <div
                v-if="entryModal.mode === 'variant'"
            >
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
                                <th
                                    class="px-3 py-2 text-left"
                                >
                                    Biến thể
                                </th>

                                <th
                                    class="w-24 px-3 py-2 text-center"
                                >
                                    Số lượng
                                </th>

                                <th
                                    class="w-32 px-3 py-2 text-right"
                                >
                                    Giá nhập
                                </th>

                                <th
                                    class="w-32 px-3 py-2 text-right"
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
                                    row,
                                    index
                                ) in entryModal.item.rows"
                                :key="
                                    row.variant_id
                                "
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

                                <td
                                    class="px-2 text-center"
                                >
                                    <button
                                        type="button"
                                        class="p-1 text-slate-400 transition-colors hover:text-red-500"
                                        @click="
                                            removeVariantRow(
                                                entryModal.item,
                                                index
                                            )
                                        "
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
                            class="font-mono font-bold text-emerald-600"
                        >
                            +
                        </span>
                        để thêm.
                    </p>
                </div>
            </div>
        </div>

        <!-- FOOTER -->
        <template #footer>
            <div class="flex justify-end gap-2">
                <button
                    type="button"
                    class="rounded-lg bg-slate-800 px-5 py-2 text-sm font-semibold text-white transition-colors hover:bg-slate-900"
                    @click="complete"
                >
                    Hoàn tất
                </button>
            </div>
        </template>
    </BaseModal>
</template>
