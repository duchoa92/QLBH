<script setup>

defineProps({

    url: {
        type: String,
        required: true,
    },
})

</script>

<template>

    <div class="mb-4">

        <div
            class="text-sm text-gray-500 mb-2"
        >
            Quét QR để thanh toán
        </div>

        <img
            :src="url"
            class="w-56 mx-auto border rounded-lg shadow"
        />

    </div>

</template>