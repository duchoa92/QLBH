<script setup>

const props = defineProps({

    item: {
        type: Object,
        required: true,
    },
})

const emit = defineEmits([
    'remove',
])

</script>

<template>

    <div
        class="
            flex
            items-center
            border
            rounded-lg
            overflow-hidden
            h-7
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
                w-8
                bg-gray-50
                border-r
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
            v-model="item.quantity"
            type="number"
            :disabled="item.imei_id"
            class="
                w-12
                text-center
                border-none
            "
        >

        <button
            :disabled="item.imei_id"
            class="
                w-8
                bg-gray-50
                border-l
            "
            @click="
                item.quantity++
            "
        >
            +
        </button>

    </div>

</template>