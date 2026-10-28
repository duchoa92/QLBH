<script setup>
import { ref, computed, watch, onBeforeUnmount, nextTick } from 'vue'
import { usePage } from '@inertiajs/vue3'
import PaymentMethodSelect from './PaymentMethodSelect.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import QrPayment from './QrPayment.vue'
import { X, CreditCard, Receipt, Loader2, CheckCircle2 } from 'lucide-vue-next'
import { formatCurrency, formatNumber } from '@/utils/format'
import { announceBankPayment, armBankPaymentAudio } from '@/Modules/POS/Payment/Utils/paymentAlerts'
import axios from 'axios'

const props = defineProps({
    loading: Boolean,
    show: Boolean,
    grandTotal: Number,
    invoiceCode: { type: String, default: '' },
    selectedCustomer: Object,
    cart: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits(['close', 'confirm', 'bank-completed', 'bank-completed-background', 'bank-transfer-partial'])

const paymentMethod = ref('cash')
const mixedPayment = ref(false)
const paidAmount = ref('')
const cashReceivedAmount = ref(0)
const note = ref('')
const payOldDebt = ref(false)
const bankIntent = ref(null)
const bankStatus = ref(null)
const bankError = ref('')
const bankStarting = ref(false)
const bankManualFallback = ref(false)
const bankCartSnapshot = ref('')
const qrPaymentSection = ref(null)
let bankPollTimer = null

const page = usePage()
const refreshedPaymentSettings = ref(null)
const refreshPaymentSettings = async () => {
    try {
        const { data } = await axios.get(route('pos.payment-settings'))
        refreshedPaymentSettings.value = data.settings
    } catch (error) {
        console.error('Không thể cập nhật cài đặt thanh toán POS.', error)
    }
}
const onPaymentSettingsStorage = event => {
    if (event.key === 'pos-payment-settings-updated') refreshPaymentSettings()
}
window.addEventListener('storage', onPaymentSettingsStorage)
onBeforeUnmount(() => window.removeEventListener('storage', onPaymentSettingsStorage))

const bankSettings = computed(() =>
    ({ ...(page.props.settings || {}), ...(refreshedPaymentSettings.value || {}) })
)

const bankAccounts = computed(() => {
    const configuredAccounts = bankSettings.value.bank_accounts
    if (Array.isArray(configuredAccounts) && configuredAccounts.length) return configuredAccounts
    if (bankSettings.value.bank_bin && bankSettings.value.bank_account) {
        return [{
            id: 'legacy-default',
            bank_bin: bankSettings.value.bank_bin,
            bank_name: '',
            bank_account: bankSettings.value.bank_account,
            bank_account_name: bankSettings.value.bank_account_name,
            is_default: true,
        }]
    }
    return []
})

const selectedBankAccountId = ref('')
watch(bankAccounts, accounts => {
    if (!accounts.some(account => String(account.id) === String(selectedBankAccountId.value))) {
        selectedBankAccountId.value = String(accounts.find(account => account.is_default)?.id ?? accounts[0]?.id ?? '')
    }
}, { immediate: true })

const selectedBankAccount = computed(() =>
    bankAccounts.value.find(account => String(account.id) === String(selectedBankAccountId.value))
        || bankAccounts.value.find(account => account.is_default)
        || bankAccounts.value[0]
        || null
)

watch(paymentMethod, () => {
    paidAmount.value = ''
    cashReceivedAmount.value = 0
    mixedPayment.value = false
})
watch(paymentMethod, async method => {
    if (method !== 'bank') return
    await nextTick()
    requestAnimationFrame(() => qrPaymentSection.value?.scrollIntoView({ behavior: 'smooth', block: 'center' }))
})
watch(mixedPayment, async enabled => {
    if (!enabled) return
    paidAmount.value = ''
    await nextTick()
    requestAnimationFrame(() => qrPaymentSection.value?.scrollIntoView({ behavior: 'smooth', block: 'center' }))
})

const totalNeedToPay = computed(() => {
    const debt = payOldDebt.value
        ? Number(props.selectedCustomer?.debt_balance || 0)
        : 0
    return Number(props.grandTotal || 0) + debt
})

const transferAmount = computed(() => {
    const total = Math.max(0, Number(totalNeedToPay.value || 0))
    const defaultAmount = mixedPayment.value ? Math.max(0, total - Number(cashReceivedAmount.value || 0)) : total
    const enteredAmount = paidAmount.value === '' || paidAmount.value === null ? defaultAmount : Number(paidAmount.value || 0)
    return Math.min(total, Math.max(0, enteredAmount))
})

const usesBankPayment = computed(() => paymentMethod.value === 'bank' || mixedPayment.value)
const autoBankEnabled = computed(() => usesBankPayment.value && Boolean(selectedBankAccount.value?.payment_auto_enabled))
const autoBankTracking = computed(() => autoBankEnabled.value && !bankManualFallback.value)

const effectivePaidAmount = computed(() =>
    usesBankPayment.value
        ? (autoBankTracking.value && bankIntent.value
            ? Number(bankStatus.value?.received_amount || 0) + (mixedPayment.value ? Number(cashReceivedAmount.value || 0) : 0)
            : (mixedPayment.value
                ? Number(paidAmount.value || transferAmount.value || 0) + Number(cashReceivedAmount.value || 0)
                : Number(paidAmount.value || transferAmount.value || 0)))
        : Number(paidAmount.value || 0)
)

const balanceAmount = computed(() => {
    return effectivePaidAmount.value - totalNeedToPay.value
})

const formatMoney = formatCurrency

const formatMoneyInput = (value) => {
    const number = Number(value || 0)
    return number > 0 ? formatNumber(number) : ''
}

const parseMoneyInput = (value) =>
    Number(String(value || '').replace(/\D/g, ''))

const handlePaidAmountInput = (event) => {
    paidAmount.value = parseMoneyInput(event.target.value)
}

const handleBankTransferAmountValue = (value) => {
    paidAmount.value = Math.min(Number(value || 0), Math.max(0, Math.round(totalNeedToPay.value)))
}

const handleCashReceivedAmountInput = (event) => {
    cashReceivedAmount.value = parseMoneyInput(event.target.value)
    bankError.value = ''
}

const handleCashAmountInput = (event) => {
    if (mixedPayment.value) handleCashReceivedAmountInput(event)
    else {
        handlePaidAmountInput(event)
        bankError.value = ''
    }
}

const enableRemainingQr = () => {
    const cashAmount = mixedPayment.value ? Number(cashReceivedAmount.value || 0) : Number(paidAmount.value || 0)
    if (cashAmount <= 0) {
        bankError.value = 'Nhập số tiền mặt trước để tạo mã QR cho phần còn lại.'
        return
    }
    if (cashAmount >= totalNeedToPay.value) {
        bankError.value = 'Tiền mặt đã đủ tổng cần thu; không còn khoản chuyển khoản để tạo QR.'
        return
    }
    cashReceivedAmount.value = cashAmount
    bankError.value = ''
    mixedPayment.value = true
}

const disableRemainingQr = () => {
    paidAmount.value = Number(cashReceivedAmount.value || 0)
    cashReceivedAmount.value = 0
    mixedPayment.value = false
}

const resetPaymentFields = () => {
    paymentMethod.value = 'cash'
    mixedPayment.value = false
    paidAmount.value = ''
    cashReceivedAmount.value = 0
    note.value = ''
    payOldDebt.value = false
    bankStatus.value = null
    bankError.value = ''
    bankManualFallback.value = false
    bankStarting.value = false
    bankCartSnapshot.value = ''
}

watch(() => props.show, shown => {
    // Keep an active bank intent intact when its modal is only hidden.
    if (shown && !bankIntent.value) resetPaymentFields()
})

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
    Boolean(selectedBankAccount.value?.bank_bin && selectedBankAccount.value?.bank_account)
)

const transferDescription = computed(() =>
    autoBankEnabled.value || selectedBankAccount.value?.transfer_content_mode === 'invoice'
        ? props.invoiceCode
        : ''
)

const paymentStatusLabel = computed(() => {
    if (bankStatus.value?.status === 'paid') return 'Đã nhận đủ tiền, hóa đơn đã tự hoàn tất.'
    if (bankStatus.value?.status === 'expired') return 'Đã hết thời gian chờ. Nếu khách đã chuyển, vui lòng kiểm tra lịch sử giao dịch.'
    if (bankStatus.value?.status === 'cancelled') return 'Đã dừng chờ giao dịch.'
    if (Number(bankStatus.value?.received_amount || 0) === 0) return 'Đang chờ ngân hàng báo giao dịch vào tài khoản…'
    const difference = Number(bankStatus.value?.difference || 0)
    if (difference < 0) return `Đã nhận thiếu ${formatMoney(Math.abs(difference))}. Đang tiếp tục chờ chuyển thêm.`
    if (difference > 0) return `Khách đã chuyển thừa ${formatMoney(difference)}.`
    return 'Đang chờ ngân hàng báo giao dịch vào tài khoản…'
})

const pollBankPayment = async () => {
    // Keep polling while the modal is hidden. The bank webhook can complete
    // after the cashier closes the QR dialog; the POS page still needs to
    // receive the completion event and show the sale/notification.
    if (!bankIntent.value) return
    try {
        const { data } = await axios.get(route('pos.bank-payments.status', bankIntent.value.id))
        const previousReceived = Number(bankStatus.value?.received_amount || 0)
        bankStatus.value = data.data
        const received = Number(data.data.received_amount || 0)
        const expected = Number(data.data.expected_amount || 0)
        if (received > previousReceived) {
            if (received < expected) {
                const remaining = expected - received
                announceBankPayment({ kind: 'partial', amount: remaining, language: bankSettings.value.payment_voice_language || 'vi-VN', speak: bankSettings.value.payment_voice_enabled !== false })
                emit('bank-transfer-partial', { received, expected, remaining })
            } else if (received > expected) {
                announceBankPayment({ kind: 'excess', amount: received - expected, language: bankSettings.value.payment_voice_language || 'vi-VN', speak: bankSettings.value.payment_voice_enabled !== false })
            } else {
                announceBankPayment({ kind: 'exact', language: bankSettings.value.payment_voice_language || 'vi-VN', speak: bankSettings.value.payment_voice_enabled !== false })
            }
        }
        if (data.data.status === 'paid' && data.data.sale) {
            bankIntent.value = null
            if (props.show) emit('bank-completed', data.data.sale)
            else emit('bank-completed-background', data.data.sale, bankCartSnapshot.value)
            return
        }
        if (data.data.status === 'waiting') bankPollTimer = setTimeout(pollBankPayment, 1500)
    } catch (error) {
        bankError.value = error.response?.data?.message || 'Không kiểm tra được trạng thái giao dịch.'
        bankPollTimer = setTimeout(pollBankPayment, 3000)
    }
}

const startBankPayment = async () => {
    if (bankStarting.value) return
    armBankPaymentAudio()
    bankStarting.value = true
    bankError.value = ''
    bankManualFallback.value = false
    try {
        const items = props.cart.map(item => ({
            id: item.id,
            price: item.price,
            quantity: item.quantity,
            unit_id: item.unit_id ?? null,
            unit_name: item.unit_name ?? 'Cái',
            conversion_factor: item.conversion_factor ?? 1,
            variant_id: item.variant_id ?? null,
            imei_id: item.imei_id ?? null,
            warranty_days: item.customer_warranty_days ?? item.warranty_days ?? null,
            warranty_value: item.customer_warranty_value ?? null,
            warranty_unit: item.customer_warranty_unit ?? null,
            warranty_note: item.customer_warranty_note ?? null,
            note: item.note,
            discount_type: item.discount_type ?? null,
            discount_value: Number(item.discount_value ?? 0),
            gifts: (item.gifts ?? []).map(gift => ({ id: gift.id, quantity: gift.quantity, variant_id: gift.variant_id ?? null, imei_id: gift.imei_id ?? null })),
        }))
        const { data } = await axios.post(route('pos.bank-payments.start'), {
            items,
            customer_id: props.selectedCustomer?.id ?? null,
            pay_old_debt: payOldDebt.value,
            invoice_code: props.invoiceCode,
            expected_amount: Math.round(transferAmount.value),
            total_amount: Math.round(totalNeedToPay.value),
            cash_received_amount: mixedPayment.value ? Math.round(cashReceivedAmount.value) : 0,
            bank_account: selectedBankAccount.value?.bank_account,
        })
        bankIntent.value = data.data
        bankCartSnapshot.value = JSON.stringify(props.cart)
        bankStatus.value = { status: 'waiting', expected_amount: data.data.expected_amount, received_amount: 0, difference: -data.data.expected_amount }
        bankPollTimer = setTimeout(pollBankPayment, 1200)
    } catch (error) {
        bankError.value = error.response?.data?.message || 'Không thể bắt đầu theo dõi thanh toán.'
        bankManualFallback.value = true
    } finally {
        bankStarting.value = false
    }
}

const closeModal = () => emit('close')

const submit = () => {
    if (mixedPayment.value && (Number(cashReceivedAmount.value) <= 0 || transferAmount.value <= 0)) {
        bankError.value = 'Vui lòng nhập số tiền lớn hơn 0 cho cả tiền mặt và chuyển khoản.'
        return
    }
    if (autoBankTracking.value) {
        if (!bankIntent.value) startBankPayment()
        return
    }
    emit('confirm', {
        payment_method: mixedPayment.value ? 'mixed' : paymentMethod.value,
        bank_account: usesBankPayment.value ? selectedBankAccount.value?.bank_account ?? null : null,
        invoice_code: props.invoiceCode,
        paid_amount: effectivePaidAmount.value,
        cash_received_amount: mixedPayment.value
            ? Number(cashReceivedAmount.value || 0)
            : (paymentMethod.value === 'cash' ? Number(paidAmount.value || 0) : 0),
        bank_received_amount: usesBankPayment.value
            ? (autoBankTracking.value ? Number(bankStatus.value?.received_amount || 0) : transferAmount.value)
            : 0,
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
                    @click="closeModal"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <!-- BODY -->
            <div class="flex-1 space-y-4 overflow-y-auto p-6 custom-scrollbar">

                <!-- PHƯƠNG THỨC THANH TOÁN -->
                <div>
                    <PaymentMethodSelect v-model="paymentMethod" :disabled="Boolean(bankIntent)" />
                </div>

                <!-- SỐ TIỀN MẶT KHÁCH ĐƯA VÀ NÚT TẠO QR PHẦN CÒN LẠI -->
                <div v-if="paymentMethod === 'cash'">
                    <div class="relative">
                        <FloatingInput
                            :model-value="formatMoneyInput(mixedPayment ? cashReceivedAmount : paidAmount)"
                            @input="handleCashAmountInput"
                            type="text"
                            inputmode="numeric"
                            :disabled="Boolean(bankIntent)"
                            label="Số tiền khách đưa"
                            input-class="pr-36"
                        />
                        <button
                            v-if="!mixedPayment"
                            type="button"
                            :disabled="Boolean(bankIntent)"
                            class="absolute right-1 top-1.5 rounded-md bg-blue-50 px-2 py-1.5 text-[10px] font-bold leading-tight text-blue-700 hover:bg-blue-100"
                            @click="enableRemainingQr"
                        >Tạo QR phần còn lại</button>
                        <button
                            v-else
                            type="button"
                            :disabled="Boolean(bankIntent)"
                            class="absolute right-1 top-1.5 rounded-md bg-slate-100 px-2 py-1.5 text-[10px] font-bold leading-tight text-slate-700 hover:bg-slate-200"
                            @click="disableRemainingQr"
                        >Chỉ nhận tiền mặt</button>
                    </div>
                    <p v-if="bankError" class="mt-1 text-xs font-semibold text-rose-600">{{ bankError }}</p>
                </div>

                <!-- MA QR THANH TOAN -->
                <div v-if="usesBankPayment">
                    <p v-if="mixedPayment" class="mb-2 inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-[11px] font-semibold text-blue-700">Thanh toán kết hợp · tiền mặt + chuyển khoản</p>
                    <div v-if="bankAccounts.length" class="mb-3">
                        <label for="checkout-bank-account" class="block min-w-0 text-xs font-semibold text-slate-600">
                            Tài khoản nhận tiền
                            <select id="checkout-bank-account" v-model="selectedBankAccountId" :disabled="Boolean(bankIntent)" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-normal focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100 disabled:bg-slate-100">
                                <option v-for="account in bankAccounts" :key="account.id" :value="String(account.id)">
                                    {{ account.bank_name || `BIN ${account.bank_bin}` }} · {{ account.bank_account }}{{ account.bank_account_name ? ` · ${account.bank_account_name}` : '' }}{{ account.is_default ? ' (Mặc định)' : '' }}
                                </option>
                            </select>
                        </label>
                    </div>
                    <div ref="qrPaymentSection">
                    <QrPayment
                        v-if="hasBankSettings"
                        :bank-bin="String(selectedBankAccount.bank_bin)"
                        :bank-account="String(selectedBankAccount.bank_account)"
                        :amount="transferAmount"
                        :description="transferDescription"
                        :provider-qr-url="bankIntent?.provider_qr_url || ''"
                        :provider-payment-url="bankIntent?.provider_payment_url || ''"
                        :editable="!bankIntent"
                        @amount-change="handleBankTransferAmountValue"
                        :theme="bankSettings.bank_qr_theme || 'classic'"
                    />
                    </div>

                    <div v-if="autoBankTracking" class="mt-3 rounded-xl border px-3 py-3 text-sm" :class="bankStatus?.status === 'paid' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : bankStatus?.difference > 0 ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-blue-200 bg-blue-50 text-blue-800'">
                        <p class="font-bold">{{ paymentStatusLabel }}</p>
                        <p v-if="bankStatus" class="mt-1 text-xs">Đã nhận {{ formatMoney(bankStatus.received_amount) }} / {{ formatMoney(bankStatus.expected_amount) }}</p>
                        <p v-else class="mt-1 text-xs">Nội dung chuyển khoản tự gắn mã {{ invoiceCode }} để tự đối soát.</p>
                        <p v-if="bankError" class="mt-1 text-xs font-semibold text-rose-700">{{ bankError }}</p>
                    </div>

                    <div
                        v-else-if="!autoBankTracking"
                        class="rounded-xl border border-amber-200 bg-amber-50/60 p-3 text-center text-xs font-semibold text-amber-800"
                    >
                        <template v-if="bankManualFallback">Không kết nối được dịch vụ đối soát. Bạn vẫn có thể nhập số tiền nhận và xác nhận thanh toán thủ công.</template>
                        <template v-else-if="!hasBankSettings">Chưa cấu hình tài khoản ngân hàng trong Cài đặt. Bạn vẫn có thể nhập số tiền nhận và xác nhận thủ công.</template>
                        <template v-else-if="selectedBankAccount?.payment_provider === 'apipay'">
                            ApiPay chưa đủ cấu hình. Hãy kiểm tra Bank Account Public ID, API keys và Webhook signing secret trong Cài đặt.
                        </template>
                        <template v-else-if="selectedBankAccount?.payment_provider === 'sepay'">
                            Tự đối soát SePay chưa bật. Hãy nhập API Key hoặc webhook secret cho tài khoản này trong Cài đặt.
                        </template>
                        <template v-else>Chưa chọn dịch vụ tự đối soát cho tài khoản này trong Cài đặt.</template>
                    </div>
                </div>

                <!-- THANH TOÁN NỢ CŨ (NẾU CÓ) -->
                <div
                    v-if="selectedCustomer && Number(selectedCustomer.debt_balance) > 0"
                    class="rounded-xl border border-blue-100 bg-blue-50/40 p-3"
                >
                    <label class="flex items-center gap-2 cursor-pointer select-none text-xs font-bold text-blue-900">
                        <input
                            v-model="payOldDebt"
                            type="checkbox"
                            class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-4 h-4"
                        />
                        <span>Cộng gộp nợ cũ ({{ formatMoney(selectedCustomer.debt_balance) }})</span>
                    </label>
                </div>

                <!-- Tiền nhận thủ công nằm sau QR và ngay trước ghi chú -->
                <div v-if="usesBankPayment">
                    <p v-if="mixedPayment" class="mt-1 text-xs text-slate-600">Tổng nhận: {{ formatMoney(effectivePaidAmount) }} / {{ formatMoney(totalNeedToPay) }}</p>
                    <p v-if="bankError" class="mt-1 text-xs font-semibold text-rose-600">{{ bankError }}</p>
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
                        <span class="font-bold text-slate-800">{{ formatMoney(grandTotal) }}</span>
                    </div>

                    <div class="flex justify-between text-sm font-extrabold text-blue-900 pt-2 border-t border-slate-200/60">
                        <span>Tổng thu</span>
                        <span class="text-base text-blue-600">{{ formatMoney(totalNeedToPay) }}</span>
                    </div>

                    <div class="flex justify-between text-xs font-bold pt-1">
                        <span class="text-slate-500">{{ balanceStatus.label }}</span>
                        <span :class="balanceStatus.className">
                            {{ formatMoney(balanceStatus.amount) }}
                        </span>
                    </div>
                </div>

            </div>

            <!-- FOOTER -->
            <div class="p-4 border-t border-slate-100 bg-slate-50/50 flex gap-2">
                <button
                    type="button"
                    class="flex-1 rounded-xl border border-slate-200/80 bg-white py-3 text-xs font-bold text-slate-600 hover:bg-slate-100 transition-colors"
                    @click="closeModal"
                >
                    {{ bankIntent ? 'Ẩn, tiếp tục theo dõi' : 'Hủy bỏ' }}
                </button>

                <button
                    :disabled="loading || bankStarting || Boolean(bankIntent)"
                    @click="submit"
                    class="flex-[2] inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 py-3 text-xs font-bold text-white shadow-md shadow-emerald-200 hover:bg-emerald-700 active:scale-95 transition-all disabled:opacity-50"
                >
                    <Loader2 v-if="loading" class="w-4 h-4 animate-spin" />
                    <CheckCircle2 v-else class="w-4 h-4" />
                    <span>{{ loading ? 'Đang xử lý...' : bankStarting ? 'Đang kết nối...' : bankIntent ? 'Đang chờ tiền vào...' : autoBankTracking ? 'Bắt đầu theo dõi chuyển khoản' : 'Xác nhận thanh toán' }}</span>
                </button>
            </div>

        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
</style>
