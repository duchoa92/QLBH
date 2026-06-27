<script setup>

import FloatingInput
from '@/Components/UI/FloatingInput.vue'

defineProps({

    item: Object,

    normalizeDiscount:
        Function,
})

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

            v-model.number="
                item.discount_value
            "

            @input="
                normalizeDiscount(
                    item
                )
            "

            type="number"

            min="0"

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
                item.discount_type
                ===
                'percent'

                ? 'amount'

                : 'percent'
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