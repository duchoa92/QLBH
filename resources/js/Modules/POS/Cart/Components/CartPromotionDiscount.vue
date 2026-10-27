<script setup>

import FloatingInput
from '@/Components/UI/FloatingInput.vue'
import { formatNumber } from '@/utils/format'
import { Trash2 } from 'lucide-vue-next'

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
                right-9
                top-0
                bottom-0
                flex
                h-9
                w-9
                items-center
                justify-center
                border-l
                bg-rose-50
                text-rose-600
                hover:bg-rose-100
            "
            title="Xóa giảm giá"
            aria-label="Xóa giảm giá"
        >
            <Trash2 class="h-4 w-4" />
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
                right-0
                top-0
                bottom-0
                px-1
                min-w-9
                border-l
                bg-transparent
                text-sm
                font-medium
            "
        >
            {{ item.discount_type === 'percent' ? '%' : $currency() }}
        </button>

    </div>

</template>
