<script setup>
import { computed, ref, watch } from 'vue'
import { CheckCircle2, Landmark, Banknote, CreditCard, Receipt, X } from 'lucide-vue-next'
import BaseModal from '@/Components/UI/BaseModal.vue'
import FloatingInput from '@/Components/UI/FloatingInput.vue'
import QrPayment from '@/Modules/POS/Payment/Components/QrPayment.vue'
import { formatCurrency, formatNumber } from '@/utils/format'
import api from '@/Services/api'

const props = defineProps({
    supplier: { type: Object, required: true },
    total: { type: Number, required: true },
    editMode: { type: Boolean, default: false },
    initialPayment: { type: Object, default: () => ({}) },
})
const emit = defineEmits(['close', 'updated'])
const paymentMethod = ref(props.initialPayment.payment_method || 'cash')
const tenderedAmount = ref(Number(props.initialPayment.tendered_amount ?? props.initialPayment.paid_amount ?? 0) || '')
const autoFillTendered = ref(false)
const payOldDebt = ref(false)
const oldDebtMode = ref('aggregate')
const oldDebtOrders = ref([])
const loadingOldDebtOrders = ref(false)

const oldDebt = computed(() => Math.max(0, Number(props.supplier?.debt_balance || 0)))
const tendered = computed(() => Math.max(0, Number(tenderedAmount.value || 0)))
const paidForNewImport = computed(() => Math.min(Number(props.total || 0), tendered.value))
const selectedOldDebtTarget = computed(() => oldDebtOrders.value
    .filter(order => order.selected)
    .reduce((sum, order) => sum + Math.max(0, Number(order.payment_amount || 0)), 0))
const oldDebtTarget = computed(() => payOldDebt.value ? selectedOldDebtTarget.value : 0)
const totalDueNow = computed(() => Number(props.total || 0) + oldDebtTarget.value)
const selectedOldDebtCount = computed(() => oldDebtOrders.value.filter(order => order.selected && Number(order.payment_amount || 0) > 0).length)
const allOldDebtSelected = computed(() => oldDebtOrders.value.length > 0 && selectedOldDebtCount.value === oldDebtOrders.value.length)
const partialOldDebtSelected = computed(() => selectedOldDebtCount.value > 0 && !allOldDebtSelected.value)
const availableForOldDebt = computed(() => payOldDebt.value ? Math.max(0, tendered.value - Number(props.total || 0)) : 0)
const oldDebtPaid = computed(() => Math.min(oldDebtTarget.value, availableForOldDebt.value))
const changeAmount = computed(() => Math.max(0, tendered.value - Number(props.total || 0) - oldDebtPaid.value))
// Công nợ của phiếu vừa tạo và công nợ cũ là hai khoản độc lập. Không chọn
// trả nợ cũ thì việc thanh toán đủ phiếu mới vẫn phải được ghi nhận là đủ.
const remainingNewImportDebt = computed(() => Math.max(0, Number(props.total || 0) - paidForNewImport.value))
const paymentStatus = computed(() => {
    if (remainingNewImportDebt.value > 0) return { label: 'Còn thiếu', amount: remainingNewImportDebt.value, className: 'text-rose-600' }
    if (changeAmount.value > 0) return { label: 'Tiền thừa', amount: changeAmount.value, className: 'text-emerald-600' }
    return { label: 'Đã thanh toán đủ', amount: null, className: 'text-emerald-600' }
})
const tenderedDisplay = computed(() => tenderedAmount.value === '' ? '' : formatNumber(tenderedAmount.value))
const paymentReference = `NCC${props.supplier.id}-${Date.now().toString().slice(-8)}`
const bankAccounts = computed(() => props.supplier.bank_accounts?.length ? props.supplier.bank_accounts : (props.supplier.bank_account ? [{ bank_name: props.supplier.bank_name, bank_bin: props.supplier.bank_bin, bank_account: props.supplier.bank_account, bank_account_name: props.supplier.bank_account_name, is_default: true }] : []))
const selectedBankAccount = ref(bankAccounts.value.find(account => account.is_default) || bankAccounts.value[0] || null)
const bankQrReady = computed(() => /^\d{6}$/.test(String(selectedBankAccount.value?.bank_bin || '')) && /^[a-zA-Z0-9]{1,50}$/.test(String(selectedBankAccount.value?.bank_account || '')))

