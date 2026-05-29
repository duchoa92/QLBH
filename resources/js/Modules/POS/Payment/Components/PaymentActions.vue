<script setup>

defineProps({

    loading: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits([
    'close',
    'confirm',
])

</script>

<template>

    <div
        class="sticky bottom-0 bg-white pt-3 flex justify-end gap-2"
    >

        <button
            @click="emit('close')"
            class="px-4 py-2 border rounded"
        >
            Đóng
        </button>

        <button
            @click="emit('confirm')"

            :disabled="loading"

            class="px-4 py-2 bg-blue-600 text-white rounded disabled:opacity-50"
        >

            <span v-if="loading">
                Đang xử lý...
            </span>

            <span v-else>
                Xác nhận thanh toán
            </span>

        </button>

    </div>

</template>