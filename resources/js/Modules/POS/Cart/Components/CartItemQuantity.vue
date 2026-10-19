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
                w-20
                text-center
                border-none
                px-2
                text-sm
                font-semibold
            "
        >

        <button
            :disabled="item.imei_id"
            class="
                w-9
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
