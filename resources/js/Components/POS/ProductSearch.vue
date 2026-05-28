
<script setup>
import { useProductSearch } from '@/Composables/useProductSearch'

const emit = defineEmits([
    'selected',
])

// COMPOSABLE
const {

    keyword,

    categoryId,

    products,

    categories,

} = useProductSearch()


/*
|--------------------------------------------------
| SELECT PRODUCT
|--------------------------------------------------
*/
const selectProduct = (product) => {

    emit('selected', product)
}

/*
|--------------------------------------------------
| FORMAT PRICE
|--------------------------------------------------
*/
const formatPrice = (value) => {

    return Number(value).toLocaleString('vi-VN')
}


/*
|--------------------------------------------------
| REFRESH PRODUCTS
|--------------------------------------------------
*/
const refreshProducts = () => {

    loadProducts()
}


</script>

<template>

    <div>

        <!-- SEARCH -->
        <div class="mb-3">

            <input
                v-model="keyword"
                type="text"
                class="w-full border rounded-lg p-3"
                placeholder="Tìm sản phẩm..."
            />

        </div>

        <!-- FILTER -->
        <div class="mb-4">

            <select
                v-model="categoryId"
                class="w-full border rounded-lg p-3"
            >
                <option value="">
                    Tất cả danh mục
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

        <!-- PRODUCT GRID -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">

            <div
                v-for="product in products"
                :key="product.id"
                class="border rounded-xl p-3 bg-white cursor-pointer hover:bg-gray-100 transition"
                @click="selectProduct(product)"
            >

                <!-- NAME -->
                <div class="font-semibold text-sm">

                    {{ product.name }}

                </div>

                <!-- SKU -->
                <div class="text-xs text-gray-500 mt-1">

                    SKU: {{ product.sku }}

                </div>

                <!-- PRICE -->
                <div class="text-red-600 font-bold mt-2">

                    {{ formatPrice(product.price) }}

                </div>

                <!-- STOCK -->
                <div class="text-xs mt-2">

                    Tồn kho:
                    {{ product.stock }}

                </div>

                <!-- SOLD -->
                <div class="text-xs text-gray-500">

                    Đã bán:
                    {{ product.sold_count }}

                </div>

            </div>

        </div>

    </div>

</template>
