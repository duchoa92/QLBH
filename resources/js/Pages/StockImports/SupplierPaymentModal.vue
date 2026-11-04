<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import BaseModal from '@/Components/UI/BaseModal.vue'
import { formatNumber } from '@/utils/format'
import QrPayment from '@/Modules/POS/Payment/Components/QrPayment.vue'
import api from '@/Services/api'

const props = defineProps({
    supplier: { type: Object, required: true },
    total: { type: Number, required: true },
})

const emit = defineEmits(['close', 'updated'])
const page = usePage()
const settlement = ref('cash')
// Để trống nghĩa là chưa thanh toán; phần còn lại tự trở thành công nợ.
const tenderedAmount = ref('')
const payOldDebt = ref(false)
const oldDebtOrders = ref([])
const loadingOldDebtOrders = ref(false)
const bankAccounts = computed(() => props.supplier.bank_accounts?.length
    ? props.supplier.bank_accounts
    : (props.supplier.bank_account ? [{
        bank_name: props.supplier.bank_name, bank_bin: props.supplier.bank_bin,
        bank_account: props.supplier.bank_account, bank_account_name: props.supplier.bank_account_name,
        bank_payment_url: props.supplier.bank_payment_url, is_default: true,
    }] : []))
const selectedBankAccount = ref(null)
if (bankAccounts.value.length) selectedBankAccount.value = bankAccounts.value.find(account => account.is_default) || bankAccounts.value[0]
const paymentReference = `NCC${props.supplier.id}-${Date.now().toString().slice(-8)}`
const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent)
const platform = /iPhone|iPad|iPod/i.test(navigator.userAgent) ? 'ios' : 'android'
const paymentApps = ref([])
const selectedPaymentAppId = ref('')
const selectedPaymentApp = computed(() => paymentApps.value.find(app => app.app_id === selectedPaymentAppId.value) || null)
const loadingPaymentApps = ref(false)
const paymentAppError = ref('')
onMounted(async () => {
    if (!isMobile) return
    loadingPaymentApps.value = true
    try {
        const { data } = await api.get(`/vietqr/apps/${platform}`)
        paymentApps.value = Array.isArray(data) ? data : []
        const savedAppId = window.localStorage.getItem('supplier-payment-bank-app') || ''
        selectedPaymentAppId.value = paymentApps.value.some(app => app.app_id === savedAppId) ? savedAppId : ''
    } catch {
        paymentAppError.value = 'Không tải được danh sách ứng dụng ngân hàng.'
    } finally {
        loadingPaymentApps.value = false
    }
})
const oldDebt = computed(() => Math.max(0, Number(props.supplier.debt_balance || 0)))
const bankQrReady = computed(() => /^\d{6}$/.test(String(selectedBankAccount.value?.bank_bin || ''))
    && /^[a-zA-Z0-9]{1,50}$/.test(String(selectedBankAccount.value?.bank_account || '')))
const oldDebtToPay = computed(() => payOldDebt.value
    ? oldDebtOrders.value.reduce((sum, order) => sum + Math.max(0, Number(order.payment_amount || 0)), 0)
    : 0)
const amountTendered = computed(() => Math.max(0, Number(tenderedAmount.value || 0)))
const amountApplied = computed(() => settlement.value === 'debt'
    ? 0
    : Math.min(amountTendered.value, Number(props.total || 0) + oldDebtToPay.value))
const paidAmount = computed(() => Math.min(Number(props.total || 0), amountApplied.value))
const oldDebtPaid = computed(() => Math.min(oldDebtToPay.value, Math.max(0, amountApplied.value - Number(props.total || 0))))
const oldDebtAllocations = computed(() => {
    let remaining = oldDebtPaid.value
    return oldDebtOrders.value.reduce((allocations, order) => {
        const amount = Math.min(remaining, Math.max(0, Number(order.payment_amount || 0)))
        if (amount > 0) allocations.push({ stock_import_id: order.id, amount })
        remaining -= amount
        return allocations
    }, [])
})
const changeAmount = computed(() => settlement.value === 'cash'
    ? Math.max(0, amountTendered.value - Number(props.total || 0) - oldDebtToPay.value)
    : 0)
