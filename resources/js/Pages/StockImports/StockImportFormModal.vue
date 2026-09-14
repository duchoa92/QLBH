<script setup>
import {
    computed,
    ref,
    watch,
    onBeforeUnmount,
} from 'vue'

import { useForm } from '@inertiajs/vue3'

import api from '@/Services/api'

import BaseModal from '@/Components/UI/BaseModal.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'

import SupplierSection from './SupplierSection.vue'
import StockImportProductModal from './StockImportProductModal.vue'

import {
    Search,
    X,
    FileUp,
    Package,
    Pencil,
    Trash2,
} from 'lucide-vue-next'

const props = defineProps({
    suppliers: {
        type: Array,
        default: () => [],
    },

    categories: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits([
    'close',
    'created',
])

const form = useForm({
    supplier_id: null,
    import_date: new Date().toISOString().slice(0, 10),

    items: [],

    discount: 0,
    extra_fee: 0,
    note: '',
})

/*
|--------------------------------------------------------------------------
| SUPPLIER
|--------------------------------------------------------------------------
*/

const selectedSupplier = ref(null)

const selectSupplier = (supplier) => {
    selectedSupplier.value = supplier
    form.supplier_id = supplier?.id ?? null
}

/*
|--------------------------------------------------------------------------
| PRODUCT SEARCH
|--------------------------------------------------------------------------
*/

const keyword = ref('')
const searchResults = ref([])
const searching = ref(false)
const showResults = ref(false)

let searchTimeout = null

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
        const res = await api.get(
            '/api/products',
            {
                params: {
                    keyword: value,
                },
            }
        )

        searchResults.value =
            res.data || []
    } catch (error) {
        console.error(
            'Lỗi khi tải danh sách sản phẩm:',
            error
        )

        searchResults.value = []
    } finally {
        searching.value = false
    }
}

watch(keyword, () => {
    clearTimeout(searchTimeout)

    searchTimeout = setTimeout(
        searchProducts,
        300
    )
})

onBeforeUnmount(() => {
    clearTimeout(searchTimeout)
})

/*
|--------------------------------------------------------------------------
| SELECTED ITEMS
|--------------------------------------------------------------------------
*/

const selectedItems = ref([])

const formErrorMessages = computed(() =>
    Object.values(form.errors || {})
        .flat()
        .filter(Boolean)
)

/*
|--------------------------------------------------------------------------
| PRODUCT CONFIG MODAL
|--------------------------------------------------------------------------
*/

const productModal = ref({
    open: false,
    item: null,
})

/*
|--------------------------------------------------------------------------
| SELECT PRODUCT
|--------------------------------------------------------------------------
*/

const selectProduct = (product) => {
    const item =
        JSON.parse(
            JSON.stringify(product)
        )

    let type = 'simple'

    const hasIMEI =
        item.manage_stock_by_serial ||
        item.has_imei

    const hasVariant =
        item.variants?.length ||
        item.has_variants

    if (
        hasIMEI &&
        hasVariant
    ) {
        type = 'imei_variant'
    } else if (hasIMEI) {
        type = 'imei'
    } else if (hasVariant) {
        type = 'variant'
    }

    let target =
        selectedItems.value.find(
            i =>
                Number(i.product_id) ===
                    Number(item.id) &&
                i.type === type
        )

    if (!target) {
        const costPrice =
            Number(
                item.cost_price ??
                item.import_price ??
                0
            )

        const sellPrice =
            Number(
                item.sell_price ??
                item.price ??
                0
            )

        target = {
            type,

            product_id: item.id,

            name: item.name,

            unit:
                item.unit_name ??
                item.unit ??
                'Cái',

            unit_id:
                item.unit_id ?? null,

            unit_name:
                item.unit_name ??
                item.unit ??
                'Cái',

            conversion_factor: 1,

            quantity:
                type === 'simple'
                    ? 1
                    : 0,

            cost_price:
                costPrice,

            sell_price:
                sellPrice,

            cost_price_input:
                costPrice,

            sell_price_input:
                sellPrice,

            imeis: [],

            rows: [],

            available_variants:
                item.variants || [],

            variants:
                item.variants || [],
        }

        selectedItems.value.push(
            target
        )
    }

    keyword.value = ''
    searchResults.value = []
    showResults.value = false

    productModal.value = {
        open: true,
        item: target,
    }
}

