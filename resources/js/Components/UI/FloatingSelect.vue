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



</script>

<template>

    <div class="relative w-full">

        <select
            :name="name"
            :id="name"
            ref="input"
            :value="modelValue"
            @change="emit('update:modelValue', $event.target.value ? Number($event.target.value) : null)"
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
        <!-- Lỗi-->
        <p v-if="error" class="text-red-500 text-sm mt-1">
            {{ error }}
        </p>

        <label class="absolute left-3 bg-white px-1 text-gray-500 transition-all -top-2 text-xs">
            {{ label }}
        </label>

    </div>

</template>