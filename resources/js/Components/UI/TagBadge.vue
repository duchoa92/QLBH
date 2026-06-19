<script setup>

defineProps({

    label: {
        type: String,
        default: '',
    },

    removable: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits([
    'remove',
])

</script>

<template>

    <div
        class="
            relative
            inline-flex
            items-center

            rounded-full

            bg-green-100
            text-green-700

            px-3
            py-1

            text-xs
        "
    >

        <slot>
            {{ label }}
        </slot>

        <button
            v-if="removable"
            type="button"
            @click="
                emit('remove')
            "
            class="
                absolute

                -top-1
                -right-1

                w-3
                h-3

                rounded-full

                bg-red-500
                text-white

                text-[10px]

                flex
                items-center
                justify-center

                shadow
            "
        >
            ×
        </button>

    </div>

</template>