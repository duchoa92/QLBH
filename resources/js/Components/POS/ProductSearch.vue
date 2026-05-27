
<script setup>
import axios from 'axios'

import {
    ref,
    watch,
    onMounted,
} from 'vue'

const emit = defineEmits([
    'selected',
])

/*
|--------------------------------------------------
| STATE
|--------------------------------------------------
*/
const keyword = ref('')

const categoryId = ref('')

const products = ref([])

const categories = ref([])

/*
|--------------------------------------------------
| LOAD PRODUCTS
|--------------------------------------------------
*/
const loadProducts = async () => {

    const res = await axios.get('/api/products', {

        params: {
            keyword: keyword.value,
            category_id: categoryId.value,
        },
    })

    products.value = res.data
}

/*
|--------------------------------------------------
| LOAD CATEGORIES
|--------------------------------------------------
*/
const loadCategories = async () => {

    const res = await axios.get('/api/categories')

    categories.value = res.data
}

/*
|--------------------------------------------------
| SELECT PRODUCT
|--------------------------------------------------
*/
const select = (product) => {

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
| WATCH SEARCH
|--------------------------------------------------
*/
watch(
    [keyword, categoryId],
    () => {
        loadProducts()
    }
)

/*
|--------------------------------------------------
| INIT
|--------------------------------------------------
*/
onMounted(() => {

    loadProducts()

    loadCategories()
})
</script>

<template>
    <div>

        <!-- SEARCH -->
        <div class="mb-3">

            <input
                v-model="keyword"
                type="text"
                class="w-full border rounded p-2"
                placeholder="Tìm sản phẩm..."
            />

        </div>

        <!-- CATEGORY -->
        <div class="mb-3">

            <select
                v-model="categoryId"
                class="w-full border rounded p-2"
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
                class="border rounded p-3 cursor-pointer hover:bg-gray-100"
                @click="select(product)"
            >
                <div class="font-bold">
                    {{ product.name }}
                </div>

                <div class="text-sm text-gray-500">
                    SKU: {{ product.sku }}
                </div>

                <div class="text-red-600 font-bold mt-2">
                    {{ formatPrice(product.price) }}
                </div>

                <div class="text-xs text-gray-500 mt-1">
                    Đã bán: {{ product.sold_count }}
                </div>

                <div class="text-xs">
                    Tồn: {{ product.stock }}
                </div>

            </div>

        </div>

    </div>
</template>
