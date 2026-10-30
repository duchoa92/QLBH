<script setup>
import { computed, nextTick, ref, watch } from 'vue'
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
    editable: { type: Boolean, default: false },
    compact: { type: Boolean, default: false },
    theme: {
        type: String,
        default: 'classic',
    },
})

const emit = defineEmits(['amount-change'])
const editingAmount = ref(false)
const editedAmount = ref('')
const amountInput = ref(null)

watch(() => props.amount, amount => {
    if (!editingAmount.value) editedAmount.value = String(Math.round(Number(amount || 0)))
}, { immediate: true })

const beginEditAmount = async () => {
    if (!props.editable) return
    editingAmount.value = true
    editedAmount.value = String(Math.round(Number(props.amount || 0)))
    await nextTick()
    amountInput.value?.focus()
    amountInput.value?.select()
}

const updateAmount = event => {
    const digits = String(event.target.value || '').replace(/\D/g, '')
    editedAmount.value = digits
    emit('amount-change', Number(digits || 0))
}

const finishEditAmount = () => {
    editingAmount.value = false
}

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
    <div class="relative isolate overflow-hidden rounded-2xl border border-emerald-100 bg-gradient-to-br from-white via-emerald-50/60 to-teal-50/70 text-center shadow-sm" :class="compact ? 'mx-auto w-fit p-3' : 'p-4 sm:p-5'">
        <div v-if="!compact" class="pointer-events-none absolute -right-10 -top-10 -z-10 h-32 w-32 rounded-full bg-emerald-200/30 blur-2xl" />

        <div v-if="!compact" class="mb-3 flex items-center justify-center gap-2">
            <span class="inline-flex items-center justify-center rounded-full bg-emerald-100 text-emerald-700" :class="compact ? 'h-6 w-6' : 'h-7 w-7'">
                <svg viewBox="0 0 24 24" fill="none" :class="compact ? 'h-3.5 w-3.5' : 'h-4 w-4'" aria-hidden="true">
                    <path d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3M8 8h2v2H8zM14 8h2v2h-2zM8 14h2v2H8zM14 14h2v2h-2z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </span>
            <span class="text-xs font-bold uppercase tracking-wide text-emerald-800">Quét mã thanh toán</span>
        </div>

        <div class="mx-auto w-fit rounded-xl border border-white bg-white p-1.5 shadow-sm ring-1 ring-emerald-900/5" :class="compact ? '' : 'rounded-[1.35rem] p-2.5 shadow-[0_12px_35px_-18px_rgba(15,118,110,0.42)] sm:p-3'">
            <img
                v-if="qrImage"
                :src="qrImage"
                alt="VietQR Payment"
                class="max-w-full rounded-lg object-contain"
                :class="compact ? 'aspect-square h-[132px] w-[132px]' : 'aspect-square h-64 w-64 rounded-xl sm:h-72 sm:w-72'"
            />
            <p v-else class="px-3 text-sm font-semibold text-rose-600" :class="compact ? 'flex aspect-square h-[132px] w-[132px] items-center text-[10px]' : 'w-64 py-8 sm:w-72'">
                BIN cần đủ 6 chữ số; mã tài khoản chỉ gồm chữ và số (tối đa 50 ký tự).
            </p>
        </div>

        <div :class="compact ? 'mt-2' : 'mt-4'">
            <div class="font-semibold uppercase tracking-wider text-slate-500" :class="compact ? 'text-[9px]' : 'text-[11px]'">Số tiền chuyển</div>
            <input
                v-if="editable && editingAmount"
                ref="amountInput"
                :value="editedAmount"
                type="text"
                inputmode="numeric"
                aria-label="Số tiền chuyển khoản"
                class="mt-1 rounded-full border border-emerald-300 bg-white text-center font-black tracking-tight text-emerald-800 shadow-sm outline-none focus:ring-2 focus:ring-emerald-200"
                :class="compact ? 'w-full px-2 py-1 text-sm' : 'w-48 px-4 py-1.5 text-xl'"
                @input="updateAmount"
                @blur="finishEditAmount"
                @keydown.enter.prevent="finishEditAmount"
            />
            <button
                v-else-if="editable"
                type="button"
                title="Nhấn để sửa số tiền chuyển khoản"
                class="mt-1 inline-flex items-baseline gap-1 rounded-full border border-emerald-100 bg-white/85 font-black tracking-tight text-emerald-800 shadow-sm hover:border-emerald-300 hover:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-200"
                :class="compact ? 'px-3 py-1 text-sm' : 'px-4 py-1.5 text-xl'"
                @click="beginEditAmount"
            >
                {{ $money(amount) }}
            </button>
            <div v-else class="mt-1 inline-flex items-baseline gap-1 rounded-full border border-emerald-100 bg-white/85 font-black tracking-tight text-emerald-800 shadow-sm" :class="compact ? 'px-3 py-1 text-sm' : 'px-4 py-1.5 text-xl'">
                {{ $money(amount) }}
            </div>
            <p v-if="editable && !compact" class="mt-1 text-[11px] text-slate-500">Nhấn vào số tiền để chỉnh sửa</p>
            <p v-if="compact" class="mt-1 text-[9px] leading-4 text-slate-500">Mở ứng dụng ngân hàng để quét mã</p>
            <a v-if="providerPaymentUrl" :href="providerPaymentUrl" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex text-xs font-semibold text-violet-700 underline">Mở trang thanh toán ApiPay</a>
        </div>
    </div>
</template>
