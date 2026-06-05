<script setup>

defineProps({

    modelValue: {
        type: String,
        default: 'cash',
    },
})

const emit = defineEmits([
    'update:modelValue',
])

</script>

<template>

    <div class="mb-4">

        <select
            :value="modelValue"

            @change="
                emit(
                    'update:modelValue',
                    $event.target.value
                )
            "

            class="w-full border rounded p-2"
        >

            <option value="cash">
                Tiền mặt
            </option>

            <option value="bank">
                Chuyển khoản
            </option>

            <option value="card">
                Thẻ
            </option>

        </select>

    </div>

</template>