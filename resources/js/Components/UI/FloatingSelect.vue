<script setup>

defineProps({

    modelValue: {
        type: [String, Number],
        default: '',
    },

    label: {
        type: String,
        required: true,
    },

    options: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits([
    'update:modelValue',
])

</script>

<template>

    <div class="relative w-full">

        <select
            :value="modelValue"
            @change="
                emit(
                    'update:modelValue',
                    $event.target.value
                )
            "
            class="
                peer
                w-full
                h-10

                border
                border-gray-300
                rounded-lg

                px-3

                bg-white
                text-sm

                focus:border-blue-500
                focus:ring-0
            "
        >

            <option
                v-for="item in options"
                :key="item.value"
                :value="item.value"
            >
                {{ item.label }}
            </option>

        </select>

        <label
            class="
                absolute
                left-3

                bg-white
                px-1

                text-gray-500

                transition-all

                -top-2
                text-xs
            "
        >
            {{ label }}
        </label>

    </div>

</template>