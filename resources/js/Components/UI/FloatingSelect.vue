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

})

const emit = defineEmits([
    'update:modelValue',
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

    <div class="relative w-full">

        <select
            :name="name"
            :id="name"
            ref="input"
            :value="modelValue ?? ''"
            @change="handleChange"
            class="
                peer
                w-full
                border
                border-gray-300
                rounded-lg
                px-2 pt-2.5 pb-2
                bg-white
                text-sm
                focus:border-blue-500
                focus:ring-0
            "
        >

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