const parseAmount = value => Number(String(value || '').replace(/\D/g, '') || 0)
const updateTendered = event => { autoFillTendered.value = false; tenderedAmount.value = parseAmount(event.target.value) }
const toggleAutoFillTendered = checked => {
    autoFillTendered.value = checked
    if (checked) tenderedAmount.value = totalDueNow.value
}

const loadOldDebtOrders = async () => {
    if (loadingOldDebtOrders.value || oldDebtOrders.value.length) return
    loadingOldDebtOrders.value = true
    try {
        const { data } = await api.get(`/stock-import/suppliers/${props.supplier.id}/outstanding-orders`)
        oldDebtOrders.value = (data.orders || []).map(order => ({ ...order, selected: false, payment_amount: 0 }))
    } catch (error) { console.error('Không thể tải phiếu nhập còn nợ:', error) }
    finally { loadingOldDebtOrders.value = false }
}
const oldDebtAllocations = computed(() => {
    let available = oldDebtPaid.value
    const orders = oldDebtOrders.value.filter(order => order.selected)
    return orders.reduce((allocations, order) => {
        const requested = Number(order.payment_amount || 0)
        const amount = Math.min(available, Math.max(0, requested))
        if (amount > 0) allocations.push({ stock_import_id: order.id, amount })
        available -= amount
        return allocations
    }, [])
})
const selectAllOldDebtOrders = () => {
    oldDebtOrders.value.forEach(order => {
        order.selected = true
        order.payment_amount = Number(order.remaining || 0)
    })
}
const toggleOldDebt = async checked => {
    payOldDebt.value = checked
    if (!checked) return
    oldDebtMode.value = 'aggregate'
    await loadOldDebtOrders()
    selectAllOldDebtOrders()
}
const toggleGroupedDebt = async () => {
    if (allOldDebtSelected.value) {
        oldDebtOrders.value.forEach(order => { order.selected = false; order.payment_amount = 0 })
        payOldDebt.value = false
        return
    }
    await toggleOldDebt(true)
}
const selectOldDebtMode = async mode => {
    oldDebtMode.value = mode
    if (mode === 'specific') {
        if (!payOldDebt.value) payOldDebt.value = true
        await loadOldDebtOrders()
    }
    if (mode === 'aggregate') selectAllOldDebtOrders()
}
const updateSpecificDebt = (order, event) => {
    order.payment_amount = Math.min(Number(order.remaining || 0), parseAmount(event.target.value))
    order.selected = Number(order.payment_amount) > 0
}
const toggleSpecificOrder = order => {
    order.payment_amount = order.selected ? Number(order.remaining || 0) : 0
}
watch(paymentMethod, () => { tenderedAmount.value = ''; autoFillTendered.value = false })
watch(totalDueNow, value => { if (autoFillTendered.value) tenderedAmount.value = value })
const submit = () => {
    emit('updated', {
        payment_method: paymentMethod.value,
        tendered_amount: tendered.value,
        change_amount: changeAmount.value,
        paid_amount: paidForNewImport.value,
        old_debt_paid: oldDebtPaid.value,
        old_debt_allocations: oldDebtAllocations.value,
        payment_reference: paymentMethod.value === 'bank' ? paymentReference : '',
    })
    emit('close')
}
</script>

