<script setup>
import { useProductSearch } from '@/Modules/POS/Product/Composables/useProductSearch'
import { useToast } from '@/Composables/useToast'
import { productService } from '@/Modules/POS/Product/Services/productService'

const emit = defineEmits([
    'selected',
    'product-scanned',
])

const {
    error: errorToast,
} = useToast()

const {

    keyword,

    categoryId,

    products,

    categories,

    loading,

    loadProducts,

} = useProductSearch()

const selectProduct = (
    product
) => {

    if (
        product.product_type === 'imei'
    ) {

        error(
            'Sản phẩm này phải quét IMEI'
        )

        return
    }

    emit(
        'selected',
        product
    )
}

const scanImei = async () => {

    const value = keyword.value.trim()

    if (!value) {
        return
    }

    try {

        const result =
            await productService.scan(
                value
            )

        const product = {

            id:
                result.data.id,

            name:
                result.data.name,

            sell_price:
                result.data.sell_price
                ??
                result.data.price,

            product_type:
                'imei',

            imei_id:
                result.data.imei_id,

            imei:
                result.data.imei,
        }


        emit(
            'selected',
            product
        )


        keyword.value = ''


    } catch (error) {

        console.error(error)
        
        const message =
            error.response?.data?.message
            ??
            'Không tìm thấy sản phẩm'


        errorToast(
            message
        )


        keyword.value = ''
    }
}
const formatPrice = (value) => {

    return Number(value).toLocaleString('vi-VN')
}

const refreshProducts = () => {

    loadProducts()
}
</script>

<template>

    <div class="flex min-h-0 flex-1 flex-col">

        <div class="border-b border-slate-200 bg-slate-50 p-4">

            <div class="grid gap-3 md:grid-cols-[1fr_220px_112px]">

                <div>

                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Tìm nhanh / Barcode / IMEI
                    </label>

                    <input
                        v-model="keyword"
                        @keyup.enter="scanImei"
                        type="text"
                        class="h-12 w-full rounded-md border-slate-300 bg-white px-4 text-base font-medium text-slate-900 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        placeholder="Nhập tên sản phẩm, SKU, barcode hoặc IMEI"
                    />

                </div>

                <div>

                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Danh mục
                    </label>

                    <select
                        v-model="categoryId"
                        class="h-12 w-full rounded-md border-slate-300 bg-white px-3 text-sm font-medium text-slate-800 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    >
                        <option value="">
                            Tất cả
                        </option>

                        <option
                            v-for="category in categories"
                            :key="category.id"
                            :value="category.id"
                        >
                            {{ category.name }}
                        </option>

                    </select>

                </div>

                <div class="flex items-end">

                    <button
                        type="button"
                        @click="refreshProducts"
                        class="h-12 w-full rounded-md border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-100"
                    >
                        Tải lại
                    </button>

                </div>

            </div>

        </div>

        <div class="min-h-0 flex-1 overflow-auto p-4">

            <div
                v-if="loading"
                class="flex h-full items-center justify-center rounded-lg border border-dashed border-slate-300 bg-slate-50 text-sm font-semibold text-slate-500"
            >
                Đang tải sản phẩm...
            </div>

            <div
                v-else-if="products.length"
                class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5"
            >

                <button
                    v-for="product in products"
                    :key="product.id"
                    type="button"
                    class="group flex min-h-[148px] flex-col rounded-lg border border-slate-200 bg-white p-3 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-md"
                    @click="selectProduct(product)"
                >

                    <div class="flex items-start justify-between gap-2">

                        <div class="min-w-0">

                            <div class="h-10 overflow-hidden text-sm font-bold leading-5 text-slate-900">
                                {{ product.name }}
                            </div>

                            <div class="mt-1 truncate text-xs text-slate-500">
                                SKU: {{ product.sku }}
                            </div>

                        </div>

                        <span
                            v-if="product.product_type === 'imei'"
                            class="shrink-0 rounded bg-rose-50 px-2 py-1 text-[11px] font-bold text-rose-600"
                        >
                            IMEI
                        </span>

                    </div>

                    <div class="mt-auto pt-4">

                        <div class="text-lg font-black text-rose-600">
                            {{ formatPrice(product.price) }}
                        </div>

                        <div class="mt-2 flex items-center justify-between text-xs">

                            <span
                                class="rounded bg-slate-100 px-2 py-1 font-semibold text-slate-600"
                                :class="{
                                    'bg-rose-50 text-rose-600':
                                        product.stock <= 0
                                }"
                            >
                                Tồn: {{ product.stock }}
                            </span>

                            <span class="text-slate-500">
                                Đã bán: {{ product.sold_count }}
                            </span>

                        </div>

                    </div>

                </button>

            </div>

            <div
                v-else
                class="flex h-full items-center justify-center rounded-lg border border-dashed border-slate-300 bg-slate-50 text-sm font-semibold text-slate-500"
            >
                Không tìm thấy sản phẩm
            </div>

        </div>

    </div>

</template>
