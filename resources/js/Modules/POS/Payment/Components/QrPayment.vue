<script setup>

defineProps({

    url: {
        type: String,
        required: true,
    },

    amount: {
        type: Number,
        default: 0,
    },

    account: {
        type: String,
        default: '',
    },

    accountName: {
        type: String,
        default: '',
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

        <div class="mb-2 text-center text-sm font-semibold text-slate-700">
            <div v-if="account">
                {{ accountName || 'Tài khoản nhận' }} - {{ account }}
            </div>
            <div class="text-green-700">
                {{ Number(amount || 0).toLocaleString('vi-VN') }} đ
            </div>
        </div>

        <img
            :src="url"
            class="w-56 mx-auto border rounded-lg shadow"
        />

    </div>

</template>
