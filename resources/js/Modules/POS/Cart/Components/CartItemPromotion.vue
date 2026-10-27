<script setup>
import { Gift, Percent } from 'lucide-vue-next'
import CartPromotionGift from './CartPromotionGift.vue'
import CartPromotionDiscount from './CartPromotionDiscount.vue'

defineProps({
    item: { type: Object, required: true },
    searchGiftProducts: { type: Function, required: true },
    selectGiftProduct: { type: Function, required: true },
    normalizeDiscount: { type: Function, required: true },
})
</script>

<template>
    <div v-if="item.showPromotion" @click.stop class="promotion-panel mt-3 space-y-4">
        <section class="space-y-2">
            <div class="flex items-center gap-2 text-xs font-bold text-slate-800">
                <Gift class="h-4 w-4 text-amber-600" />
                Thêm quà tặng
            </div>
            <CartPromotionGift :item="item" :search-gift-products="searchGiftProducts" :select-gift-product="selectGiftProduct" />
        </section>

        <section class="space-y-2 border-t border-slate-200 pt-3">
            <div class="flex items-center gap-2 text-xs font-bold text-slate-800">
                <Percent class="h-4 w-4 text-blue-600" />
                Giảm giá sản phẩm
            </div>
            <CartPromotionDiscount :item="item" :normalize-discount="normalizeDiscount" />
            <p class="text-[10px] leading-4 text-slate-500">Nhập số tiền giảm hoặc đổi sang phần trăm bằng nút bên cạnh.</p>
        </section>
    </div>
</template>
