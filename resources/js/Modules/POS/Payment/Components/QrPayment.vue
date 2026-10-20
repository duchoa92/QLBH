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
    providerQrUrl: { type: String, default: '' },
    providerPaymentUrl: { type: String, default: '' },
    theme: {
        type: String,
        default: 'classic',
    },
})

const qrImage = computed(() => {
    if (props.providerQrUrl) return props.providerQrUrl
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
    <div class="relative isolate overflow-hidden rounded-2xl border border-emerald-100 bg-gradient-to-br from-white via-emerald-50/60 to-teal-50/70 p-4 text-center shadow-sm sm:p-5">
        <div class="pointer-events-none absolute -right-10 -top-10 -z-10 h-32 w-32 rounded-full bg-emerald-200/30 blur-2xl" />

        <div class="mb-3 flex items-center justify-center gap-2">
            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                    <path d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3M8 8h2v2H8zM14 8h2v2h-2zM8 14h2v2H8zM14 14h2v2h-2z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </span>
            <span class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-800">Quét mã để thanh toán</span>
        </div>

        <div class="mx-auto w-fit rounded-[1.35rem] border border-white bg-white p-2.5 shadow-[0_12px_35px_-18px_rgba(15,118,110,0.42)] ring-1 ring-emerald-900/5 sm:p-3">
            <img
                v-if="qrImage"
                :src="qrImage"
                alt="VietQR Payment"
                class="h-64 w-64 max-w-full rounded-xl object-contain sm:h-72 sm:w-72"
            />
            <p v-else class="w-64 px-3 py-8 text-sm font-semibold text-rose-600 sm:w-72">
                BIN hoặc số tài khoản chưa đúng định dạng để tạo mã QR.
            </p>
        </div>

        <div class="mt-4">
            <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Số tiền chuyển</div>
            <div class="mt-1 inline-flex items-baseline gap-1 rounded-full border border-emerald-100 bg-white/85 px-4 py-1.5 text-xl font-black tracking-tight text-emerald-800 shadow-sm">
                {{ $money(amount) }}
            </div>
            <p class="mt-2 text-xs font-medium text-slate-500">Mở ứng dụng ngân hàng và quét mã phía trên</p>
            <a v-if="providerPaymentUrl" :href="providerPaymentUrl" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex text-xs font-semibold text-violet-700 underline">Mở trang thanh toán ApiPay</a>
        </div>
    </div>
</template>
