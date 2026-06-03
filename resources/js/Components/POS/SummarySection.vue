<script setup>
import { computed } from 'vue'

const props = defineProps({

    items: {
        type: Array,
        default: () => [],
    },
})

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

const itemCount = computed(() => {

    return props.items.reduce(

        (total, item) => total + Number(item.quantity || 0),

        0
    )
})

const format = (number) => {

    return Number(number || 0)
        .toLocaleString('vi-VN')
}

const emit = defineEmits([
    'checkout',
])
</script>

<template>

    <div class="space-y-3">

        <div class="space-y-2 rounded-lg bg-slate-50 p-3">

            <div class="flex justify-between text-sm text-slate-600">

                <span>So luong</span>

                <span class="font-semibold text-slate-900">
                    {{ itemCount }}
                </span>

            </div>

            <div class="flex justify-between text-sm text-slate-600">

                <span>Tam tinh</span>

                <span class="font-semibold text-slate-900">
                    {{ format(subtotal) }}
                </span>

            </div>

            <div class="flex justify-between text-sm text-slate-600">

                <span>Giam gia</span>

                <span class="font-semibold text-slate-900">
                    0
                </span>

            </div>

        </div>

        <div class="flex items-end justify-between">

            <span class="text-sm font-semibold uppercase tracking-wide text-slate-500">
                Tong thanh toan
            </span>

            <span class="text-2xl font-black text-blue-700">
                {{ format(subtotal) }}
            </span>

        </div>

        <button
            type="button"
            @click="emit('checkout')"
            class="h-14 w-full rounded-md bg-blue-600 text-base font-black uppercase tracking-wide text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-slate-300"
            :disabled="!items.length"
        >
            Thanh toan
        </button>

    </div>

</template>