<template>
    <BaseModal title="Xác nhận thanh toán" size="md" layer-class="z-[1200]" body-class="space-y-4 p-4 sm:p-5" @close="emit('close')">
        <template #header>
            <div class="flex items-center gap-3"><div class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><Receipt class="h-5 w-5" /></div><div><div class="text-base font-bold text-slate-900">{{ editMode ? 'Cập nhật thanh toán' : 'Xác nhận thanh toán' }}</div><div class="text-xs font-medium text-slate-500">{{ editMode ? 'Điều chỉnh thanh toán phiếu nhập' : 'Hoàn tất phiếu nhập hàng' }}</div></div></div>
            <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-white hover:text-slate-600" aria-label="Đóng" @click="emit('close')"><X class="h-5 w-5" /></button>
        </template>

        <div class="flex min-w-0 items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm">
            <div class="min-w-0"><div class="truncate font-bold text-slate-800">{{ supplier.name }}</div><div class="text-xs text-slate-500">{{ supplier.phone || 'Chưa có SĐT' }}</div></div>
            <div v-if="oldDebt > 0" class="shrink-0 text-right"><div class="text-[11px] text-slate-500">Đang nợ</div><div class="font-bold text-rose-600">{{ formatCurrency(oldDebt) }}</div></div>
        </div>

        <div class="grid grid-cols-3 gap-2">
            <button type="button" class="flex flex-col items-center justify-center gap-1.5 rounded-xl border py-3 text-xs font-bold transition" :class="paymentMethod === 'cash' ? 'border-blue-600 bg-blue-50 text-blue-600' : 'border-slate-200 text-slate-600 hover:bg-slate-50'" @click="paymentMethod = 'cash'"><Banknote class="h-4 w-4" />Tiền mặt</button>
            <button type="button" class="flex flex-col items-center justify-center gap-1.5 rounded-xl border py-3 text-xs font-bold transition" :class="paymentMethod === 'bank' ? 'border-blue-600 bg-blue-50 text-blue-600' : 'border-slate-200 text-slate-600 hover:bg-slate-50'" @click="paymentMethod = 'bank'"><Landmark class="h-4 w-4" />Chuyển khoản</button>
            <button type="button" class="flex flex-col items-center justify-center gap-1.5 rounded-xl border py-3 text-xs font-bold transition" :class="paymentMethod === 'card' ? 'border-blue-600 bg-blue-50 text-blue-600' : 'border-slate-200 text-slate-600 hover:bg-slate-50'" @click="paymentMethod = 'card'"><CreditCard class="h-4 w-4" />Thẻ / POS</button>
        </div>

        <FloatingInput :model-value="tenderedDisplay" type="text" inputmode="numeric" :label="paymentMethod === 'bank' ? 'Số tiền chuyển khoản' : 'Số tiền đưa'" @input="updateTendered" />
        <label class="-mt-2 inline-flex cursor-pointer items-center gap-2 text-xs font-semibold text-slate-600">
            <input :checked="autoFillTendered" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" @change="toggleAutoFillTendered($event.target.checked)" />
            Đưa đủ <span class="text-blue-600">{{ formatCurrency(totalDueNow) }}</span>
        </label>

        <div v-if="paymentMethod === 'bank'" class="rounded-xl border border-blue-100 bg-blue-50/40 p-3">
            <select v-if="bankAccounts.length > 1" v-model="selectedBankAccount" class="mb-2 h-9 w-full rounded-lg border border-slate-300 bg-white px-2 text-xs text-slate-800"><option v-for="account in bankAccounts" :key="account.bank_account" :value="account">{{ account.bank_name || 'Ngân hàng' }} · {{ account.bank_account }}</option></select>
            <div v-if="bankQrReady"><QrPayment :bank-bin="String(selectedBankAccount.bank_bin)" :bank-account="String(selectedBankAccount.bank_account)" :amount="tendered" :description="paymentReference" compact /></div>
            <p v-else class="text-xs text-amber-700">Nhà cung cấp chưa có đủ thông tin tài khoản để tạo QR.</p>
        </div>

        <div v-if="oldDebt > 0" class="rounded-xl border border-amber-200 bg-amber-50/60 p-3">
            <div class="flex items-center justify-between gap-3">
            <button type="button" class="flex min-w-0 items-center gap-2 text-left text-sm text-amber-900" @click="toggleGroupedDebt">
                <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded border text-[11px] font-black" :class="allOldDebtSelected ? 'border-amber-600 bg-amber-600 text-white' : partialOldDebtSelected ? 'border-amber-500 bg-amber-100 text-amber-700' : 'border-amber-300 bg-white text-transparent'">{{ partialOldDebtSelected ? '−' : '✓' }}</span>
                <span class="truncate font-semibold">Cộng gộp nợ cũ <span class="text-rose-600">({{ formatCurrency(oldDebtTarget) }})</span></span>
            </button>
                <button type="button" class="shrink-0 text-xs font-bold text-blue-600 hover:underline" @click="selectOldDebtMode(oldDebtMode === 'specific' ? 'aggregate' : 'specific')">{{ oldDebtMode === 'specific' ? 'Cộng gộp nợ cũ' : 'Thanh toán đơn cụ thể' }}</button>
            </div>
            <div v-if="payOldDebt && oldDebtMode === 'specific'" class="mt-3 space-y-2 border-t border-amber-200 pt-3">
                <p v-if="loadingOldDebtOrders" class="text-xs text-slate-500">Đang tìm các phiếu còn nợ...</p>
                <p v-else-if="!oldDebtOrders.length" class="text-xs text-amber-700">Không tìm thấy phiếu nhập cũ còn nợ.</p>
                <div v-else class="max-h-44 space-y-2 overflow-y-auto pr-1 custom-scrollbar">
                    <label v-for="order in oldDebtOrders" :key="order.id" class="grid grid-cols-[auto_minmax(0,1fr)_110px] items-center gap-2 rounded-lg border border-amber-100 bg-white p-2 text-xs">
                        <input v-model="order.selected" type="checkbox" class="h-4 w-4 rounded border-amber-300 text-amber-600" @change="toggleSpecificOrder(order)" />
                        <span class="min-w-0"><strong class="block truncate text-slate-800">{{ order.code }}</strong><span class="text-slate-500">{{ order.date }} · Còn {{ formatCurrency(order.remaining) }}</span></span>
                        <input :disabled="!order.selected" :value="order.payment_amount ? formatNumber(order.payment_amount) : ''" inputmode="numeric" placeholder="Số trả" class="h-8 min-w-0 rounded-md border border-slate-200 px-2 text-right font-bold text-amber-700 disabled:bg-slate-100" @input="updateSpecificDebt(order, $event)" />
                    </label>
                </div>
            </div>
        </div>

        <div class="space-y-2 rounded-xl border border-slate-200 bg-slate-50/70 p-3 text-sm">
            <div class="flex justify-between text-slate-600"><span>Tiền hàng</span><strong class="text-slate-800">{{ formatCurrency(total) }}</strong></div>
            <div v-if="oldDebtTarget > 0" class="flex justify-between text-amber-700"><span>Nợ cũ</span><strong>{{ formatCurrency(oldDebtTarget) }}</strong></div>
            <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-extrabold text-blue-700"><span>Tổng cần thanh toán</span><span>{{ formatCurrency(Number(total) + oldDebtTarget) }}</span></div>
            <div v-if="oldDebtPaid > 0" class="flex justify-between text-xs text-amber-700"><span>Đã trả nợ cũ</span><strong>{{ formatCurrency(oldDebtPaid) }}</strong></div>
            <div class="flex justify-between border-t border-slate-200 pt-2 text-xs font-bold" :class="paymentStatus.className"><span>{{ paymentStatus.label }}</span><span>{{ paymentStatus.amount === null ? '✓' : formatCurrency(paymentStatus.amount) }}</span></div>
        </div>

        <template #footer><div class="flex gap-2"><button type="button" class="flex-1 rounded-xl border border-slate-200 bg-white py-3 text-xs font-bold text-slate-600 hover:bg-slate-50" @click="emit('close')">Hủy bỏ</button><button type="button" class="flex-[2] inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 py-3 text-xs font-bold text-white shadow-md shadow-emerald-200 hover:bg-emerald-700" @click="submit"><CheckCircle2 class="h-4 w-4" />Xác nhận thanh toán</button></div></template>
    </BaseModal>
</template>
