<script setup>

import FloatingInput
from '@/Components/UI/FloatingInput.vue'

defineProps({

    item: Object,

    normalizeDiscount:
        Function,
})

const formatMoneyInput = (value) => {
    const number = Number(value || 0)

    return number > 0
        ? number.toLocaleString('vi-VN')
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

            class="
                w-full
                no-spinner
            "

            label="Nhập số tiền"
        />

        <button

            type="button"

            @click="
                item.discount_type =
                item.discount_type || 'amount';

                item.discount_type =
                item.discount_type
                ===
                'percent'

                ? 'amount'

                : 'percent'

                ;

                normalizeDiscount(item)
            "

            class="
                absolute
                right-0
                top-0
                bottom-0
                px-3
                min-w-9
                border-l
                bg-transparent
                text-sm
                font-medium
            "
        >

            {{
                item.discount_type
                ===
                'percent'

                ? '%'

                : 'đ'
            }}

        </button>

    </div>

</template>
