<script setup>
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
    <div v-if="item.showPromotion" @click.stop class="promotion-panel mt-3 space-y-2">
        <div class="grid grid-cols-[minmax(0,1fr)_minmax(8.5rem,10rem)] gap-2">
            <section class="min-w-0">
                <CartPromotionGift :item="item" :search-gift-products="searchGiftProducts" :select-gift-product="selectGiftProduct" />
            </section>

            <section class="min-w-0">
                <CartPromotionDiscount :item="item" :normalize-discount="normalizeDiscount" />
            </section>
        </div>
        <p class="text-[10px] leading-4 text-slate-500">Nhập số tiền giảm hoặc đổi sang phần trăm bằng nút bên cạnh.</p>
    </div>
</template>
