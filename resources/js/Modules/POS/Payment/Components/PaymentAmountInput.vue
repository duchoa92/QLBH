<script setup>

defineProps({

    modelValue: {
        type: String,
        default: '',
    },

    inputRef: {
        type: Object,
        default: null,
    },
})

const emit = defineEmits([
    'update:modelValue',
])

const handleInput = (event) => {

    const rawValue =
        event.target.value
            .replace(/\D/g, '')

    const formatted =
        Number(rawValue || 0)
            .toLocaleString('vi-VN')

    event.target.value =
        formatted

    emit(
        'update:modelValue',
        formatted
    )
}

</script>

<template>

    <div class="mb-4">

        <label
            class="block text-sm mb-1"
        >
            Khách đưa
        </label>

        <input
            :ref="inputRef"
            :value="modelValue"
            @input="handleInput"
            type="text"
            inputmode="numeric"
            class="w-full border rounded p-2 text-lg"
        />

    </div>

</template>