const transferAmount = computed(() => amountApplied.value)
const paymentDeepLink = computed(() => {
    const account = selectedBankAccount.value
    if (!account?.bank_bin || !account?.bank_account) return ''
    const bank = (page.props.vietqr_banks || []).find(item => String(item.bin) === String(account.bank_bin))
    const bankCode = String(bank?.code || '').toLowerCase()
    if (!bankCode) return ''
    if (!selectedPaymentAppId.value) return ''
    const params = new URLSearchParams({ app: selectedPaymentAppId.value, ba: `${account.bank_account}@${bankCode}`, am: String(Math.round(transferAmount.value)), tn: paymentReference, url: window.location.origin })
    if (account.bank_account_name) params.set('bn', account.bank_account_name)
    return `https://dl.vietqr.io/pay?${params.toString()}`
})
watch(selectedPaymentAppId, value => {
    if (!value) return
    try { window.localStorage.setItem('supplier-payment-bank-app', value) } catch { /* storage is optional */ }
})

const dueAmount = computed(() => Math.max(0, oldDebt.value + Number(props.total || 0) - paidAmount.value - oldDebtPaid.value))

const chooseSettlement = (method) => {
    settlement.value = method
    if (method === 'debt') {
        tenderedAmount.value = 0
        payOldDebt.value = false
        return
    }
    tenderedAmount.value = ''
}

const toggleOldDebt = (enabled) => {
    payOldDebt.value = enabled
    if (enabled) loadOldDebtOrders()
    else oldDebtOrders.value.forEach(order => { order.selected = false; order.payment_amount = 0 })
}

const parseAmount = value => Number(String(value || '').replace(/\D/g, '') || 0)
const updateTendered = event => {
    tenderedAmount.value = parseAmount(event.target.value)
}
const loadOldDebtOrders = async () => {
    if (loadingOldDebtOrders.value || oldDebtOrders.value.length) return
    loadingOldDebtOrders.value = true
    try {
        const { data } = await api.get(`/stock-import/suppliers/${props.supplier.id}/outstanding-orders`)
        oldDebtOrders.value = (data.orders || []).map(order => ({ ...order, selected: false, payment_amount: 0 }))
    } catch (error) {
        console.error('Không thể tải phiếu nhập còn nợ:', error)
    } finally {
        loadingOldDebtOrders.value = false
    }
}
const updateOldDebt = (order, event) => {
    order.payment_amount = Math.min(Number(order.remaining || 0), parseAmount(event.target.value))
    order.selected = Number(order.payment_amount) > 0
}
const tenderedDisplay = computed(() => tenderedAmount.value === '' || tenderedAmount.value === null
    ? ''
    : formatNumber(tenderedAmount.value))

