<script setup>
import { ref, onMounted, onBeforeUnmount  } from 'vue'
import {
    PenSquare,
    Trash2,
    Gift,
} from 'lucide-vue-next'
import { productService } from '@/Modules/POS/Product/Services/productService'



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

        item.discount_type = null

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
        <img v-if="item.image" :src="item.image" class="w-full h-full object-cover" />
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

    <!--Số lượng và tiền-->
    <div class="flex justify-between items-center mt-2">
        <!--Số lượng-->
        <div
            class="flex items-center border rounded-lg overflow-hidden h-7"
            :class="{
                'opacity-60': item.imei_id
            }"
        >
        <button
            :disabled="item.imei_id"
            class="w-8 bg-gray-50 hover:bg-gray-100 border-r" @click="item.quantity > 1 ? item.quantity-- : emit('remove', item)">-</button>
        <input 
            v-model="item.quantity" 
            :disabled="item.imei_id"
            type="number" 
            class="w-12 text-center outline-none bg-transparent border-none focus:ring-0 text-sm no-spinner" 
        />
        <button    
            :disabled="item.imei_id"
            class="w-8 bg-gray-50 hover:bg-gray-100 border-l" @click="item.quantity++">+</button>
        </div>

        <!--Hiện quà tặng-->
        <div v-if="
                item.gifts &&
                item.gifts.length
            "
            class="flex flex-wrap gap-1 mt-1 items-Left"
        >
            <div
                v-for="gift in item.gifts"
                :key="gift.id"
                class="bg-green-100 text-green-700 text-[11px] px-2 py-1 rounded-full flex items-center gap-1"
            >
                🎁 {{ gift.name }}
                <span class="font-bold">
                    x{{ gift.quantity }}
                </span>

                <button
                    @click="
                        removeGift(
                            item,
                            gift.id
                        )
                    "
                    class="text-red-500 font-bold"
                >
                    ×
                </button>
            </div>
        </div>

        

        
        <!--Tiền-->
        <div class="text-right">
            <div class="text-green-600 font-semibold">
                {{ format(item.price) }}
            </div>

             <!--Giảm giá-->
            <div
                v-if="
                    item.discount_value &&
                    item.discount_value > 0
                "
                class="items-right bg-red-100 text-red-700 text-[11px] px-2 py-1"
            >
                -
                <span v-if="item.discount_type === 'percent'">
                    {{ item.discount_value }}%
                </span>
                <span v-else>
                    {{ format(item.discount_value) }}đ
                </span>
                <button
                    @click="
                        item.discount_value = 0
                    "
                    title="Hủy giảm giá"
                    class="text-red-500 font-bold"
                >
                    ×
                </button>
            </div>

            <div
                v-if="item.quantity > 1"
                class="text-xs text-gray-500"
            >
                <div
                    v-if="item.discount_value > 0"
                    class="text-xs text-gray-400 line-through"
                >
                    {{ format(item.price * item.quantity) }}
                </div>

                = {{ format(lineTotal(item)) }}
            </div>
        </div>
    </div>

    <!--hiện Ghi chú-->
    <div v-if="item.note"class="mt-1 inline-flex items-center gap-1 bg-blue-100 text-blue-700 text-[11px] px-2 py-1 rounded-full">
        Ghi chú: {{ item.note }}
        <button
            @click="
                item.note = ''
            "
            class="text-red-500 font-bold"
        >
            ×
        </button>
    </div>
    <!--Ô ghi chú-->
    <textarea
      v-if="item.showNote"
      v-model="item.note"
      @blur="
            item.showNote = false
        "
      rows="1"
      placeholder="Ghi chú..."
      class="w-full mt-3 p-2 border rounded-lg text-sm focus:border-blue-500 outline-none"
    />

    <!--Phần quà tặng và giảm giá-->
    <div
        v-if="item.showPromotion"
        
        @click.stop
        class="promotion-panel mt-2 p-3 border rounded-lg bg-amber-10 space-y-2"
        :data-index="index">
      <div>
          <div class="relative">
            <input
                v-model="item.gift_keyword"
                @input="
                    searchGiftProducts(item)
                "
                @blur="
                    setTimeout(
                        () => closeGiftResults(item),
                        200
                    )
                "
                placeholder="Nhập tên sản phẩm để tìm"
                class="w-full border rounded-lg px-2 py-2 text-sm"
            >
            <div
                v-if="
                    item.gift_results
                    &&
                    item.gift_results.length
                "
                class="absolute z-50 bg-white border rounded-lg shadow-lg w-full max-h-60 overflow-y-auto"
            >
                <div v-for="product in item.gift_results"
                    :key="product.id"
                    @click="
                        selectGiftProduct(
                            item,
                            product
                        )
                    "
                    class="px-3 py-2 cursor-pointer hover:bg-blue-50 border-b">

                    <div class="font-medium">{{ product.name }}</div>
                    <div class="text-xs text-gray-500">{{ product.sku }}</div>
                </div>
            </div>
        </div>
      </div>

      <div class="space-y-2">

        <input
            v-model.number="item.discount_value"
            @input="normalizeDiscount(item)"
            type="number"
            min="0"
            placeholder="Nhập số tiền muốn giảm"
            class="w-full border rounded-lg px-2 py-2 text-sm"
        >
        <div class="flex gap-4">
            <label
                class="flex items-center gap-1 text-sm"
            >
                <input
                    type="radio"
                    value="amount"
                    v-model="item.discount_type"
                >
                Theo tiền
            </label>
            <label
                class="flex items-center gap-1 text-sm"
            >
                <input
                    type="radio"
                    value="percent"
                    v-model="item.discount_type"
                >
                Theo %
            </label>
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
