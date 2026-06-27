<script setup>

import FloatingInput
from '@/Components/UI/FloatingInput.vue'

defineProps({

    item: Object,

    searchGiftProducts: Function,

    selectGiftProduct: Function,
})

</script>

<template>

    <div class="flex-[2] relative">

        <FloatingInput
            v-model="item.gift_keyword"
            @input="
                searchGiftProducts(
                    item
                )
            "
            label="Tìm quà tặng"
        />

        <div
            v-if="
                item.gift_results &&
                item.gift_results.length
            "
            class="
                absolute
                z-50
                bg-white
                border
                rounded-lg
                shadow-lg
                w-full
                max-h-60
                overflow-y-auto
            "
        >

            <div

                v-for="
                    product
                    in
                    item.gift_results
                "

                :key="
                    product.id
                "

                @click="
                    selectGiftProduct(
                        item,
                        product
                    )
                "

                class="
                    px-3
                    py-2
                    cursor-pointer
                    hover:bg-blue-50
                    border-b
                "
            >

                <div
                    class="font-medium"
                >
                    {{ product.name }}
                </div>

                <div
                    class="
                        text-xs
                        text-gray-500
                    "
                >
                    {{ product.sku }}
                </div>

            </div>

        </div>

    </div>

</template>