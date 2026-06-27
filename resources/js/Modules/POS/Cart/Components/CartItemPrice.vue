<script setup>

import { computed } from 'vue'

const props = defineProps({

    item: {
        type: Object,
        required: true,
    },
})

const format = (number) => {

    return Number(number || 0)
        .toLocaleString('vi-VN')
}

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

</script>

<template>

    <div
        class="
            text-right
            shrink-0
        "
    >

        <div
            class="
                text-green-600
                font-semibold
            "
        >
            {{ format(item.price) }}
        </div>

        <div
            v-if="
                item.discount_value > 0
            "
            class="
                inline-flex
                items-center
                bg-red-100
                text-red-700
                text-[11px]
                px-2
                py-1
                rounded
            "
        >

            -

            <span
                v-if="
                    item.discount_type
                    === 'percent'
                "
            >
                {{ item.discount_value }}%
            </span>

            <span v-else>
                {{ format(item.discount_value) }}đ
            </span>

        </div>

        <div
            v-if="
                item.quantity > 1
            "
            class="
                text-xs
                text-gray-500
            "
        >

            <div
                v-if="
                    item.discount_value > 0
                "
                class="
                    text-xs
                    text-gray-400
                    line-through
                "
            >
                {{
                    format(
                        item.price
                        *
                        item.quantity
                    )
                }}
            </div>

            =
            {{ format(lineTotal) }}

        </div>

    </div>

</template>