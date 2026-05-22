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
            class="w-full bg-blue-600 text-white py-3 rounded mt-3"
        >
            Thanh toán
        </button>

    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    items: Array
})

const subtotal = computed(() => {

    return props.items.reduce(
        (total, item) => {

            return total + (item.sell_price * item.quantity)

        },
        0
    )
})

const format = (number) => {
    return Number(number).toLocaleString('vi-VN')
}
</script>