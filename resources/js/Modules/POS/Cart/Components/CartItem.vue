<script setup>
import { computed } from 'vue'
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

const warrantyDays = computed({
    get: () => {
        if (props.item.customer_warranty_days === undefined && props.item.warranty_days === undefined) return ''
        const value = props.item.customer_warranty_days ?? props.item.warranty_days
        return value === null ? '' : Number(value)
    },
    set: (value) => {
        props.item.customer_warranty_days = value === '' ? null : Math.max(0, Math.min(3650, Number(value || 0)))
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

        <div v-if="item.imei_id || item.imei || item.serial" class="mt-2 flex flex-wrap items-center gap-2 rounded-lg border border-emerald-100 bg-emerald-50/60 px-2 py-1.5">
            <label :for="`pos-warranty-${item.imei_id || item.imei || item.serial}`" class="text-[11px] font-semibold text-emerald-800">Bảo hành khách</label>
            <input
                :id="`pos-warranty-${item.imei_id || item.imei || item.serial}`"
                v-model.number="warrantyDays"
                type="number"
                min="0"
                max="3650"
                step="1"
                class="h-7 w-20 rounded-md border-emerald-200 bg-white px-2 text-right text-xs font-semibold text-slate-800 focus:border-emerald-500 focus:ring-emerald-500"
                aria-label="Thời hạn bảo hành cho khách tính theo ngày"
            />
            <span class="text-[11px] text-emerald-800">ngày</span>
            <span class="text-[10px] text-slate-500">0 = không BH · để trống = theo cấu hình SP<span v-if="item.warranty_days"> ({{ item.warranty_days }} ngày)</span></span>
        </div>

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
