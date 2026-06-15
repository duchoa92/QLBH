<script setup>

import { ref, onMounted } from 'vue'
import { productService } from '@/Modules/POS/Product/Services/productService'

const props = defineProps({

    show: Boolean,
})

const emit = defineEmits([
    'close',
    'selected',
])

const products = ref([])

const loadProducts = async () => {

    products.value =
        await productService.search(
            '',
            ''
        )
}

onMounted(
    loadProducts
)

</script>

<template>

<div
    v-if="show"
    class="fixed inset-0 bg-black/50 z-[9999] flex items-center justify-center"
>

    <div
        class="bg-white w-[700px] rounded-xl p-4"
    >

        <div
            class="flex justify-between mb-3"
        >

            <h2 class="font-bold">
                Chọn quà tặng
            </h2>

            <button
                @click="emit('close')"
            >
                ✕
            </button>

        </div>

        <div
            class="grid grid-cols-3 gap-2 max-h-[500px] overflow-y-auto"
        >

            <button

                v-for="product in products"

                :key="product.id"

                @click="emit('selected', product)"

                class="border rounded-lg p-2 text-left hover:bg-blue-50"
            >

                <div class="font-medium">
                    {{ product.name }}
                </div>

                <div class="text-xs text-gray-500">
                    {{ product.sell_price }}
                </div>

            </button>

        </div>

    </div>

</div>

</template>