/*
|--------------------------------------------------------------------------
| PRODUCT MODAL
|--------------------------------------------------------------------------
*/

const closeProductModal = () => {
    productModal.value = {
        open: false,
        item: null,
    }
}

const handleProductCompleted = (
    item
) => {
    const index =
        selectedItems.value.findIndex(
            i =>
                Number(i.product_id) ===
                    Number(
                        item.product_id
                    ) &&
                i.type === item.type
        )

    if (index !== -1) {
        selectedItems.value[index] =
            item
    }

    closeProductModal()
}

/*
|--------------------------------------------------------------------------
| REMOVE
|--------------------------------------------------------------------------
*/

const removeItem = (index) => {
    selectedItems.value.splice(
        index,
        1
    )
}

/*
|--------------------------------------------------------------------------
| FORMAT
|--------------------------------------------------------------------------
*/

const formatPrice = (value) =>
    Number(
        value || 0
    ).toLocaleString('vi-VN')

const itemUnit = (item) =>
    item.unit_name ||
    item.unit ||
    'Cái'

const baseQuantity = (quantity, conversionFactor = 1) =>
    Math.max(
        0,
        Math.round(
            Number(quantity || 0) *
            Math.max(1, Number(conversionFactor || 1))
        )
    )

/*
|--------------------------------------------------------------------------
| QUANTITY
|--------------------------------------------------------------------------
*/

const itemQuantity = (item) => {
    if (
        item.type === 'simple'
    ) {
        return baseQuantity(
            item.quantity,
            item.conversion_factor
        )
    }

    if (
        item.type === 'imei' ||
        item.type === 'imei_variant'
    ) {
        return (
            item.imeis || []
        ).length
    }

    if (
        item.type === 'variant'
    ) {
        return (
            item.rows || []
        ).reduce(
            (sum, row) =>
                sum +
                baseQuantity(
                    row.quantity,
                    item.conversion_factor
                ),
            0
        )
    }

    return 0
}

/*
|--------------------------------------------------------------------------
| COST PRICE
|--------------------------------------------------------------------------
*/

const itemCostPrice = (
    item
) => {
    if (
        item.type === 'simple'
    ) {
        return Number(
            item.cost_price || 0
        )
    }

    const rows =
        item.type === 'variant'
            ? item.rows || []
            : item.imeis || []

    if (rows.length) {
        const prices =
            rows.map(
                r =>
                    Number(
                        r.cost_price ||
                        0
                    )
            )

        return prices.every(
            p =>
                p ===
                prices[0]
        )
            ? prices[0]
            : null
    }

    return Number(
        item.cost_price || 0
    )
}

/*
|--------------------------------------------------------------------------
| SELL PRICE
|--------------------------------------------------------------------------
*/

const itemSellPrice = (
    item
) => {
    if (
        item.type === 'simple'
    ) {
        return Number(
            item.sell_price || 0
        )
    }

    const rows =
        item.type === 'variant'
            ? item.rows || []
            : item.imeis || []

    if (rows.length) {
        const prices =
            rows.map(
                r =>
                    Number(
                        r.sell_price ||
                        0
                    )
            )

        return prices.every(
            p =>
                p ===
                prices[0]
        )
            ? prices[0]
            : null
    }

    return Number(
        item.sell_price || 0
    )
}

/*
|--------------------------------------------------------------------------
| TOTAL QUANTITY
|--------------------------------------------------------------------------
*/

const totalQuantity =
    computed(() =>
        selectedItems.value.reduce(
            (total, item) =>
                total +
                itemQuantity(item),
            0
        )
    )

/*
|--------------------------------------------------------------------------
| TOTAL
|--------------------------------------------------------------------------
*/

