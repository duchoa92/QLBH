<script setup>
import FloatingInput from '@/Components/UI/FloatingInput.vue'

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

                <!-- Quà tặng -->
                <div class="flex-[2] relative">

                    <FloatingInput
                        v-model="item.gift_keyword"
                        @input="searchGiftProducts(item)"
                        class=""
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
                            v-for="product in item.gift_results"
                            :key="product.id"
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

                            <div class="font-medium">
                                {{ product.name }}
                            </div>

                            <div class="text-xs text-gray-500">
                                {{ product.sku }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Giảm giá -->
                <div class="flex flex-1 relative">
                    <FloatingInput
                        v-model.number="item.discount_value"
                        @input="normalizeDiscount(item)"
                        type="number"
                        min="0"
                        class="w-full no-spinner" 
                        label="Nhập số tiền"
                    />

                    <button
                        type="button"
                        @click="item.discount_type = item.discount_type === 'percent' ? 'amount' : 'percent'"
                        class="absolute right-0 top-0 bottom-0 px-3 min-w-9 border-l bg-transparent text-sm font-medium z-10 flex items-center justify-center hover:bg-gray-50 transition-colors"
                        style="height: 100%; margin-top: 0px;"
                    >
                        {{ item.discount_type === 'percent' ? '%' : 'đ' }}
                    </button>
                </div>
            </div>
        </div>
</template>