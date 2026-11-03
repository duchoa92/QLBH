<script setup>

const props = defineProps({

    modelValue: {
        type: [String, Number, null],
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

    optionLabel: {
        type: String,
        default: 'label',
    },

    optionValue: {
        type: String,
        default: 'value',
    },

    name: String,
    error: String,
    disabled: { type: Boolean, default: false },
    inputClass: { type: String, default: '' },
    wrapperClass: { type: String, default: '' },
    wrapperStyle: { type: Object, default: null },
    compact: { type: Boolean, default: false },
    hideLabel: { type: Boolean, default: false },
    placeholderText: { type: String, default: 'Chọn...' },
    showPlaceholder: { type: Boolean, default: true },
    floatOnValue: { type: Boolean, default: false },
    createLabel: { type: String, default: '' },

})

const emit = defineEmits([
    'update:modelValue',
    'create',
])

const handleChange = (event) => {

    const selectedValue = event.target.value

    // Không chọn gì
    if (selectedValue === '') {
        emit('update:modelValue', null)
        return
    }

    /*
    |--------------------------------------------------------------------------
    | Tìm option thật trong mảng options
    |--------------------------------------------------------------------------
    | <select> luôn trả về string.
    | Nhưng options của chúng ta có thể là:
    |
    | id      => number
    | value   => string
    |
    | Vì vậy phải lấy lại giá trị gốc từ options.
    |--------------------------------------------------------------------------
    */

    const selectedOption = props.options.find(
        item => String(item[props.optionValue]) === selectedValue
    )

    if (selectedOption) {
        emit(
            'update:modelValue',
            selectedOption[props.optionValue]
        )
    } else {
        // fallback
        emit('update:modelValue', selectedValue)
    }
}

</script>

<template>

    <div class="relative w-full" :class="wrapperClass" :style="wrapperStyle">

        <select
            :name="name"
            :id="name"
            ref="input"
            :value="modelValue ?? ''"
            :disabled="disabled"
            @change="handleChange"
            class="
                peer
                w-full
                border
                border-gray-300
                rounded-lg
                bg-white
                text-sm
                focus:border-blue-500
                focus:ring-0
                disabled:cursor-not-allowed
                disabled:bg-gray-100
            "
            :class="[
                inputClass,
                compact && hideLabel
                    ? 'px-2.5 py-1 text-xs leading-4'
                    : compact
                    ? 'px-2.5 pt-3 pb-1 text-xs leading-4'
                    : floatOnValue
                    ? (modelValue !== '' && modelValue !== null && modelValue !== undefined ? 'px-2 pt-2.5 pb-2' : 'px-2 py-2')
                    : 'px-2 pt-2.5 pb-2',
            ]"
        >

            <option v-if="showPlaceholder" value="">{{ placeholderText }}</option>

            <option
                v-for="item in options"
                :key="item[optionValue]"
                :value="item[optionValue]"
                
            >
                {{ item[optionLabel] }}
            </option>

        </select>

        <p
            v-if="error"
            class="text-red-500 text-sm mt-1"
        >
            {{ error }}
        </p>

        <button
            v-if="createLabel"
            type="button"
            class="mt-1 text-xs font-medium text-blue-700 hover:underline"
            @click="emit('create')"
        >
            + {{ createLabel }}
        </button>

        <label
            v-if="!hideLabel"
            class="pointer-events-none absolute left-3 bg-white px-1 text-gray-500 transition-all"
            :class="compact
                ? '-top-2 text-[11px] leading-none'
                : floatOnValue
                ? (modelValue !== '' && modelValue !== null && modelValue !== undefined
                    ? '-top-2 text-xs'
                    : 'top-2.5 text-sm peer-focus:-top-2 peer-focus:text-xs peer-focus:text-blue-600')
                : '-top-2 text-xs'"
        >
            {{ label }}
        </label>

    </div>

</template>
