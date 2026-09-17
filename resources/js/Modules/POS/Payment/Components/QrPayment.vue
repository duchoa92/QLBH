<script setup>
import { QrCode } from 'lucide-vue-next'

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
    <div class="flex flex-col items-center justify-center rounded-xl border border-slate-200/80 bg-slate-50/40 p-4 text-center">
        <div class="flex items-center gap-1.5 text-xs font-bold text-slate-500 mb-2">
            <QrCode class="w-4 h-4 text-indigo-600" />
            <span>Quét mã VietQR thanh toán</span>
        </div>

        <div v-if="account" class="text-xs font-extrabold text-slate-800">
            {{ accountName || 'Tài khoản' }} · <span class="font-mono text-indigo-600">{{ account }}</span>
        </div>

        <div class="text-sm font-black text-emerald-600 my-1">
            {{ Number(amount || 0).toLocaleString('vi-VN') }}đ
        </div>

        <div class="mt-2 rounded-xl bg-white p-2 shadow-sm border border-slate-100">
            <img
                :src="url"
                alt="VietQR Payment"
                class="w-48 h-48 object-contain rounded-lg"
            />
        </div>
    </div>
</template>