const total = computed(() =>
    selectedItems.value.reduce(
        (total, item) => {
            if (
                item.type ===
                'simple'
            ) {
                return (
                    total +
                    Number(
                        baseQuantity(
                            item.quantity,
                            item.conversion_factor
                        )
                    ) *
                    Number(
                        item.cost_price || 0
                    )
                )
            }

            const rows =
                item.rows || []

            const imeis =
                item.imeis || []

            const variantTotal =
                rows.reduce(
                    (
                        sum,
                        row
                    ) =>
                        sum +
                        baseQuantity(
                            row.quantity,
                            item.conversion_factor
                        ) *
                        Number(
                            row.cost_price ||
                            0
                        ),
                    0
                )

            const imeiTotal =
                imeis.reduce(
                    (
                        sum,
                        row
                    ) =>
                        sum +
                        Number(
                            row.cost_price ||
                            0
                        ),
                    0
                )

            return (
                total +
                variantTotal +
                imeiTotal
            )
        },
        0
    )
)

/*
|--------------------------------------------------------------------------
| IMPORT FILE
|--------------------------------------------------------------------------
*/

const importFile = () => {
    alert(
        'Chức năng đang phát triển'
    )
}

/*
|--------------------------------------------------------------------------
| SAVE
|--------------------------------------------------------------------------
*/

const canConfirm = computed(
    () =>
        selectedSupplier.value &&
        selectedItems.value.length > 0
)

const confirm = () => {
    if (!selectedSupplier.value) {
        return
    }

    if (!selectedItems.value.length) {
        return
    }

    form.supplier_id = selectedSupplier.value.id

    form.items = selectedItems.value.flatMap(item => {
        // Sản phẩm thường
        if (item.type === 'simple') {
            return [{
                product_id: item.product_id,
                variant_id: null,
                unit_id: item.unit_id ?? null,
                unit_name: item.unit_name ?? itemUnit(item),
                import_quantity: Number(item.quantity || 0),
                conversion_factor: Number(item.conversion_factor || 1),
                quantity: baseQuantity(item.quantity, item.conversion_factor),
                cost_price: Number(item.cost_price || 0),
                sell_price: Number(item.sell_price || 0),
                imeis: [],
            }]
        }

        // Sản phẩm có biến thể
        if (item.type === 'variant') {
            return (item.rows || [])
                .filter(row => Number(row.quantity || 0) > 0)
                .map(row => ({
                    product_id: item.product_id,
                    variant_id: row.variant_id ?? null,
                    unit_id: item.unit_id ?? null,
                    unit_name: item.unit_name ?? itemUnit(item),
                    import_quantity: Number(row.quantity || 0),
                    conversion_factor: Number(item.conversion_factor || 1),
                    quantity: baseQuantity(row.quantity, item.conversion_factor),
                    cost_price: Number(row.cost_price || 0),
                    sell_price: Number(row.sell_price || 0),
                    imeis: [],
                }))
        }

        // Sản phẩm IMEI
        if (item.type === 'imei') {
            return (item.imeis || []).map(imei => ({
                product_id: item.product_id,
                variant_id: null,
                unit_id: item.unit_id ?? null,
                unit_name: item.unit_name ?? itemUnit(item),
                import_quantity: 1,
                conversion_factor: 1,
                quantity: 1,
                cost_price: Number(imei.cost_price || item.cost_price || 0),
                sell_price: Number(imei.sell_price || item.sell_price || 0),
                imeis: [imei],
            }))
        }

        // Sản phẩm biến thể + IMEI
        if (item.type === 'imei_variant') {
            return (item.imeis || []).map(imei => ({
                product_id: item.product_id,
                variant_id: imei.variant_id ?? null,
                unit_id: item.unit_id ?? null,
                unit_name: item.unit_name ?? itemUnit(item),
                import_quantity: 1,
                conversion_factor: 1,
                quantity: 1,
                cost_price: Number(imei.cost_price || item.cost_price || 0),
                sell_price: Number(imei.sell_price || item.sell_price || 0),
                imeis: [imei],
            }))
        }

        return []
    })

    if (!form.items.length) {
        return
    }

    form.post('/stock-import', {
        preserveScroll: true,

        onSuccess: () => {
            emit('created')
        },

        onError: (errors) => {
            console.error('Lỗi validation phiếu nhập:', errors)
        },
    })
}
</script>

