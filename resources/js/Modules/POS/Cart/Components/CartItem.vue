<script setup>
import {
    PenSquare,
    Trash2,
    Gift,
} from 'lucide-vue-next'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import TagBadge from '@/Components/UI/TagBadge.vue'
import { productService } from '@/Modules/POS/Product/Services/productService'
import {
    ref,
} from 'vue'
import CartItemPromotion from './CartItemPromotion.vue'
import CartItemGiftList from './CartItemGiftList.vue'
import CartItemHeader from './CartItemHeader.vue'
import CartItemQuantity from './CartItemQuantity.vue'

const props = defineProps({

    item: {
        type: Object,
        required: true,
    },
})

const item = props.item

const emit = defineEmits([
    'remove',
])

const format = (number) => {

    return Number(number || 0)
        .toLocaleString('vi-VN')
}

// Ghi chú
const toggleNote = (item) => {

    item.showNote =
        !item.showNote
}

//
const togglePromotion = (item) => {

    const isOpen =
        item.showPromotion

    props.items.forEach(i => {

        i.showPromotion = false
    })

    item.showPromotion =
        !isOpen
}

const lineTotal = (item) => {

    const total =

        Number(item.price)
        *
        Number(item.quantity)

    if (
        item.discount_type === 'percent'
    ) {

        return total -
            (
                total *
                Number(item.discount_value)
                / 100
            )
    }

    if (
        item.discount_type === 'amount'
    ) {

        return total -
            Number(item.discount_value)
    }

    return total

}

const normalizeDiscount = (item) => {

    if (
        !item.discount_value
    ) {

        item.discount_value = 0

        return
    }
    /*
    |--------------------------------------
    | Giảm %
    |--------------------------------------
    */

    if (
        item.discount_type === 'percent'
    ) {

        if (
            item.discount_value > 100
        ) {

            item.discount_value = 100
        }

        if (
            item.discount_value < 0
        ) {

            item.discount_value = 0
        }

        return
    }

    /*
    |--------------------------------------
    | Giảm tiền
    |--------------------------------------
    */

    const maxDiscount =

        Number(item.price)
        *
        Number(item.quantity)

    if (
        item.discount_value > maxDiscount
    ) {

        item.discount_value =
            maxDiscount
    }

    if (
        item.discount_value < 0
    ) {

        item.discount_value = 0
    }

}

// Tìm quà
let giftTimer = null

const searchGiftProducts = async (
    item
) => {

    clearTimeout(giftTimer)

    giftTimer = setTimeout(
        async () => {

            if (
                !item.gift_keyword ||
                item.gift_keyword.length < 2
            ) {

                item.gift_results = []

                return
            }

            try {

                const results =
                    await productService.search(
                        item.gift_keyword
                    )

                item.gift_results =
                    results
                        .filter(product => {

                            return (
                                product.product_type !== 'imei'
                            )

                        })
                        .slice(0, 10)

            } catch (error) {

                console.error(error)

            }

        },
        300
    )
}

// Chọn quà
const selectGiftProduct = (
    item,
    product
) => {

    if (!item.gifts) {

        item.gifts = []
    }

    const gift = item.gifts.find(
        g => g.id === product.id
    )

    if (gift) {

        gift.quantity++
    }
    else {


        item.gift_product_id =
            product.id

        item.gifts.push({

            id: product.id,

            name: product.name,

            sku: product.sku,

            quantity: 1,
        })
    }

    item.gift_keyword = ''

    item.gift_results = []

}

// Xóa quà
const removeGift = (
    item,
    giftId
) => {

    const gift =
        item.gifts.find(
            g => g.id === giftId
        )

    if (!gift) {

        return
    }

    gift.quantity--

    if (
        gift.quantity <= 0
    ) {

        item.gifts =
            item.gifts.filter(
                g => g.id !== giftId
            )

        item.gift_product_id =
            null
    }
}


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

                    @remove="
                        emit(
                            'remove',
                            item
                        )
                    "
                />

                <!--Hiện quà tặng-->
                <CartItemGiftList
                    v-if="
                        item.gifts &&
                        item.gifts.length
                    "
                    :gifts="item.gifts"
                    @remove="
                        removeGift(
                            item,
                            $event
                        )
                    "
                />
            </div>

            <div class="text-right shrink-0">
                <div class="text-green-600 font-semibold">
                    {{ format(item.price) }}
                </div>

                <div
                    v-if="item.discount_value && item.discount_value > 0"
                    class="inline-flex items-center bg-red-100 text-red-700 text-[11px] px-2 py-1 rounded"
                >
                    -
                    <span v-if="item.discount_type === 'percent'">{{ item.discount_value }}%</span>
                    <span v-else>{{ format(item.discount_value) }}đ</span>
                    <button
                        @click="item.discount_value = 0"
                        title="Hủy giảm giá"
                        class="ml-1 text-red-500 font-bold hover:text-red-700"
                    >
                        ×
                    </button>
                </div>

                <div v-if="item.quantity > 1" class="text-xs text-gray-500">
                    <div v-if="item.discount_value > 0" class="text-xs text-gray-400 line-through">
                        {{ format(item.price * item.quantity) }}
                    </div>
                    = {{ format(lineTotal(item)) }}
                </div>
            </div>
        </div>

        <!--nơi hiện Ghi chú-->
        <div v-if="item.note" class="bg-white rounded-lg flex items-center w-full mt-2 border overflow-hidden">
            <div class="bg-gray-100 px-3 py-1 font-semibold text-blue-900 text-sm border-r flex items-center self-stretch">
                Ghi chú:
            </div>
            
            <div class="flex-1 px-3 py-1 flex justify-between items-center bg-white">
                <span class="truncate">{{ item.note }}</span>
                <button
                    @click="item.note = ''"
                    class="ml-1 px-1 text-blue-400 hover:text-rose-600 hover:bg-rose-100 rounded-full transition-colors shrink-0"
                >
                    ×
                </button>
            </div>
        </div>
        <!--Ô nhập ghi chú-->
        <div v-if="item.showNote" class="mt-2">
            <FloatingInput
                v-model="item.note"
                @blur="
                        item.showNote = false
                    "
                rows="1"
                class=""
                label="Nhập ghi chú"
            />
        </div>
        <!--nơi nhập quà tặng và giảm giá-->
        <CartItemPromotion
            v-if="item.showPromotion"
            :item="item"
            :searchGiftProducts="
                searchGiftProducts
            "
            :selectGiftProduct="
                selectGiftProduct
            "
            :normalizeDiscount="
                normalizeDiscount
            "
        />
        

    </div>
</template>