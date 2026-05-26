<script setup>
import axios from 'axios'
import { ref } from 'vue'

const emit = defineEmits(['selected'])

const products = ref([])
const selectedIndex = ref(-1)
const keyword = ref('')

let timeout = null

const search = () => {
    clearTimeout(timeout)

    timeout = setTimeout(async () => {

        if (!keyword.value) {
            products.value = []
            selectedIndex.value = -1
            return
        }

        // scan barcode
        if (keyword.value.length >= 8) {
            try {
                const scanRes = await axios.post('/api/pos/scan', {
                    code: keyword.value
                })

                if (scanRes.data.data) {
                    select(scanRes.data.data)
                    return
                }
            } catch (e) {}
        }

        // search products
        const res = await axios.get('/api/products/search', {
            params: { q: keyword.value }
        })

        products.value = res.data.data ?? res.data
        selectedIndex.value = 0

    }, 300)
}

const handleKeydown = (event) => {

    if (event.key === 'ArrowDown') {
        event.preventDefault()
        if (selectedIndex.value < products.value.length - 1) {
            selectedIndex.value++
        }
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault()
        if (selectedIndex.value > 0) {
            selectedIndex.value--
        }
    }

    if (event.key === 'Enter') {
        event.preventDefault()

        const product = products.value[selectedIndex.value]
        if (product) select(product)
    }
}

const select = (product) => {
    emit('selected', product)
    keyword.value = ''
    products.value = []
    selectedIndex.value = -1
}

const format = (number) =>
    Number(number).toLocaleString('vi-VN')
</script>


<template>
    <div class="relative">

        <!-- INPUT -->
        <input
            v-model="keyword"
            @input="search"
            @keydown="handleKeydown"
            type="text"
            placeholder="Quét mã / tên sản phẩm"
            class="w-full border rounded p-3 text-lg"
        />

        <!-- RESULTS -->
        <div v-if="products.length"
            class="absolute w-full bg-white border rounded mt-1 shadow z-50">

            <div
                v-for="(product, index) in products"
                :key="product.id"
                @click="select(product)"
                :class="[
                    'p-2 cursor-pointer',
                    index === selectedIndex
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