<script setup>

import FloatingInput
from '@/Components/UI/FloatingInput.vue'
import { findProductByScannedCode } from '@/Modules/POS/Product/Services/productBarcodeLookup'
import { toast } from 'vue-sonner'
import BarcodeCameraButton from '@/Components/BarcodeCameraButton.vue'

const props = defineProps({

    item: Object,

    searchGiftProducts: Function,

    selectGiftProduct: Function,
})

const scanGiftProduct = async (item) => {
    const code = String(item.gift_keyword || '').trim()
    if (!code) return

    try {
        const match = await findProductByScannedCode(code)
        if (match && match.product.product_type !== 'imei') {
            props.selectGiftProduct(item, match.product)
        }
    } catch {
        toast.error('Không tìm thấy sản phẩm quà tặng theo mã vừa quét')
    }
}

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
            @keydown.enter.prevent="scanGiftProduct(item)"
            label="Tìm quà tặng"
            class="pr-12"
        />
        <BarcodeCameraButton class="absolute right-1 top-1/2 z-10 -translate-y-1/2 !h-8 !w-8" @scanned="(code) => { item.gift_keyword = code; scanGiftProduct(item) }" />

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
