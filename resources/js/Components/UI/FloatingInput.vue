<script setup>
import { computed } from 'vue'
import { formatThousandsInput, formatThousandsText } from '@/utils/format'

const props = defineProps({

    modelValue: {
        type: [String, Number],
        default: '',
    },

    label: {
        type: String,
        required: true,
    },
    placeholderText: {
        type: String,
        default: '',
    },

    type: {
        type: String,
        default: 'text',
    },
    name: String,
    id: String,
    error: String,
    formatThousands: {
        type: Boolean,
        default: false,
    },
    inputClass: {
        type: String,
        default: '',
    },
    modelModifiers: {
        type: Object,
        default: () => ({}),
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
    const value = props.formatThousands
        ? formatThousandsInput(event)
        : event.target.value

    emit('update:modelValue', value)

    emit(
        'input',
        event
    )
}

const displayValue = computed(() => props.formatThousands
    ? formatThousandsText(props.modelValue)
    : props.modelValue)
const inputType = computed(() => props.formatThousands ? 'text' : props.type)

</script>

<template>

    <div class="relative w-full">

        <input
            :name="name"
            :id="id || name"
            ref="input"
            v-bind="$attrs" 
            :value="displayValue"
            @input="handleInput"
            @change="emit('change', $event)"
            @focus="emit('focus', $event)"
            @blur="emit('blur', $event)"
            @keydown="emit('keydown', $event)"
            @keyup="emit('keyup', $event)"
            :type="inputType"
            :inputmode="formatThousands ? ($attrs.inputmode || 'numeric') : $attrs.inputmode"
            placeholder=" "
            :class="['peer w-full border border-gray-300 rounded-lg px-2 pt-2.5 pb-2 text-sm focus:border-blue-500 focus:ring-0 transition-all', inputClass]"
        />
        <span
            v-if="placeholderText"
            class="pointer-events-none absolute left-3 top-2.5 hidden text-sm text-gray-400 peer-placeholder-shown:block peer-focus:hidden"
        >{{ placeholderText }}</span>
        <!-- Lỗi-->
        <p v-if="error" class="text-red-500 text-sm mt-1">
            {{ error }} 
        </p>

        <label
            :for="id || name"
            :class="[
                'pointer-events-none absolute left-3 bg-white px-1 text-gray-500 transition-all duration-200 -top-2 text-xs peer-placeholder-shown:top-2.5 peer-placeholder-shown:text-sm peer-focus:-top-2 peer-focus:text-xs peer-focus:text-blue-600',
                placeholderText ? 'peer-placeholder-shown:opacity-0 peer-focus:opacity-100' : '',
            ]"
        >

            {{ label }}

        </label>

    </div>

</template>
