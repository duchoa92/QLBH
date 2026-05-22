

<script setup>
import axios from 'axios'
import { ref } from 'vue'

const emit = defineEmits(['selected'])

const keyword = ref('')
const results = ref([])

let timeout = null

const search = () => {

    clearTimeout(timeout)

    timeout = setTimeout(async () => {

        if (!keyword.value) {

            results.value = []

            return
        }

        const res = await axios.get(
            '/api/products/search',
            {
                params: {
                    q: keyword.value
                }
            }
        )

        results.value = res.data

        // barcode exact match → auto select
        const exactBarcode = res.data.find(
            item => item.barcode === keyword.value
        )

        if (exactBarcode) {

            select(exactBarcode)
        }

    }, 300)
}

const select = (product) => {

    emit('selected', product)

    keyword.value = ''

    results.value = []
}

const format = (number) => {

    return Number(number)
        .toLocaleString('vi-VN')
}
</script>


<template>
    <div class="relative">

        <!-- INPUT -->
        <input
            v-model="keyword"
            @input="search"
            type="text"
            placeholder="Quét mã / tên sản phẩm"
            class="w-full border rounded p-3 text-lg"
        />

        <!-- RESULTS -->
        <div
            v-if="results.length"
            class="absolute w-full bg-white border rounded mt-1 shadow z-50"
        >

            <div
                v-for="product in results"
                :key="product.id"
                @click="select(product)"
                class="p-3 hover:bg-gray-100 cursor-pointer border-b"
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