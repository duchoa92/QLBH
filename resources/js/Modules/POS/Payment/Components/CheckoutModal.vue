<script setup>
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import PaymentMethodSelect from './PaymentMethodSelect.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import QrPayment from './QrPayment.vue'
import { X, CreditCard, Receipt, Loader2, CheckCircle2 } from 'lucide-vue-next'

const props = defineProps({
    loading: Boolean,
    show: Boolean,
    grandTotal: Number,
    selectedCustomer: Object,
    cart: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits(['close', 'confirm'])

const paymentMethod = ref('cash')
const paidAmount = ref('')
const note = ref('')
const payOldDebt = ref(false)

const page = usePage()

const bankSettings = computed(() =>
    page.props.settings || {}
)

const totalNeedToPay = computed(() => {
    const debt = payOldDebt.value
        ? Number(props.selectedCustomer?.debt_balance || 0)
        : 0
    return Number(props.grandTotal || 0) + debt
})

const transferAmount = computed(() =>
    Math.max(0, Number(totalNeedToPay.value || 0))
)

const effectivePaidAmount = computed(() =>
    paymentMethod.value === 'bank'
        ? Number(paidAmount.value || transferAmount.value || 0)
        : Number(paidAmount.value || 0)
)

const balanceAmount = computed(() => {
    return effectivePaidAmount.value - totalNeedToPay.value
})

const formatMoney = (value) => {
    return Number(value || 0).toLocaleString('vi-VN')
}

const formatMoneyInput = (value) => {
    const number = Number(value || 0)
    return number > 0 ? number.toLocaleString('vi-VN') : ''
}

const parseMoneyInput = (value) =>
    Number(String(value || '').replace(/\D/g, ''))

const handlePaidAmountInput = (event) => {
    paidAmount.value = parseMoneyInput(event.target.value)
}

const balanceStatus = computed(() => {
    if (balanceAmount.value < 0) {
        return {
            label: 'Còn thiếu',
            amount: Math.abs(balanceAmount.value),
            className: 'text-rose-600',
        }
    }

    if (balanceAmount.value > 0) {
        return {
            label: 'Tiền thừa',
            amount: balanceAmount.value,
            className: 'text-emerald-600',
        }
    }

    return {
        label: 'Đã thanh toán đủ',
        amount: 0,
        className: 'text-slate-600',
    }
})

const hasBankSettings = computed(() =>
    Boolean(bankSettings.value.bank_bin && bankSettings.value.bank_account)
)

const vietQrUrl = computed(() => {
    if (!hasBankSettings.value) return ''

    const description = encodeURIComponent(
        bankSettings.value.bank_transfer_content || 'Thanh toan don hang'
    )

    return `https://img.vietqr.io/image/${bankSettings.value.bank_bin}-${bankSettings.value.bank_account}-compact2.png?amount=${transferAmount.value}&addInfo=${description}`
})

const submit = () => {
    emit('confirm', {
        payment_method: paymentMethod.value,
        paid_amount: effectivePaidAmount.value,
        note: note.value,
        pay_old_debt: payOldDebt.value,
    })
}
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm animate-fade-in"
    >
        <div class="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white shadow-2xl border border-slate-100">

            <!-- HEADER -->
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 bg-slate-50/50">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-emerald-50 text-emerald-600 rounded-xl">
                        <Receipt class="w-5 h-5" />
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Xác nhận thanh toán</h2>
                        <p class="text-xs text-slate-500 font-medium">Hoàn tất đơn hàng POS</p>
                    </div>
                </div>

                <button
                    type="button"
                    class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors"
                    @click="emit('close')"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <!-- BODY -->
            <div class="flex-1 space-y-4 overflow-y-auto p-6 custom-scrollbar">

                <!-- PHƯƠNG THỨC THANH TOÁN -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">
                        Phương thức thanh toán
                    </label>
                    <PaymentMethodSelect v-model="paymentMethod" />
                </div>

                <!-- SỐ TIỀN KHÁCH ĐƯA -->
                <div>
                    <FloatingInput
                        :model-value="formatMoneyInput(paidAmount)"
                        @input="handlePaidAmountInput"
                        type="text"
                        inputmode="numeric"
                        :label="paymentMethod === 'bank' ? 'Số tiền chuyển khoản' : 'Số tiền khách đưa'"
                    />
                </div>

                <!-- MA QR THANH TOAN -->
                <div v-if="paymentMethod === 'bank'">
                    <QrPayment
                        v-if="vietQrUrl"
                        :url="vietQrUrl"
                        :amount="transferAmount"
                        :account="bankSettings.bank_account"
                        :account-name="bankSettings.bank_account_name"
                    />

                    <div
                        v-else
                        class="rounded-xl border border-amber-200 bg-amber-50/60 p-3 text-xs font-semibold text-amber-700 text-center"
                    >
                        Chưa cấu hình tài khoản ngân hàng trong Cài đặt.
                    </div>
                </div>

                <!-- THANH TOÁN NỢ CŨ (NẾU CÓ) -->
                <div
                    v-if="selectedCustomer && Number(selectedCustomer.debt_balance) > 0"
                    class="rounded-xl border border-indigo-100 bg-indigo-50/40 p-3"
                >
                    <label class="flex items-center gap-2 cursor-pointer select-none text-xs font-bold text-indigo-900">
                        <input
                            v-model="payOldDebt"
                            type="checkbox"
                            class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4"
                        />
                        <span>Cộng gộp nợ cũ ({{ formatMoney(selectedCustomer.debt_balance) }}đ)</span>
                    </label>
                </div>

                <!-- GHI CHÚ -->
                <div>
                    <FloatingInput
                        v-model="note"
                        label="Ghi chú đơn hàng..."
                    />
                </div>

                <!-- TỔNG KẾT TÍNH TOÁN -->
                <div class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-4 space-y-2">
                    <div class="flex justify-between text-xs font-medium text-slate-600">
                        <span>Tiền hàng</span>
                        <span class="font-bold text-slate-800">{{ formatMoney(grandTotal) }}đ</span>
                    </div>

                    <div class="flex justify-between text-sm font-extrabold text-indigo-900 pt-2 border-t border-slate-200/60">
                        <span>Tổng thu</span>
                        <span class="text-base text-indigo-600">{{ formatMoney(totalNeedToPay) }}đ</span>
                    </div>

                    <div class="flex justify-between text-xs font-bold pt-1">
                        <span class="text-slate-500">{{ balanceStatus.label }}</span>
                        <span :class="balanceStatus.className">
                            {{ formatMoney(balanceStatus.amount) }}đ
                        </span>
                    </div>
                </div>

            </div>

            <!-- FOOTER -->
            <div class="p-4 border-t border-slate-100 bg-slate-50/50 flex gap-2">
                <button
                    type="button"
                    class="flex-1 rounded-xl border border-slate-200/80 bg-white py-3 text-xs font-bold text-slate-600 hover:bg-slate-100 transition-colors"
                    @click="emit('close')"
                >
                    Hủy bỏ
                </button>

                <button
                    :disabled="loading"
                    @click="submit"
                    class="flex-[2] inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 py-3 text-xs font-bold text-white shadow-md shadow-emerald-200 hover:bg-emerald-700 active:scale-95 transition-all disabled:opacity-50"
                >
                    <Loader2 v-if="loading" class="w-4 h-4 animate-spin" />
                    <CheckCircle2 v-else class="w-4 h-4" />
                    <span>{{ loading ? 'Đang xử lý...' : 'Xác nhận thanh toán' }}</span>
                </button>
            </div>

        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
</style>