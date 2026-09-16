<script setup>

import { computed } from 'vue'

const props = defineProps({

    item: {
        type: Object,
        required: true,
    },
})


const lineTotal = computed(() => {

    const total =

        Number(props.item.price)
        *
        Number(props.item.quantity)

    if (
        props.item.discount_type === 'percent'
    ) {

        return total -
            (
                total *
                Number(
                    props.item.discount_value
                )
                / 100
            )
    }

    if (
        props.item.discount_type === 'amount'
    ) {

        return total -
            Number(
                props.item.discount_value
            )
    }

    return total
})

const originalTotal = computed(() =>
    Number(props.item.price) * Number(props.item.quantity)
)

const hasDiscount = computed(() =>
    Number(props.item.discount_value || 0) > 0
)

</script>

<template>

    <div class="text-right shrink-0">
        <div
            class="font-semibold"
            :class="hasDiscount ? 'text-xs text-slate-400 line-through' : 'text-green-600'"
        >
            {{ $money(item.price) }}
        </div>

        <div
            v-if="hasDiscount"
            class="text-base font-black text-green-700"
        >
            {{ $money(lineTotal) }}
        </div>

        <div
            v-if="hasDiscount"
            class="inline-flex items-center bg-red-100 text-red-700 text-[11px] px-2 py-1 rounded"
        >
            -
            <span v-if="item.discount_type === 'percent'">{{ item.discount_value }}%</span>
            <span v-else>{{ $money(item.discount_value) }}đ</span>
            <button
                @click="item.discount_value = 0"
                title="Hủy giảm giá"
                class="ml-1 text-red-500 font-bold hover:text-red-700"
            >
                ×
            </button>
        </div>

        <div
            v-if="item.quantity > 1"
            class="text-xs text-gray-500"
        >
            <div
                v-if="hasDiscount"
                class="text-xs text-gray-400 line-through"
            >
                {{ $money(originalTotal) }}
            </div>
            = {{ $money(lineTotal) }}
        </div>
    </div>

</template>
