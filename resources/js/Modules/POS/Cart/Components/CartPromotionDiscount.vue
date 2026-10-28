<script setup>

import FloatingInput
from '@/Components/UI/FloatingInput.vue'
import { formatNumber } from '@/utils/format'
import { CircleX } from 'lucide-vue-next'

defineProps({

    item: Object,

    normalizeDiscount:
        Function,
})

const formatMoneyInput = (value) => {
    const number = Number(value || 0)

    return number > 0
        ? formatNumber(number)
        : ''
}

const parseMoneyInput = (value) =>
    Number(String(value || '').replace(/\D/g, ''))

const handleDiscountInput = (item, event, normalizeDiscount) => {
    if (!item.discount_type) {
        item.discount_type = 'amount'
    }

    item.discount_value = parseMoneyInput(event.target.value)

    normalizeDiscount(item)
}

</script>

<template>

    <div
        class="
            flex
            min-w-0
            flex-1
            relative
        "
    >

        <FloatingInput

            :model-value="
                formatMoneyInput(item.discount_value)
            "

            @input="
                handleDiscountInput(
                    item,
                    $event,
                    normalizeDiscount
                )
            "

            type="text"

            inputmode="numeric"

            input-class="pr-20 no-spinner"

            label="Số tiền giảm"
        />

        <button
            v-if="Number(item.discount_value || 0) > 0"
            type="button"
            @click="item.discount_value = 0; item.discount_type = null"
            class="
                absolute
                right-10
                top-1/2
                flex
                h-8
                w-8
                -translate-y-1/2
                items-center
                justify-center
                rounded-md
                bg-rose-50/80
                text-rose-600
                hover:bg-rose-100
            "
            title="Xóa giảm giá"
            aria-label="Xóa giảm giá"
        >
            <CircleX class="h-4 w-4" />
        </button>

        <button
            type="button"
            @click="
                item.discount_type = item.discount_type || 'amount';
                item.discount_type = item.discount_type === 'percent' ? 'amount' : 'percent';
                normalizeDiscount(item)
            "
            class="
                absolute
                right-1
                top-1/2
                flex
                h-8
                w-8
                -translate-y-1/2
                items-center
                justify-center
                rounded-md
                text-sm
                font-extrabold
                shadow-sm
            "
            :class="item.discount_type === 'percent'
                ? 'bg-amber-500 text-white hover:bg-amber-600'
                : 'bg-blue-600 text-white hover:bg-blue-700'"
        >
            {{ item.discount_type === 'percent' ? '%' : $currency() }}
        </button>

    </div>

</template>
