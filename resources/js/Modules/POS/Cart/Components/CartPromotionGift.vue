<script setup>

import FloatingInput
from '@/Components/UI/FloatingInput.vue'

const props = defineProps({

    item: Object,

    searchGiftProducts: Function,

    selectGiftProduct: Function,
})

</script>

<template>

    <div class="relative flex-[2]">

        <FloatingInput
            v-model="item.gift_keyword"
            @input="
                searchGiftProducts(
                    item
                )
            "
            label="Tìm quà tặng hoặc IMEI"
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

            <button

                v-for="
                    product
                    in
                    item.gift_results
                "

                :key="
                    product.id
                "

                type="button"
                @click="
                    selectGiftProduct(
                        item,
                        product
                    )
                "

                class="
                    block
                    text-left
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
                    <span v-if="product.product_type === 'imei' || product.manage_stock_by_serial" class="ml-1 rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700">
                        IMEI
                    </span>
                </div>

                <div
                    class="
                        text-xs
                        text-gray-500
                    "
                >
                    {{ product.sku }}
                </div>

            </button>

        </div>

    </div>

</template>
