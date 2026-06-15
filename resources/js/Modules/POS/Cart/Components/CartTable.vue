<script setup>
import { ref } from 'vue'
import {
    PenSquare,
    Trash2,
    Gift,
} from 'lucide-vue-next'
import GiftProductModal from '@/Modules/POS/Product/Components/GiftProductModal.vue'

defineProps({

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

    item.showPromotion =
        !item.showPromotion

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

// Hiện chọn quà tặng
const showGiftModal = ref(false)
// Chọn quà
const selectedGiftItem = ref(null)

const openGiftModal = (item) => {

    selectedGiftItem.value =
        item

    showGiftModal.value = true
}

const selectGiftProduct = (
    product
) => {

    selectedGiftItem.value.gift_product_id =
        product.id

    selectedGiftItem.value.gift_product_name =
        product.name

    showGiftModal.value = false
}

</script>

<template>
  <div
    v-for="item in items"
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
            <button @click="toggleNote(item)" title="Ghi chú" class="text-gray-400 hover:text-blue-600">
                <PenSquare class="w-4 h-4" />
            </button>

            <!-- khuyến mại -->
            <button @click="openGiftModal(item)" title="Giảm giá, quà tặng" class="text-gray-400 hover:text-amber-600">
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
        <div
            v-if="item.gift_product_name"
            class="text-[11px] text-green-600 mt-1"
        >

            🎁

            {{ item.gift_product_name }}

        </div>

        <div
            v-if="item.discount_value > 0"
            class="text-[11px] text-red-500"
        >
            <template
                v-if="item.discount_type === 'percent'"
            >
                Giảm {{ item.discount_value }}%
            </template>

            <template
                v-else
            >
                Giảm {{ format(item.discount_value) }}đ
            </template>
        </div>
      </div>
    </div>

    <!--Số lượng và tiền-->
    <div class="flex justify-between items-center mt-2">
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

      <!--Tiền-->
      <div class="text-right">
        <div class="text-green-600 font-semibold">
            {{ format(item.price) }}
        </div>

        <div
            v-if="item.quantity > 1"
            class="text-xs text-gray-500"
        >
          <div
              v-if="item.discount_value > 0"
              class="
                  text-xs
                  text-gray-400
                  line-through
              "
          >
              {{ format(item.price * item.quantity) }}
          </div>

          = {{ format(lineTotal(item)) }}
        </div>
    </div>
    </div>

    <textarea
      v-if="item.showNote"
      v-model="item.note"
      rows="1"
      placeholder="Ghi chú..."
      class="w-full mt-3 p-2 border rounded-lg text-sm focus:border-blue-500 outline-none"
    />
    <div
      v-if="item.showPromotion"
      class="mt-2 p-3 border rounded-lg bg-amber-50 space-y-2 ">
      <div>
        <label class="block text-xs font-medium mb-1">Quà tặng </label>
          <button
              type="button"
              @click="openGiftModal(item)"
              class="w-full border rounded-lg px-2 py-2 text-left text-sm bg-white">
              {{
                  item.gift_product_name
                      ?? 'Chọn quà tặng'
              }}
          </button>
      </div>

      <div class="grid grid-cols-2 gap-2">
          <div>
              <label class="block text-xs font-medium mb-1">
                  Loại giảm giá
              </label>

              <select
                  v-model="item.discount_type"
                  class="w-full border rounded-lg px-2 py-1 text-sm">
                  <option :value="null">Không giảm</option>
                  <option value="amount">Theo tiền</option>
                  <option value="percent">Theo % </option>
              </select>
          </div>

          <div>
              <label class="block text-xs font-medium mb-1">Giá trị</label>
              <input
                  v-model.number="item.discount_value"
                  @input="normalizeDiscount(item)"
                  type="number"
                  min="0"
                  class="w-full border rounded-lg px-2 py-1 text-sm"/>
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
