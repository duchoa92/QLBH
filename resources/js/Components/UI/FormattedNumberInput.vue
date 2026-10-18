<script setup>
import { computed } from 'vue'
import { formatThousandsInput, formatThousandsText } from '@/utils/format'

defineOptions({ inheritAttrs: false })

const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: '',
    },
    modelModifiers: {
        type: Object,
        default: () => ({}),
    },
})

const emit = defineEmits(['update:modelValue', 'input', 'change', 'focus', 'blur', 'keydown', 'keyup'])

const displayValue = computed(() => formatThousandsText(props.modelValue))

const handleInput = (event) => {
    emit('update:modelValue', formatThousandsInput(event))
    emit('input', event)
}
</script>

<template>
    <input
        v-bind="$attrs"
        :value="displayValue"
        type="text"
        inputmode="numeric"
        @input="handleInput"
        @change="emit('change', $event)"
        @focus="emit('focus', $event)"
        @blur="emit('blur', $event)"
        @keydown="emit('keydown', $event)"
        @keyup="emit('keyup', $event)"
    />
</template>
