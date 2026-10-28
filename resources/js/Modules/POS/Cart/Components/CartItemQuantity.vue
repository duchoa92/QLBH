<script setup>
import { computed } from 'vue'

const props = defineProps({

    item: {
        type: Object,
        required: true,
    },
})

const emit = defineEmits([
    'remove',
])

const quantity = computed({
    get: () => props.item.quantity,
    set: (value) => {
        const normalized = Math.floor(Number(value))
        props.item.quantity = Number.isFinite(normalized)
            ? Math.max(1, normalized)
            : 1
    },
})

</script>

<template>

    <div
        class="
            flex
            items-center
            border
            rounded-lg
            overflow-hidden
            h-9
            shrink-0
            bg-white
        "
        :class="{
            'opacity-60':
            item.imei_id
        }"
    >

        <button
            :disabled="item.imei_id"
            class="
                w-9
                h-full
                self-stretch
                bg-gray-50
                border-r
                text-base
                font-bold
            "
            @click="
                item.quantity > 1
                ? item.quantity--
                : emit('remove')
            "
        >
            -
        </button>

        <input
            v-model="quantity"
            type="number"
            min="1"
            step="1"
            :disabled="item.imei_id"
            class="
                quantity-input
                w-20
                h-full
                text-center
                border-none
                bg-white
                px-2
                text-sm
                font-semibold
                outline-none
                focus:outline-none
                focus:ring-0
            "
        >

        <button
            :disabled="item.imei_id"
            class="
                w-9
                h-full
                self-stretch
                bg-gray-50
                border-l
                text-base
                font-bold
            "
            @click="
                item.quantity++
            "
        >
            +
        </button>

    </div>

</template>

<style scoped>
.quantity-input {
    appearance: textfield;
    -moz-appearance: textfield;
}

.quantity-input::-webkit-inner-spin-button,
.quantity-input::-webkit-outer-spin-button {
    margin: 0;
    -webkit-appearance: none;
}
</style>
