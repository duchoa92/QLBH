<script setup>
import { computed } from 'vue'
import { buildVietQrPayload, renderVietQrImage } from '@/Modules/POS/Payment/Utils/vietQr'

const props = defineProps({
    bankBin: {
        type: String,
        required: true,
    },
    bankAccount: {
        type: String,
        required: true,
    },
    amount: {
        type: Number,
        default: 0,
    },
    description: {
        type: String,
        default: '',
    },
    theme: {
        type: String,
        default: 'classic',
    },
})

const qrImage = computed(() => {
    const payload = buildVietQrPayload({
        bankBin: props.bankBin,
        bankAccount: props.bankAccount,
        amount: props.amount,
        description: props.description,
    })

    if (!payload) return ''

    return renderVietQrImage(payload, props.theme)
})
</script>

<template>
    <div class="flex flex-col items-center justify-center rounded-xl border border-slate-200/80 bg-slate-50/40 p-3 text-center">
        <div class="text-sm font-black text-emerald-600 mb-2">
            {{ $money(amount) }}
        </div>

        <div class="rounded-xl bg-white p-2 shadow-sm border border-slate-100">
            <img
                v-if="qrImage"
                :src="qrImage"
                alt="VietQR Payment"
                class="h-72 w-72 max-w-full object-contain rounded-lg sm:h-80 sm:w-80"
            />
            <p v-else class="px-3 py-8 text-sm font-semibold text-rose-600">
                BIN hoặc số tài khoản chưa đúng định dạng để tạo mã QR.
            </p>
        </div>
    </div>
</template>
