<script setup>

import { CircleMinus, Minus, X } from 'lucide-vue-next'
import { computed } from 'vue'

const props = defineProps({
    label: String,

    removable: {
        type: Boolean,
        default: false
    },

    size: {
        type: String,
        default: 'md' // sm | md | lg
    },

    color: {
        type: String,
        default: 'green' // green | blue | gray
    }
})

const emit = defineEmits(['remove'])

const sizeClass = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'text-[11px] px-2 py-[2px]'
        case 'lg':
            return 'text-sm px-3 py-1.5'
        default:
            return 'text-xs px-3 py-1'
    }
})

const colorClass = computed(() => {
    switch (props.color) {
        case 'blue':
            return 'bg-blue-100 text-blue-700 border-blue-200'
        case 'red':
            return 'bg-red-100 text-red-700 border-red-200'
        case 'gray':
            return 'bg-gray-100 text-gray-700 border-gray-200'
        case 'yellow':
            return 'bg-yellow-100 text-yellow-700 border-yellow-200'
        default:
            return 'bg-green-100 text-green-700 border-green-200'
    }
})
</script>

<template>
    <div
        class="
            relative  
            inline-flex
            items-center
            gap-1
            rounded-md
            border
            font-medium
        "
        :class="[sizeClass, colorClass]"
    >
        <span>{{ label }}</span>

        <button
            v-if="removable"
            @click="emit('remove')"
            class="
                absolute
                -top-1.5
                -right-1.5

                w-3 h-3
                rounded-full

                bg-red-500
                text-white

                text-[10px]
                leading-none

                flex items-center justify-center

                shadow
                hover:bg-red-600
            "
        >
            <minus />
        </button>
    </div>
</template>