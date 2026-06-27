<script setup>
import CartItemHeader from './CartItemHeader.vue'
import CartItemQuantity from './CartItemQuantity.vue'
import CartItemGiftList from './CartItemGiftList.vue'
import {useCartItem,} from '../Composables/useCartItem'
import CartItemPrice from './CartItemPrice.vue'
import CartItemNote from './CartItemNote.vue'
import CartItemPromotion from './CartItemPromotion.vue'
import { useCartGift, } from '../Composables/useCartGift'
import { useCartDiscount, } from '../Composables/useCartDiscount'


const props = defineProps({

    item: {
        type: Object,
        required: true,
    },
})


const emit = defineEmits([
    'remove',
])

const {

    toggleNote,

    togglePromotion,

} = useCartItem()

const {

    searchGiftProducts,

    selectGiftProduct,

    removeGift,

} = useCartGift()

const {

    lineTotal,

    normalizeDiscount,

} = useCartDiscount()


</script>

<template>
    <div
        class="bg-white border border-gray-200 rounded-xl px-3 py-2 mb-1 shadow-sm"
    >
        <!--Thông tin SP, và Chức năng-->
        <CartItemHeader

            :item="item"

            @toggle-note="
                toggleNote(item)
            "

            @toggle-promotion="
                togglePromotion(item)
            "

            @remove="
                emit(
                    'remove',
                    item
                )
            "
        />

        <!--Số lượng, hiện quà và tiền-->
        <div class="flex justify-between items-start mt-2">
            <div class="flex items-center gap-3">
                <CartItemQuantity
                    :item="item"
                    @remove="emit('remove', item)"
                />

                <!--Hiện quà tặng-->
                <CartItemGiftList
                    v-if="item.gifts && item.gifts.length"
                    :gifts="item.gifts"
                    @remove="removeGift(item, $event)"
                />
            </div>

            <!--giảm giá-->
            <CartItemPrice
                :item="item"
            />
                
        </div>

        <!--nơi hiện Ghi chú-->
        <CartItemNote
            :item="item"
        />
        <!--nơi nhập quà tặng và giảm giá-->
        <CartItemPromotion
            v-if="item.showPromotion"
            :item="item"
            :searchGiftProducts="searchGiftProducts"
            :selectGiftProduct="selectGiftProduct"
            :normalizeDiscount="normalizeDiscount"
        />
        

    </div>
</template>