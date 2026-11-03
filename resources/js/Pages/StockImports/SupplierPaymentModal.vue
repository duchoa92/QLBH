<script setup>
import { computed, ref, watch } from 'vue'
import BaseModal from '@/Components/UI/BaseModal.vue'
import { formatNumber } from '@/utils/format'
import QrPayment from '@/Modules/POS/Payment/Components/QrPayment.vue'

const props = defineProps({
    supplier: { type: Object, required: true },
    total: { type: Number, required: true },
})

const emit = defineEmits(['close', 'updated'])
const settlement = ref('bank')
const paidAmount = ref(Number(props.total || 0))
const payOldDebt = ref(false)
const paymentReference = `NCC${props.supplier.id}-${Date.now().toString().slice(-8)}`
const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent)
const oldDebt = computed(() => Math.max(0, Number(props.supplier.debt_balance || 0)))
const bankQrReady = computed(() => /^\d{6}$/.test(String(props.supplier.bank_bin || ''))
    && /^[a-zA-Z0-9]{1,50}$/.test(String(props.supplier.bank_account || '')))
const transferAmount = computed(() => Math.min(Math.max(0, Number(paidAmount.value || 0)), Number(props.total || 0))
    + (payOldDebt.value ? oldDebt.value : 0))

watch(() => props.total, value => {
    if (settlement.value === 'bank') paidAmount.value = Number(value || 0)
})

const dueAmount = computed(() => settlement.value === 'debt'
    ? Number(props.total || 0)
    : Math.max(0, Number(props.total || 0) - Math.min(Number(paidAmount.value || 0), Number(props.total || 0))))

const chooseBank = () => {
    settlement.value = 'bank'
    paidAmount.value = Number(props.total || 0)
}

const chooseDebt = () => {
    settlement.value = 'debt'
    paidAmount.value = 0
    payOldDebt.value = false
}

const updateAmount = event => {
    paidAmount.value = Number(String(event.target.value).replace(/\D/g, '') || 0)
}

const submit = () => {
    const total = Number(props.total || 0)
    emit('updated', {
        payment_method: settlement.value === 'bank' ? 'bank' : 'cash',
        paid_amount: settlement.value === 'debt' ? 0 : Math.min(Math.max(0, Number(paidAmount.value || 0)), total),
        pay_old_debt: settlement.value === 'bank' && payOldDebt.value,
        payment_reference: paymentReference,
        settlement: settlement.value,
    })
    emit('close')
}
</script>

<template>
    <BaseModal title="Thanh toán nhà cung cấp" size="md" layer-class="z-[1200]" body-class="space-y-3 p-4 sm:p-5" @close="emit('close')">
        <section class="rounded-xl bg-slate-50 px-3 py-3">
            <div class="text-xs text-slate-500">Nhà cung cấp</div>
            <div class="mt-0.5 font-bold text-slate-900">{{ supplier.name }}</div>
            <div class="mt-3 flex items-center justify-between border-t border-slate-200 pt-2.5 text-sm">
                <span class="text-slate-600">Tổng tiền phiếu</span>
                <strong class="text-lg text-slate-900">{{ formatNumber(total) }} đ</strong>
            </div>
        </section>

        <div class="grid gap-2 sm:grid-cols-2">
            <button type="button" class="rounded-xl border p-3 text-left text-sm font-semibold transition" :class="settlement === 'bank' ? 'border-blue-500 bg-blue-50 text-blue-800 ring-1 ring-blue-300' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'" @click="chooseBank">
                Chuyển khoản
            </button>
            <button type="button" class="rounded-xl border p-3 text-left text-sm font-semibold transition" :class="settlement === 'debt' ? 'border-amber-500 bg-amber-50 text-amber-800 ring-1 ring-amber-300' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'" @click="chooseDebt">
                Ghi nợ nhà cung cấp
            </button>
        </div>

        <section v-if="settlement === 'bank'" class="space-y-3 rounded-xl border border-blue-200 bg-blue-50/50 p-3">
            <label class="block text-sm font-semibold text-slate-700">
                Số tiền cần chuyển
                <input :value="formatNumber(paidAmount)" inputmode="numeric" class="mt-1.5 h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-base font-bold text-slate-900 outline-none focus:border-blue-500" @input="updateAmount" />
            </label>
            <label v-if="oldDebt > 0" class="flex cursor-pointer items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-900">
                <input v-model="payOldDebt" type="checkbox" class="mt-0.5 rounded border-amber-300 text-amber-600 focus:ring-amber-500" />
                <span><strong>Trả nợ cũ</strong><span class="block text-xs text-amber-700">Cộng {{ formatNumber(oldDebt) }} đ vào số tiền chuyển khoản.</span></span>
            </label>
            <div class="rounded-lg bg-white p-3 text-sm">
                <div class="font-semibold text-slate-800">Thông tin tài khoản nhà cung cấp</div>
                <div v-if="supplier.bank_account" class="mt-2 grid gap-1 text-slate-600">
                    <div>Ngân hàng: <strong class="text-slate-900">{{ supplier.bank_name || '—' }}</strong></div>
                    <div>Số tài khoản: <strong class="select-all text-slate-900">{{ supplier.bank_account }}</strong></div>
                    <div>Chủ tài khoản: <strong class="text-slate-900">{{ supplier.bank_account_name || '—' }}</strong></div>
                </div>
                <p v-else class="mt-1 text-amber-700">Nhà cung cấp chưa khai báo tài khoản ngân hàng.</p>
            </div>
            <div class="rounded-lg bg-white p-3 text-sm">
                <div class="flex items-center justify-between gap-3"><span class="font-semibold text-slate-800">Nội dung chuyển khoản</span><strong class="select-all text-blue-700">{{ paymentReference }}</strong></div>
                <div v-if="bankQrReady" class="mt-3">
                    <QrPayment :bank-bin="String(supplier.bank_bin)" :bank-account="String(supplier.bank_account)" :amount="transferAmount" :description="paymentReference" compact />
                </div>
                <p v-else class="mt-2 text-amber-700">Cần nhập mã BIN gồm 6 chữ số và số tài khoản để tạo mã QR.</p>
                <a v-if="isMobile && supplier.bank_payment_url" :href="supplier.bank_payment_url" class="mt-3 inline-flex min-h-10 items-center rounded-lg bg-emerald-600 px-4 text-sm font-bold text-white hover:bg-emerald-700">Mở ứng dụng ngân hàng</a>
            </div>
        </section>

        <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2.5 text-sm">
            <span class="text-slate-600">{{ settlement === 'debt' ? 'Ghi nợ nhà cung cấp' : 'Còn nợ sau thanh toán' }}</span>
            <strong :class="dueAmount > 0 ? 'text-rose-600' : 'text-emerald-600'">{{ formatNumber(dueAmount) }} đ</strong>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <button type="button" class="min-h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="emit('close')">Hủy</button>
                <button type="button" class="min-h-10 rounded-lg bg-blue-600 px-5 text-sm font-bold text-white hover:bg-blue-700" @click="submit">Xác nhận và lưu phiếu</button>
            </div>
        </template>
    </BaseModal>
</template>
