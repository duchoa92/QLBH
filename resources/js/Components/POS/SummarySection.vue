<script setup>
import { computed } from 'vue'

const props = defineProps({

    items: Array,
})

/*
|--------------------------------------------------------------------------
| Tạm tính
|--------------------------------------------------------------------------
*/

const subtotal = computed(() => {

    return props.items.reduce(

        (total, item) => {

            return total +

                (
                    Number(item.price || 0) *
                    Number(item.quantity || 0)
                )
        },

        0
    )
})

/*
|--------------------------------------------------------------------------
| Format money
|--------------------------------------------------------------------------
*/

const format = (number) => {

    return Number(number || 0)
        .toLocaleString('vi-VN')
}

/*
|--------------------------------------------------------------------------
| Emit
|--------------------------------------------------------------------------
*/

const emit = defineEmits([
    'checkout',
])
</script>

<template>

    <div>

        <!-- Tạm tính -->

        <div class="flex justify-between py-2 border-b">

            <span>
                Tạm tính
            </span>

            <span>
                {{ format(subtotal) }}
            </span>

        </div>

        <!-- Giảm giá -->

        <div class="flex justify-between py-2 border-b">

            <span>
                Giảm giá
            </span>

            <span>
                0
            </span>

        </div>

        <!-- Tổng -->

        <div class="flex justify-between py-3 text-xl font-bold">

            <span>
                Tổng tiền
            </span>

            <span>
                {{ format(subtotal) }}
            </span>

        </div>

        <!-- Button -->

        <button
            @click="emit('checkout')"
            class="w-full bg-blue-600 text-white py-3 rounded"
        >
            Thanh toán
        </button>

    </div>

</template>