<template>
    <BaseModal
        title="Tạo đơn nhập"
        size="xl"
        @close="emit('close')"
    >
        <div
            class="flex min-h-0 flex-col gap-4"
        >

            <!-- NHÀ CUNG CẤP -->
            <SupplierSection
                :supplier="selectedSupplier"
                :date="form.import_date"
                :error="form.errors.supplier_id"
                @selected="selectSupplier"
                @update:date="form.import_date = $event"
            />

            <div
                v-if="formErrorMessages.length"
                class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
                <div
                    v-for="message in formErrorMessages"
                    :key="message"
                    class="leading-6"
                >
                    {{ message }}
                </div>
            </div>

            <!-- TÌM SẢN PHẨM -->
            <div class="relative">

                <div
                    class="flex items-stretch gap-3"
                >
                    <div
                        class="relative flex-1"
                    >
                        <FloatingInput
                            v-model="keyword"
                            type="text"
                            label="Tìm tên sản phẩm, SKU hoặc barcode"
                        />

                        <div
                            class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center gap-1.5 text-slate-400"
                        >
                            <button
                                v-if="keyword"
                                type="button"
                                class="hover:text-slate-600 p-1"
                                @click="
                                    keyword = ''
                                "
                            >
                                <X
                                    class="h-4 w-4"
                                />
                            </button>

                            <Search
                                class="h-5 w-5"
                            />
                        </div>
                    </div>

                    <button
                        type="button"
                        @click="importFile"
                        class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-slate-800 px-4 text-sm font-semibold text-white shadow-sm hover:bg-slate-900 transition-colors"
                    >
                        <FileUp
                            class="h-4 w-4"
                        />

                        <span>
                            Nhập file
                        </span>
                    </button>
                </div>

                <!-- DROPDOWN PRODUCT -->
                <div
                    v-if="showResults"
                    class="absolute left-0 right-0 top-full z-50 mt-1 max-h-80 overflow-auto rounded-xl border border-slate-200 bg-white shadow-2xl"
                >
                    <div
                        v-if="searching"
                        class="px-4 py-4 text-center text-sm text-slate-500"
                    >
                        Đang tìm sản phẩm...
                    </div>

                    <button
                        v-for="product in searchResults"
                        :key="product.id"
                        type="button"
                        class="flex w-full items-center gap-3 border-b border-slate-100 px-4 py-3 text-left hover:bg-slate-50"
                        @click="
                            selectProduct(
                                product
                            )
                        "
                    >
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-100"
                        >
                            <img
                                v-if="
                                    product.image_url
                                "
                                :src="
                                    product.image_url
                                "
                                class="h-full w-full object-cover"
                            />

                            <Package
                                v-else
                                class="h-5 w-5 text-slate-400"
                            />
                        </div>

                        <div
                            class="min-w-0 flex-1"
                        >
                            <div
                                class="truncate text-sm font-semibold text-slate-900"
                            >
                                {{
                                    product.name
                                }}
                            </div>

                            <div
                                class="mt-0.5 flex gap-3 text-xs text-slate-500"
                            >
                                <span>
                                    SKU:
                                    {{
                                        product.sku ||
                                        '—'
                                    }}
                                </span>

                                <span>
                                    Tồn:
                                    {{
                                        product.stock ??
                                        0
                                    }}
                                </span>
                            </div>
                        </div>

                        <div
                            class="text-sm font-bold text-slate-900"
                        >
                            {{
                                formatPrice(
                                    product.cost_price ??
                                    product.price ??
                                    0
                                )
                            }}
                            đ
                        </div>
                    </button>

                    <div
                        v-if="
                            !searching &&
                            !searchResults.length
                        "
                        class="px-4 py-6 text-center text-sm text-slate-400"
                    >
                        Không tìm thấy sản phẩm
                    </div>
                </div>
            </div>

            <!-- DANH SÁCH -->
            <div
                class="overflow-hidden rounded-xl border border-slate-200 bg-white"
            >
                <div
                    class="flex items-center justify-between border-b border-slate-200 px-4 py-3"
                >
                    <div
                        class="font-semibold text-slate-900"
                    >
                        Danh sách sản phẩm
                    </div>

                    <div
                        class="text-sm font-semibold text-blue-600"
                    >
                        {{
                            totalQuantity
                        }}
                        sản phẩm
                    </div>
                </div>

                <div
                    v-if="
                        !selectedItems.length
                    "
                    class="py-12 text-center text-sm text-slate-400"
                >
                    Chưa có sản phẩm nào
                </div>

                <div
                    v-else
                    class="overflow-x-auto"
                >
                    <table
                        class="w-full min-w-[750px] text-sm"
                    >
                        <thead>
                            <tr
                                class="border-b bg-slate-50/70 text-xs font-semibold text-slate-500"
                            >
                                <th
                                    class="px-4 py-3 text-left"
                                >
                                    Sản phẩm
                                </th>

                                <th
                                    class="w-24 px-3 py-3 text-center"
                                >
                                    Đơn vị
                                </th>

                                <th
                                    class="w-28 px-3 py-3 text-center"
                                >
                                    Số lượng
                                </th>

                                <th
                                    class="w-36 px-3 py-3 text-right"
                                >
                                    Giá nhập
                                </th>

                                <th
                                    class="w-36 px-3 py-3 text-right"
                                >
                                    Giá bán
                                </th>

                                <th
                                    class="w-24 px-3 py-3 text-center"
                                >
                                    Thao tác
                                </th>
                            </tr>
                        </thead>

                        <tbody
                            class="divide-y divide-slate-100"
                        >
                            <tr
                                v-for="(
                                    item,
                                    itemIndex
                                ) in selectedItems"
                                :key="`${item.type}-${item.product_id}`"
                                class="hover:bg-slate-50/50"
                            >
                                <td
                                    class="px-4 py-3"
                                >
                                    <div
                                        class="flex min-w-0 items-center gap-2.5"
                                    >
                                        <Package
                                            class="h-5 w-5 shrink-0 text-blue-600"
                                        />

                                        <div
                                            class="min-w-0 flex-1"
                                        >
                                            <div
                                                class="truncate font-semibold text-slate-900"
                                            >
                                                {{
                                                    item.name
                                                }}
                                            </div>

                                            <div
                                                class="mt-1 flex flex-wrap items-center gap-1.5"
                                            >
                                                <span
                                                    v-if="
                                                        item.type ===
                                                            'variant' ||
                                                        item.type ===
                                                            'imei_variant'
                                                    "
                                                    class="inline-flex items-center rounded-md bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10"
                                                >
                                                    {{
                                                        (
                                                            item.rows ||
                                                            []
                                                        ).length
                                                    }}
                                                    biến thể
                                                </span>

                                                <span
                                                    v-if="
                                                        item.type ===
                                                            'imei' ||
                                                        item.type ===
                                                            'imei_variant'
                                                    "
                                                    class="inline-flex items-center rounded-md bg-orange-50 px-2 py-0.5 text-[11px] font-medium text-orange-700 ring-1 ring-inset ring-orange-700/10"
                                                >
                                                    {{
                                                        (
                                                            item.imeis ||
                                                            []
                                                        ).length
                                                    }}
                                                    IMEI
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td
                                    class="px-3 py-3 text-center text-slate-600"
                                >
                                    <div class="font-medium">
                                        {{
                                            itemUnit(
                                                item
                                            )
                                        }}
                                    </div>

                                    <div
                                        v-if="
                                            Number(
                                                item.conversion_factor ||
                                                1
                                            ) > 1
                                        "
                                        class="mt-0.5 text-[11px] text-slate-400"
                                    >
                                        x{{
                                            item.conversion_factor
                                        }}
                                    </div>
                                </td>

                                <td
                                    class="px-3 py-3 text-center font-semibold text-slate-900"
                                >
                                    {{
                                        itemQuantity(
                                            item
                                        )
                                    }}
                                </td>

                                <td
                                    class="px-3 py-3 text-right"
                                >
                                    <span
                                        v-if="
                                            itemCostPrice(
                                                item
                                            ) !==
                                            null
                                        "
                                        class="font-medium text-slate-700"
                                    >
                                        {{
                                            formatPrice(
                                                itemCostPrice(
                                                    item
                                                )
                                            )
                                        }}
                                    </span>

                                    <span
                                        v-else
                                        class="text-xs text-slate-400"
                                    >
                                        Nhiều giá
                                    </span>
                                </td>

                                <td
                                    class="px-3 py-3 text-right"
                                >
                                    <span
                                        v-if="
                                            itemSellPrice(
                                                item
                                            ) !==
                                            null
                                        "
                                        class="font-medium text-slate-700"
                                    >
                                        {{
                                            formatPrice(
                                                itemSellPrice(
                                                    item
                                                )
                                            )
                                        }}
                                    </span>

                                    <span
                                        v-else
                                        class="text-xs text-slate-400"
                                    >
                                        Nhiều giá
                                    </span>
                                </td>

                                <td
                                    class="px-3 py-3 text-center"
                                >
                                    <div
                                        class="flex items-center justify-center gap-1"
                                    >
                                        <button
                                            type="button"
                                            @click="
                                                productModal = {
                                                    open: true,
                                                    item,
                                                }
                                            "
                                            title="Chỉnh sửa cấu hình"
                                            class="rounded-lg p-1.5 text-blue-600 hover:bg-blue-50 transition-colors"
                                        >
                                            <Pencil
                                                class="h-4 w-4"
                                            />
                                        </button>

                                        <button
                                            type="button"
                                            @click="
                                                removeItem(
                                                    itemIndex
                                                )
                                            "
                                            title="Xóa sản phẩm"
                                            class="rounded-lg p-1.5 text-red-500 hover:bg-red-50 transition-colors"
                                        >
                                            <Trash2
                                                class="h-4 w-4"
                                            />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- TOTAL -->
                <div
                    v-if="
                        selectedItems.length
                    "
                    class="flex items-center justify-between border-t bg-slate-50/50 px-4 py-3"
                >
                    <span
                        class="font-semibold text-slate-700"
                    >
                        Tổng tiền
                    </span>

                    <span
                        class="text-lg font-bold text-green-600"
                    >
                        {{
                            formatPrice(
                                total
                            )
                        }}
                    </span>
                </div>
            </div>
        </div>

        <!-- FOOTER -->
        <template #footer>
            <div
                class="flex items-center justify-between gap-3"
            >
                <div
                    class="text-sm text-slate-500"
                >
                    <span
                        class="font-semibold text-slate-700"
                    >
                        {{
                            selectedItems.length
                        }}
                    </span>
                    dòng /

                    <span
                        class="font-semibold text-slate-700"
                    >
                        {{
                            totalQuantity
                        }}
                    </span>
                    sản phẩm
                </div>

                <div
                    class="ml-auto flex gap-2"
                >
                    <button
                        type="button"
                        @click="
                            emit('close')
                        "
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        Hủy
                    </button>

                    <button
                        type="button"
                        @click="confirm"
                        :disabled="
                            !canConfirm
                        "
                        class="rounded-lg bg-slate-800 px-5 py-2 text-sm font-semibold text-white hover:bg-slate-900 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Lưu kho
                    </button>
                </div>
            </div>
        </template>

        <!-- MODAL CẤU HÌNH SẢN PHẨM -->
        <StockImportProductModal
            v-if="
                productModal.open &&
                productModal.item
            "
            :item="
                productModal.item
            "
            @close="
                closeProductModal
            "
            @completed="
                handleProductCompleted
            "
        />
    </BaseModal>
</template>