const submit = () => {
    const total = Number(props.total || 0)
    emit('updated', {
        payment_method: settlement.value === 'bank' ? 'bank' : 'cash',
        paid_amount: settlement.value === 'debt' ? 0 : Math.min(Math.max(0, Number(paidAmount.value || 0)), total),
        old_debt_paid: settlement.value === 'debt' ? 0 : oldDebtPaid.value,
        old_debt_allocations: settlement.value === 'debt' ? [] : oldDebtAllocations.value,
        payment_reference: settlement.value === 'bank' ? paymentReference : '',
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

        <div class="grid gap-2 sm:grid-cols-3">
            <button type="button" class="rounded-xl border p-3 text-left text-sm font-semibold transition" :class="settlement === 'cash' ? 'border-emerald-500 bg-emerald-50 text-emerald-800 ring-1 ring-emerald-300' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'" @click="chooseSettlement('cash')">
                Tiền mặt
            </button>
            <button type="button" class="rounded-xl border p-3 text-left text-sm font-semibold transition" :class="settlement === 'bank' ? 'border-blue-500 bg-blue-50 text-blue-800 ring-1 ring-blue-300' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'" @click="chooseSettlement('bank')">
                Chuyển khoản
            </button>
            <button type="button" class="rounded-xl border p-3 text-left text-sm font-semibold transition" :class="settlement === 'debt' ? 'border-amber-500 bg-amber-50 text-amber-800 ring-1 ring-amber-300' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'" @click="chooseSettlement('debt')">
                Ghi nợ
            </button>
        </div>

        <section v-if="settlement !== 'debt'" class="space-y-3 rounded-xl border p-3" :class="settlement === 'bank' ? 'border-blue-200 bg-blue-50/50' : 'border-emerald-200 bg-emerald-50/50'">
            <label class="block text-sm font-semibold text-slate-700">
                {{ settlement === 'cash' ? 'Tiền đưa' : 'Số tiền chuyển' }}
                <input :value="tenderedDisplay" inputmode="numeric" class="mt-1.5 h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-base font-bold text-slate-900 outline-none focus:border-blue-500" @input="updateTendered" />
            </label>
            <div v-if="oldDebt > 0" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-900">
                <label class="flex cursor-pointer items-start gap-2">
                <input :checked="payOldDebt" type="checkbox" class="mt-0.5 rounded border-amber-300 text-amber-600 focus:ring-amber-500" @change="toggleOldDebt($event.target.checked)" />
                <span class="min-w-0 flex-1"><strong>Trả nợ đơn cũ</strong><span class="block text-xs text-amber-700">Công nợ hiện tại: {{ formatNumber(oldDebt) }} đ</span></span>
                </label>
                <div v-if="payOldDebt" class="mt-3 space-y-2 border-t border-amber-200 pt-2">
                    <div v-if="loadingOldDebtOrders" class="text-xs text-amber-700">Đang tải các phiếu còn nợ...</div>
                    <div v-else-if="!oldDebtOrders.length" class="text-xs text-amber-700">Không có phiếu nhập còn nợ để chọn.</div>
                    <div v-else v-for="order in oldDebtOrders" :key="order.id" class="grid grid-cols-[auto_minmax(0,1fr)_120px] items-center gap-2 rounded-md bg-white p-2">
                        <input v-model="order.selected" type="checkbox" class="rounded border-amber-300 text-amber-600 focus:ring-amber-500" @change="!order.selected && (order.payment_amount = 0)" />
                        <div class="min-w-0"><div class="font-semibold text-slate-800">{{ order.code }}</div><div class="text-[11px] text-slate-500">{{ order.date }} · Còn {{ formatNumber(order.remaining) }} đ</div></div>
                        <input :disabled="!order.selected" :value="order.payment_amount ? formatNumber(order.payment_amount) : ''" inputmode="numeric" placeholder="Số trả" class="h-9 min-w-0 rounded-md border border-amber-200 px-2 text-right text-xs font-bold text-amber-900 disabled:bg-slate-100 disabled:text-slate-400" @input="updateOldDebt(order, $event)" />
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2 rounded-lg bg-white p-3 text-sm">
                <div><div class="text-xs text-slate-500">Thanh toán phiếu</div><strong class="text-slate-900">{{ formatNumber(paidAmount) }} đ</strong></div>
                <div><div class="text-xs text-slate-500">Trả nợ cũ</div><strong class="text-amber-700">{{ formatNumber(oldDebtPaid) }} đ</strong></div>
                <div v-if="settlement === 'cash'" class="col-span-2 flex items-center justify-between border-t border-slate-100 pt-2"><span class="font-semibold text-slate-600">Tiền thừa</span><strong :class="changeAmount > 0 ? 'text-emerald-600' : 'text-slate-900'">{{ formatNumber(changeAmount) }} đ</strong></div>
            </div>
        </section>

        <section v-if="settlement === 'bank'" class="space-y-3 rounded-xl border border-blue-200 bg-blue-50/50 p-3">
            <div class="rounded-lg bg-white p-3 text-sm">
                <div class="font-semibold text-slate-800">Thông tin tài khoản nhà cung cấp</div>
                <select v-if="bankAccounts.length > 1" v-model="selectedBankAccount" class="mb-2 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800">
                    <option v-for="account in bankAccounts" :key="account.bank_account" :value="account">{{ account.bank_name || 'Ngân hàng' }} · {{ account.bank_account }}{{ account.is_default ? ' · Mặc định' : '' }}</option>
                </select>
                <div v-if="selectedBankAccount?.bank_account" class="mt-2 grid gap-1 text-slate-600">
                    <div>Ngân hàng: <strong class="text-slate-900">{{ selectedBankAccount.bank_name || '—' }}</strong></div>
                    <div>Số tài khoản: <strong class="select-all text-slate-900">{{ selectedBankAccount.bank_account }}</strong></div>
                    <div>Chủ tài khoản: <strong class="text-slate-900">{{ selectedBankAccount.bank_account_name || '—' }}</strong></div>
                </div>
                <p v-else class="mt-1 text-amber-700">Nhà cung cấp chưa khai báo tài khoản ngân hàng.</p>
            </div>
            <div class="rounded-lg bg-white p-3 text-sm">
                <div class="flex items-center justify-between gap-3"><span class="font-semibold text-slate-800">Nội dung chuyển khoản</span><strong class="select-all text-blue-700">{{ paymentReference }}</strong></div>
                <div v-if="bankQrReady" class="mt-3">
                    <QrPayment :bank-bin="String(selectedBankAccount.bank_bin)" :bank-account="String(selectedBankAccount.bank_account)" :amount="transferAmount" :description="paymentReference" compact />
                </div>
                <p v-else class="mt-2 text-amber-700">Cần nhập mã BIN gồm 6 chữ số và số tài khoản để tạo mã QR.</p>
                <label v-if="isMobile" class="mt-3 block text-sm font-semibold text-slate-700">
                    Ứng dụng ngân hàng bạn dùng
                    <select v-model="selectedPaymentAppId" class="mt-1.5 h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm font-normal text-slate-800">
                        <option value="">{{ loadingPaymentApps ? 'Đang tải danh sách ứng dụng...' : 'Chọn ứng dụng ngân hàng...' }}</option>
                        <option v-for="app in paymentApps" :key="app.app_id" :value="app.app_id">{{ app.app_name }}</option>
                    </select>
                </label>
                <p v-if="paymentAppError" class="mt-2 text-xs text-rose-600">{{ paymentAppError }}</p>
                <p v-else-if="isMobile && !loadingPaymentApps && !paymentApps.length" class="mt-2 text-xs text-amber-700">Danh sách ứng dụng ngân hàng hiện không khả dụng.</p>
                <p v-else-if="isMobile && !selectedPaymentAppId" class="mt-2 text-xs text-slate-500">Chọn app ngân hàng đã cài trên điện thoại để mở sẵn thông tin chuyển khoản.</p>
                <a v-if="isMobile && paymentDeepLink" :href="paymentDeepLink" class="mt-3 inline-flex min-h-10 items-center rounded-lg bg-emerald-600 px-4 text-sm font-bold text-white hover:bg-emerald-700">Mở {{ selectedPaymentApp?.app_name }} để chuyển khoản</a>
            </div>
        </section>

        <div v-if="settlement === 'debt'" class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-800">
            Chưa thanh toán tiền hàng. Toàn bộ giá trị phiếu sẽ được cộng vào công nợ nhà cung cấp.
        </div>

        <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2.5 text-sm">
            <span class="text-slate-600">Công nợ còn lại sau phiếu</span>
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
