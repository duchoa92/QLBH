<script setup>
import { ref, onMounted, onBeforeUnmount  } from 'vue'
import {
    PenSquare,
    Trash2,
    Gift,
} from 'lucide-vue-next'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import { productService } from '@/Modules/POS/Product/Services/productService'
import TagBadge from '@/Components/UI/TagBadge.vue'


const props = defineProps({

    selectedIndex: {
        type: Number,
        default: -1,
    },

    items: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits([
    'remove',
])

const giftProducts = ref([])

const searchingGift = ref(false)
const promotionRef = ref(null)
const promotionPanel = ref(null)

onMounted(() => {

    document.addEventListener(
        'mousedown',
        handleClickOutside
    )
})

onBeforeUnmount(() => {

    document.removeEventListener(
        'mousedown',
        handleClickOutside
    )
})

const handleClickOutside = (event) => {

    const insidePromotion =

        event.target.closest(
            '.promotion-panel'
        )

    const clickButton =

        event.target.closest(
            '.promotion-button'
        )

    if (
        insidePromotion ||
        clickButton
    ) {

        return
    }

    props.items.forEach(item => {

        item.showPromotion = false

    })
}

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
    }
}

const closeGiftResults = (
    item
) => {

    item.gift_results = []
}


</script>

<template>
    <div
        v-for="(item,index) in items"
        :key="item.imei_id ? 'imei-' + item.imei_id : 'product-' + item.id"
        class="bg-white border border-gray-200 rounded-xl px-3 py-2 mb-1 shadow-sm"
    >
        <!--Thông tin SP, và Chức năng-->
        <div class="flex gap-3">
            <div class="w-12 h-12 rounded-lg border flex items-center justify-center shrink-0 bg-gray-50 overflow-hidden">
                <img v-if="item.image_url" :src="item.image_url" class="w-full h-full object-cover" />
                <span v-else class="text-gray-400">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V7a2 2 0 00-2-2H6a2 2 0 00-2 2v10a2 2 0 002 2h12"/></svg>
                </span>
            </div>
            <div class="flex-1 min-w-0 flex flex-col justify-center">
                <div class="flex justify-between items-center">
                    <!--Thông tin SP-->
                    <div class="font-semibold text-sm truncate">{{ item.name }}</div>
                    
                    <!--Chức năng -->
                    <div class="flex gap-2 shrink-0">

                        <!-- ghi chú -->
                        <button @click.stop="toggleNote(item)" title="Ghi chú" class="text-gray-400 hover:text-blue-600">
                            <PenSquare class="w-4 h-4" />
                        </button>

                        <!-- khuyến mại -->
                        <button @click.stop="togglePromotion(item)" title="Giảm giá, quà tặng" class="promotion-button text-gray-400 hover:text-amber-600">
                            <Gift class="w-4 h-4" />
                        </button>

                        <!-- xóa -->
                        <button @click="emit('remove', item)" title="Xóa khỏi giỏ hàng" class="text-red-400 hover:text-red-600">
                            <Trash2 class="w-4 h-4" />
                        </button>

                    </div>
                </div>
                <div class="text-xs text-gray-500">{{ item.unit_name ?? 'Cái' }}</div>
                    <div
                        v-if="item.imei"
                        class="text-[11px] text-blue-600 font-medium"
                    >
                        IMEI: {{ item.imei }}
                    </div>
                
            </div>
        </div>

        <!--Số lượng, hiện quà và tiền-->
        <div class="flex justify-between items-start mt-2">
            <div class="flex items-center gap-3">
                <div
                    class="flex items-center border rounded-lg overflow-hidden h-7 shrink-0"
                    :class="{ 'opacity-60': item.imei_id }"
                >
                    <button
                        :disabled="item.imei_id"
                        class="w-8 bg-gray-50 hover:bg-gray-100 border-r"
                        @click="item.quantity > 1 ? item.quantity-- : emit('remove', item)"
                    >-</button>
                    <input 
                        v-model="item.quantity" 
                        :disabled="item.imei_id"
                        type="number" 
                        class="w-12 text-center outline-none bg-transparent border-none focus:ring-0 text-sm no-spinner" 
                    />
                    <button    
                        :disabled="item.imei_id"
                        class="w-8 bg-gray-50 hover:bg-gray-100 border-l"
                        @click="item.quantity++"
                    >+</button>
                </div>

                <!--Hiện quà tặng-->
                <div v-if="item.gifts && item.gifts.length" class="flex flex-wrap gap-1">
                    <TagBadge
                        v-for="gift in item.gifts"
                        :key="gift.id"
                        removable
                        @remove="
                            removeGift(
                                item,
                                gift.id
                            )
                        "
                    >
                        {{ gift.name }}
                        <span class="font-bold text-red-700 px-1">x {{ gift.quantity }}</span>
                    </TagBadge>
                </div>
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
        <div v-if="item.showPromotion"
            @click.stop
            class="promotion-panel mt-3"
            :data-index="index">

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
    </div>
</template>

<style scoped>
/* Ẩn mũi tên input */
.no-spinner::-webkit-inner-spin-button,
.no-spinner::-webkit-outer-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
.no-spinner {
  -moz-appearance: textfield;
}
</style>
