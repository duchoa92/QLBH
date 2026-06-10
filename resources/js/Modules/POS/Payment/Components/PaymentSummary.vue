<script setup>

defineProps({

    grand_total: {
        type: Number,
        default: 0,
    },

    changeAmount: {
        type: Number,
        default: 0,
    },

    formatMoney: {
        type: Function,
        required: true,
    },
})

</script>

<template>

    <div>

        <!-- Total -->
        <div class="mb-4">

            <div class="text-gray-500 text-sm">
                Tổng tiền
            </div>

            <div
                class="text-3xl font-bold text-blue-600"
            >
                {{ formatMoney(grand_total) }} đ
            </div>

        </div>

        <!-- Change -->
        <div class="mb-6">

            <div class="text-sm text-gray-500">
                Tiền thừa
            </div>

            <div
                class="text-2xl font-bold"
                :class="{
                    'text-red-600':
                        changeAmount < 0,

                    'text-green-600':
                        changeAmount >= 0,
                }"
            >
                {{ formatMoney(changeAmount) }} đ
            </div>

        </div>

    </div>

</template>