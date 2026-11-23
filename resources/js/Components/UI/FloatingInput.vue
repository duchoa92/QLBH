<script setup>
import { computed, ref } from 'vue'
import { Eye, EyeOff, X } from 'lucide-vue-next'
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
    floatingLabel: {
        type: Boolean,
        default: true,
    },
    placeholderText: {
        type: String,
        default: '',
    },
    placeholderClass: {
        type: String,
        default: '',
    },
    labelClass: {
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
    // Hiện nút X xóa nhanh nội dung khi ô có dữ liệu (dùng cho ô tìm kiếm).
    clearable: {
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

const clearInput = () => {
    emit('update:modelValue', '')
    emit('input', { target: { value: '' } })
}

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
const passwordVisible = ref(false)
const isPassword = computed(() => !props.formatThousands && props.type === 'password')
const resolvedInputType = computed(() => isPassword.value && passwordVisible.value ? 'text' : inputType.value)

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
            :type="resolvedInputType"
            :inputmode="formatThousands ? ($attrs.inputmode || 'numeric') : $attrs.inputmode"
            placeholder=" "
            :class="['peer w-full border border-gray-300 rounded-lg px-2 pt-2.5 pb-2 text-sm focus:border-blue-500 focus:ring-0 transition-all', isPassword ? 'pr-10' : '', inputClass]"
        />
        <button
            v-if="isPassword"
            type="button"
            class="absolute right-2.5 top-1/2 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-md text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
            :title="passwordVisible ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
            :aria-label="passwordVisible ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
            @click="passwordVisible = !passwordVisible"
        ><EyeOff v-if="passwordVisible" class="h-4 w-4" /><Eye v-else class="h-4 w-4" /></button>
        <button
            v-if="clearable && !isPassword && String(modelValue ?? '') !== ''"
            type="button"
            class="absolute right-2.5 top-1/2 flex h-5 w-5 -translate-y-1/2 items-center justify-center rounded-full bg-slate-200 text-slate-500 transition hover:bg-slate-300"
            title="Xóa nội dung"
            aria-label="Xóa nội dung"
            @click="clearInput"
        ><X class="h-3 w-3" /></button>
        <span
            v-if="placeholderText"
            class="pointer-events-none absolute left-3 top-2.5 hidden text-sm text-gray-400 peer-placeholder-shown:block peer-focus:hidden"
            :class="placeholderClass"
        >{{ placeholderText }}</span>
        <!-- Lỗi-->
        <p v-if="error" class="text-red-500 text-sm mt-1">
            {{ error }} 
        </p>

        <label
            v-if="floatingLabel"
            :for="id || name"
            :class="[
                'pointer-events-none absolute left-3 bg-white px-1 text-gray-500 transition-all duration-200 -top-2 text-xs peer-placeholder-shown:top-2.5 peer-placeholder-shown:text-sm peer-focus:-top-2 peer-focus:text-xs peer-focus:text-blue-600',
                placeholderText ? 'peer-placeholder-shown:opacity-0 peer-focus:opacity-100' : '',
                labelClass,
            ]"
        >

            {{ label }}

        </label>

    </div>

</template>
