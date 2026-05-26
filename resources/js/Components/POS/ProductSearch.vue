<script setup>
import axios from 'axios'
import { ref } from 'vue'

import { useAutocompleteKeyboard } from '@/Composables/useAutocompleteKeyboard'
import { useDebounceSearch } from '@/Composables/useDebounceSearch'

const emit = defineEmits(['selected'])

/*
|--------------------------------------------------------------------------
| ACTION FIRST (IMPORTANT - FIX RUNTIME ORDER)
|--------------------------------------------------------------------------
*/
const select = (product) => {
    emit('selected', product)

    keyword.value = ''
    products.value = []
    reset()
}

/*
|--------------------------------------------------------------------------
| SEARCH (SOURCE OF TRUTH)
|--------------------------------------------------------------------------
*/
const {
    keyword,
    results: products,
    search,
} = useDebounceSearch(async (q) => {

    if (!q) return []

    /*
    |------------------------------------------
    | BARCODE SCAN (LOCK SAFE)
    |------------------------------------------
    */
    if (q.length >= 8) {
        try {
            const scanRes = await axios.post('/api/pos/scan', {
                code: q
            })

            if (scanRes.data?.data) {
                select(scanRes.data.data)
                return []
            }
        } catch (e) {}
    }

    /*
    |------------------------------------------
    | PRODUCT SEARCH
    |------------------------------------------
    */
    const res = await axios.get('/api/products/search', {
        params: { q }
    })

    return res.data?.data ?? res.data ?? []

}, 150)

/*
|--------------------------------------------------------------------------
| KEYBOARD ENGINE
|--------------------------------------------------------------------------
*/
const {
    activeIndex,
    itemRefs,
    setItemRef,
    onKeyDown,
    setActive,
    reset,
} = useAutocompleteKeyboard(products, (product) => {
    select(product)
})

/*
|--------------------------------------------------------------------------
| FORMAT PRICE
|--------------------------------------------------------------------------
*/
const format = (number) =>
    Number(number).toLocaleString('vi-VN')
</script>

<template>
    <div class="relative">

        <!-- INPUT -->
        <input
            v-model="keyword"
            @input="search"
            @keydown="onKeyDown"
            type="text"
            placeholder="Quét mã / tên sản phẩm"
            class="w-full border rounded p-3 text-lg"
        />

        <!-- RESULTS -->
        <div
            v-if="products.length"
            class="absolute w-full bg-white border rounded mt-1 shadow z-50"
        >
            <div
                v-for="(product, index) in products"
                :key="product.id"
                :ref="el => setItemRef(el, index)"
                @mouseenter="setActive(index)"
                @click="select(product)"
                :class="[
                    'p-2 cursor-pointer',
                    index === activeIndex
                        ? 'bg-blue-100'
                        : 'hover:bg-gray-100'
                ]"
            >
                <div class="font-medium">
                    {{ product.name }}
                </div>

                <div class="text-sm text-gray-500">
                    {{ format(product.sell_price) }}
                </div>
            </div>
        </div>

    </div>
</template><script setup>
import axios from 'axios'
import { ref } from 'vue'

import { useAutocompleteKeyboard } from '@/Composables/useAutocompleteKeyboard'
import { useDebounceSearch } from '@/Composables/useDebounceSearch'

const emit = defineEmits(['selected'])

/*
|--------------------------------------------------------------------------
| ACTION FIRST (IMPORTANT - FIX RUNTIME ORDER)
|--------------------------------------------------------------------------
*/
const select = (product) => {
    emit('selected', product)

    keyword.value = ''
    products.value = []
    reset()
}

/*
|--------------------------------------------------------------------------
| SEARCH (SOURCE OF TRUTH)
|--------------------------------------------------------------------------
*/
const {
    keyword,
    results: products,
    search,
} = useDebounceSearch(async (q) => {

    if (!q) return []

    /*
    |------------------------------------------
    | BARCODE SCAN (LOCK SAFE)
    |------------------------------------------
    */
    if (q.length >= 8) {
        try {
            const scanRes = await axios.post('/api/pos/scan', {
                code: q
            })

            if (scanRes.data?.data) {
                select(scanRes.data.data)
                return []
            }
        } catch (e) {}
    }

    /*
    |------------------------------------------
    | PRODUCT SEARCH
    |------------------------------------------
    */
    const res = await axios.get('/api/products/search', {
        params: { q }
    })

    return res.data?.data ?? res.data ?? []

}, 150)

/*
|--------------------------------------------------------------------------
| KEYBOARD ENGINE
|--------------------------------------------------------------------------
*/
const {
    activeIndex,
    itemRefs,
    setItemRef,
    onKeyDown,
    setActive,
    reset,
} = useAutocompleteKeyboard(products, (product) => {
    select(product)
})

/*
|--------------------------------------------------------------------------
| FORMAT PRICE
|--------------------------------------------------------------------------
*/
const format = (number) =>
    Number(number).toLocaleString('vi-VN')
</script>

<template>
    <div class="relative">

        <!-- INPUT -->
        <input
            v-model="keyword"
            @input="search"
            @keydown="onKeyDown"
            type="text"
            placeholder="Quét mã / tên sản phẩm"
            class="w-full border rounded p-3 text-lg"
        />

        <!-- RESULTS -->
        <div
            v-if="products.length"
            class="absolute w-full bg-white border rounded mt-1 shadow z-50"
        >
            <div
                v-for="(product, index) in products"
                :key="product.id"
                :ref="el => setItemRef(el, index)"
                @mouseenter="setActive(index)"
                @click="select(product)"
                :class="[
                    'p-2 cursor-pointer',
                    index === activeIndex
                        ? 'bg-blue-100'
                        : 'hover:bg-gray-100'
                ]"
            >
                <div class="font-medium">
                    {{ product.name }}
                </div>

                <div class="text-sm text-gray-500">
                    {{ format(product.sell_price) }}
                </div>
            </div>
        </div>

    </div>
</template>