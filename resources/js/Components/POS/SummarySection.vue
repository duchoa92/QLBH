

<script setup>
import { computed } from 'vue'

const props = defineProps({
    items: Array
})
// Tính tổng tiền tạm tính
const subtotal = computed(() => {

    return props.items.reduce(
        (total, item) => {

            return total + (item.sell_price * item.quantity)

        },
        0
    )
})
// Định dạng số thành định dạng tiền tệ Việt Nam
const format = (number) => {
    return Number(number).toLocaleString('vi-VN')
}

//
const emit = defineEmits([
    'checkout',
])
</script>
<template>
    <div>

        <div class="flex justify-between py-2 border-b">
            <span>Tạm tính</span>

            <span>
                {{ format(subtotal) }}
            </span>
        </div>

        <div class="flex justify-between py-2 border-b">
            <span>Giảm giá</span>
            <span>0</span>
        </div>

        <div class="flex justify-between py-3 text-xl font-bold">
            <span>Tổng tiền</span>

            <span>
                {{ format(subtotal) }}
            </span>
        </div>

        <button
            @click="emit('checkout')"
            class="w-full bg-blue-600 text-white py-3 rounded"
        >
            Thanh toán
        </button>

    </div>
</template>