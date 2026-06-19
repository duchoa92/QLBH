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

    type: {
        type: String,
        default: 'text',
    },
})

const emit = defineEmits([
    'update:modelValue',
    'input',
    'change',
    'focus',
    'blur',
    'keydown',
    'keyup',
])

const handleInput = (event) => {

    emit(
        'update:modelValue',
        event.target.value
    )

    emit(
        'input',
        event
    )
}

</script>

<template>

    <div class="relative w-full">

        <input
            v-bind="$attrs" 
            :value="modelValue"
            @input="handleInput"
            @change="emit('change', $event)"
            @focus="emit('focus', $event)"
            @blur="emit('blur', $event)"
            @keydown="emit('keydown', $event)"
            @keyup="emit('keyup', $event)"
            :type="type"
            placeholder=" "
            class="peer w-full border border-gray-300 rounded-lg px-2 pt-2 pb-2 text-sm focus:border-blue-500 focus:ring-0 transition-all"
/>

        <label
            for="input-id"
            class="
                pointer-events-none
                absolute
                left-3

                bg-white
                px-1

                text-gray-500

                transition-all
                duration-200

                -top-2
                text-xs

                peer-placeholder-shown:top-2.5
                peer-placeholder-shown:text-sm

                peer-focus:-top-2
                peer-focus:text-xs
                peer-focus:text-blue-600
            "
        >

            {{ label }}

        </label>

    </div>

</template>