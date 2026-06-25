<script setup>
import { computed } from 'vue'
import { useProductSearch } from '@/Modules/POS/Product/Composables/useProductSearch'
import { toast } from 'vue-sonner'
import { productService } from '@/Modules/POS/Product/Services/productService'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import FloatingSelect from '@/Components/UI/FloatingSelect.vue'

const emit = defineEmits([
    'selected',
    'product-scanned',
])


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

        toast.error(
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
                
            image_url: result.data.image_url,

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


        toast.error(
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

const categoryOptions = computed(() => [

    {
        value: '',
        label: 'Tất cả',
    },

    ...categories.value.map(
        category => ({

            value: category.id,

            label: category.name,
        })
    ),
])
</script>

<template>

    <div class="flex min-h-0 flex-1 flex-col">

        <div class="border-b border-slate-200 bg-slate-50 p-4">

            <div class="grid
        gap-3

        md:grid-cols-[1fr_320px]
        lg:grid-cols-[1fr_420px]">

                <div>

                    <FloatingInput
                        v-model="keyword"
                        @keyup.enter="scanImei"
                        type="text"
                        label="Nhập tên sản phẩm, SKU, barcode hoặc IMEI"
                    />

                </div>

                <div class="flex gap-2">
                    <div class="flex-1">
                        <FloatingSelect
                            v-model="categoryId"
                            label="Danh mục"
                            :options="categoryOptions"
                            class=""
                        />
                    </div>

                    <button
                        type="button"
                        @click="refreshProducts"
                        class="w-12 h-10 shrink-0 rounded-lg bg-blue-600 text-white hover:bg-blue-500"
                    >
                        ↻
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
                    class="group overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm transition hover:shadow-md"
                    @click="selectProduct(product)"
                >

                    <!-- ẢNH -->
                    <div class="relative h-44 bg-slate-100">

                        <img
                            v-if="product.image_url"
                            :src="product.image_url"
                            class="h-full w-full object-cover"
                        >

                        <div
                            v-else
                            class="flex h-full items-center justify-center text-slate-400"
                        >
                            No Image
                        </div>

                        <!-- GIÁ ĐÈ LÊN ẢNH -->
                        <div
                            class="absolute bottom-2 left-2
                                rounded-md
                                bg-red-600
                                px-3 py-1
                                text-sm
                                font-bold
                                text-white"
                        >
                            {{ formatPrice(product.price) }}
                        </div>

                    </div>

                    <!-- TÊN -->
                    <div class="p-3">

                        <div
                            class="line-clamp-2
                                min-h-[40px]
                                text-sm
                                font-semibold"
                        >
                            {{ product.name }}
                        </div>

                        <!-- TỒN + ĐÃ BÁN -->
                        <div
                            class="mt-3
                                flex
                                items-center
                                justify-between
                                text-xs"
                        >
                            <span class="text-green-600 font-bold">
                                Tồn: {{ product.stock }}
                            </span>

                            <span class="text-slate-500">
                                Đã bán: {{ product.sold_count || 0 }}
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
