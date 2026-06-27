<script setup>
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import CartPromotionGift from './CartPromotionGift.vue'
import CartPromotionDiscount from './CartPromotionDiscount.vue'


const props = defineProps({

    item: {
        type: Object,
        required: true,
    },

    searchGiftProducts: {
        type: Function,
        required: true,
    },

    selectGiftProduct: {
        type: Function,
        required: true,
    },

    normalizeDiscount: {
        type: Function,
        required: true,
    },
})

</script>

<template>
            <div v-if="item.showPromotion"
            @click.stop
            class="promotion-panel mt-3"
        >

            <!--Phần nhập quà tặng và giảm giá-->
            <div class="flex gap-2">
                <CartPromotionGift
                    :item="item"
                    :searchGiftProducts="
                        searchGiftProducts
                    "
                    :selectGiftProduct="
                        selectGiftProduct
                    "
                />
                <CartPromotionDiscount
                    :item="item"
                    :normalizeDiscount="
                        normalizeDiscount
                    "
                />
            </div>
        </div>
</template>