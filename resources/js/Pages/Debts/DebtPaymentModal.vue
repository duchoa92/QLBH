<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import { Banknote, Check, CreditCard, FileText, Landmark, Receipt, X } from 'lucide-vue-next'
import QrPayment from '@/Modules/POS/Payment/Components/QrPayment.vue'
import { formatMoney, formatNumber } from '@/utils/format'

/*
 * Thanh toán công nợ dùng chung cho khách hàng (thu) và nhà cung cấp (chi).
 * - Chỉ chọn hóa đơn / phiếu nhập còn nợ (mặc định chọn tất cả); số tiền tự cập nhật theo các đơn được chọn.
 * - Giảm trừ / chiết khấu (đ hoặc %), tiền khách đưa, tiền thừa, còn thiếu, QR chuyển khoản như phần thanh toán đơn hàng.
 */
const props = defineProps({
    entity: { type: String, required: true }, // 'customer' | 'supplier'
    party: { type: Object, required: true },
    orders: { type: Array, default: () => [] }, // đơn còn nợ của đối tượng này, cũ nhất trước
    initialOrderId: { type: [Number, String], default: '' },
    bank: { type: Object, default: () => ({ accounts: [], qr_theme: 'classic' }) },
})
const emit = defineEmits(['close'])

const isCustomer = computed(() => props.entity === 'customer')
const partyName = computed(() => (isCustomer.value ? props.party.full_name : props.party.name))
const debt = computed(() => Number(props.party.debt_balance || 0))
const hasOrders = computed(() => props.orders.length > 0)

const parseMoney = value => parseInt(String(value ?? '').replace(/\D/g, ''), 10) || 0
const text = value => (value ? formatNumber(value) : '')

/* ---------- Chọn hóa đơn ---------- */
const selected = ref({})
const initSelection = () => {
    const next = {}
    props.orders.forEach((order) => {
        next[order.id] = props.initialOrderId ? Number(order.id) === Number(props.initialOrderId) : true
    })
    selected.value = next
}
// Hiển thị đơn gần nhất lên trước; việc chia tiền vẫn thu đơn cũ nhất trước (props.orders: cũ -> mới).
const displayOrders = computed(() => [...props.orders].reverse())
const selectedOrders = computed(() => props.orders.filter(order => selected.value[order.id]))
const allSelected = computed(() => hasOrders.value && selectedOrders.value.length === props.orders.length)
const toggleAll = () => {
    const next = {}
    props.orders.forEach((order) => { next[order.id] = !allSelected.value })
    selected.value = next
}

// Không có đơn nợ riêng: nhập số tiền công nợ tổng cần thanh toán.
const manualBase = ref(0)
const baseDue = computed(() => (hasOrders.value
    ? selectedOrders.value.reduce((sum, order) => sum + Number(order.due || 0), 0)
    : Math.min(manualBase.value, debt.value)))

/* ---------- Giảm trừ / chiết khấu ---------- */
const discountType = ref('amount') // amount | percent
const discountInput = ref(0)
const discountAmount = computed(() => {
    const value = Number(discountInput.value || 0)
    const raw = discountType.value === 'percent' ? Math.round(baseDue.value * Math.min(100, value) / 100) : value
    return Math.max(0, Math.min(Math.round(raw), baseDue.value))
})
const payable = computed(() => Math.max(0, baseDue.value - discountAmount.value))

/* ---------- Thanh toán ---------- */
const method = ref('cash')
const received = ref(0)
const receivedTouched = ref(false)
const note = ref('')
const error = ref('')

watch(payable, (value) => { if (!receivedTouched.value) received.value = value })
watch(method, () => { receivedTouched.value = false; received.value = payable.value })

const applied = computed(() => Math.min(Number(received.value || 0), payable.value))
const change = computed(() => Math.max(0, Number(received.value || 0) - payable.value))
const shortage = computed(() => Math.max(0, payable.value - applied.value))
const debtAfter = computed(() => Math.max(0, debt.value - applied.value - discountAmount.value))

const onReceivedInput = (event) => {
    receivedTouched.value = true
    received.value = parseMoney(event.target.value)
    event.target.value = text(received.value)
}
const setReceived = (value) => { receivedTouched.value = value !== payable.value; received.value = Math.max(0, Math.round(value)) }
const onManualInput = (event) => {
    manualBase.value = Math.min(parseMoney(event.target.value), debt.value)
    event.target.value = text(manualBase.value)
}
const onDiscountInput = (event) => {
    const digits = parseMoney(event.target.value)
    discountInput.value = discountType.value === 'percent' ? Math.min(100, digits) : digits
    event.target.value = text(discountInput.value)
}
const setDiscountType = (type) => { discountType.value = type; discountInput.value = 0 }

/* ---------- Chia tiền cho từng hóa đơn: giảm trừ theo tỷ lệ, tiền thu trả đơn cũ nhất trước ---------- */
const allocations = computed(() => {
    const rows = selectedOrders.value
    let discountLeft = discountAmount.value
    let payLeft = applied.value
    return rows.map((order, index) => {
        const due = Number(order.due || 0)
        const isLast = index === rows.length - 1
        const discount = Math.max(0, Math.min(due, discountLeft, isLast ? discountLeft : Math.round(discountAmount.value * due / (baseDue.value || 1))))
        discountLeft -= discount
        const pay = Math.min(due - discount, payLeft)
        payLeft -= pay
        return { order, due, discount, pay, remain: Math.max(0, due - discount - pay) }
    })
})
const allocationOf = id => allocations.value.find(row => Number(row.order.id) === Number(id))

/* ---------- QR chuyển khoản ---------- */
const accounts = computed(() => {
    if (!isCustomer.value) {
        // Chi cho NCC: dùng tài khoản của chính NCC
        return props.party.bank_bin && props.party.bank_account
            ? [{ id: 'supplier', bank_bin: props.party.bank_bin, bank_account: props.party.bank_account, bank_name: props.party.bank_name, bank_account_name: props.party.bank_account_name }]
            : []
    }
    return (props.bank?.accounts || []).filter(account => account.bank_bin && account.bank_account)
})
const accountId = ref('')
watch(accounts, (list) => {
    if (!list.some(account => String(account.id) === String(accountId.value))) {
        accountId.value = String((list.find(account => account.is_default) || list[0])?.id ?? '')
    }
}, { immediate: true })
const account = computed(() => accounts.value.find(item => String(item.id) === String(accountId.value)) || null)
const transferNote = computed(() => {
    const codes = selectedOrders.value.map(order => order.code)
    const label = isCustomer.value ? 'Thu no' : 'Tra no'
    return codes.length ? `${label} ${codes.slice(0, 3).join(' ')}${codes.length > 3 ? '...' : ''}` : `${label} ${props.party.code || ''}`.trim()
})
const onQrAmount = (value) => { receivedTouched.value = true; received.value = Number(value || 0) }

const labels = computed(() => ({
    title: isCustomer.value ? 'Thu công nợ khách hàng' : 'Thanh toán nhà cung cấp',
    received: method.value === 'cash'
        ? (isCustomer.value ? 'Tiền khách đưa' : 'Tiền đưa nhà cung cấp')
        : method.value === 'bank' ? (isCustomer.value ? 'Tiền khách đã chuyển' : 'Tiền đã chuyển') : 'Số tiền quẹt thẻ',
    change: isCustomer.value ? 'Tiền thừa trả khách' : 'Tiền thừa (thu lại)',
    order: isCustomer.value ? 'Hóa đơn' : 'Phiếu nhập',
}))
const methods = [
    { value: 'cash', label: 'Tiền mặt', icon: Banknote },
    { value: 'bank', label: 'Chuyển khoản', icon: Landmark },
    { value: 'card', label: 'Thẻ', icon: CreditCard },
]
const quickCash = [50000, 100000, 200000, 500000]

// Một khối trạng thái duy nhất cho tiền thừa / còn thiếu / đủ, đổi màu theo từng trường hợp.
const toneClass = {
    slate: 'border-slate-200 bg-slate-50 text-slate-600',
    rose: 'border-rose-200 bg-rose-50 text-rose-700',
    amber: 'border-amber-200 bg-amber-50 text-amber-800',
    emerald: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    blue: 'border-blue-200 bg-blue-50 text-blue-700',
}
const status = computed(() => {
    if (payable.value <= 0) return { tone: 'slate', title: 'Không phát sinh thanh toán', amount: null, hint: 'Chỉ áp dụng giảm trừ' }
    if (Number(received.value || 0) <= 0) return { tone: 'rose', title: 'Chưa nhận tiền', amount: payable.value, hint: 'Toàn bộ vẫn ghi nợ' }
    if (change.value > 0) return { tone: 'emerald', title: labels.value.change, amount: change.value, hint: 'Đã đủ, trả lại phần dư' }
    if (shortage.value > 0) return { tone: 'amber', title: 'Còn thiếu', amount: shortage.value, hint: 'Giữ lại trong công nợ' }
    return { tone: 'blue', title: 'Đã đủ tiền', amount: null, hint: 'Tất toán các đơn đã chọn' }
})

/* ---------- Gửi ---------- */
const form = useForm({ amount: 0, discount: 0, received: 0, base_due: 0, payment_method: 'cash', note: '', allocations: [] })
const canSubmit = computed(() => !form.processing && (applied.value + discountAmount.value) > 0 && (!hasOrders.value || selectedOrders.value.length > 0))
const submit = () => {
    if (!canSubmit.value) return
    error.value = ''
    form.amount = applied.value
    form.discount = discountAmount.value
    form.received = Number(received.value || 0)
    form.base_due = baseDue.value
    form.payment_method = method.value
    form.note = note.value
    form.allocations = hasOrders.value
        ? allocations.value.filter(row => row.pay > 0 || row.discount > 0).map(row => ({ order_id: row.order.id, amount: row.pay, discount: row.discount }))
        : []
    const url = isCustomer.value ? route('debts.customers.payments', props.party.id) : route('debts.suppliers.payments', props.party.id)
    form.post(url, {
        preserveScroll: true,
        onSuccess: () => {
            const message = isCustomer.value ? 'Đã ghi nhận thu công nợ khách hàng.' : 'Đã ghi nhận thanh toán công nợ nhà cung cấp.'
            toast.success(message, { id: message })
            emit('close')
        },
        onError: (errors) => { error.value = errors.amount || Object.values(errors)[0] || 'Không thể ghi nhận thanh toán.' },
    })
}

const onKey = (event) => { if (event.key === 'Escape') emit('close') }
onMounted(() => {
    window.addEventListener('keydown', onKey)
    initSelection()
    manualBase.value = debt.value
    received.value = payable.value
})
onBeforeUnmount(() => window.removeEventListener('keydown', onKey))
</script>

<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-[1000] flex items-center justify-center bg-slate-900/60 p-3 sm:p-4">
            <div class="absolute inset-0" @click="emit('close')" />

            <section role="dialog" aria-modal="true" class="relative flex max-h-[94dvh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                <!-- HEADER -->
                <div class="flex shrink-0 items-center justify-between border-b border-slate-100 bg-slate-50/60 px-5 py-3.5 sm:px-6">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-blue-100 bg-blue-50 text-blue-600"><Receipt :size="20" /></div>
                        <div class="min-w-0">
                            <h3 class="text-base font-bold text-slate-800 sm:text-lg">{{ labels.title }}</h3>
                            <p class="truncate text-xs text-slate-500">
                                <span class="font-semibold text-slate-700">{{ partyName }}</span>
                                <template v-if="party.code"> · {{ party.code }}</template><template v-if="party.phone"> · {{ party.phone }}</template>
                            </p>
                        </div>
                    </div>
                    <button type="button" title="Đóng" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" @click="emit('close')"><X :size="18" /></button>
                </div>

                <!-- BODY: 2 cột (trái: hóa đơn + QR + ghi chú | phải: số tiền + thanh toán) -->
                <div class="custom-scrollbar min-h-0 flex-1 overflow-y-auto p-4 sm:p-5">
                    <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)]">
                        <!-- CỘT TRÁI -->
                        <div class="space-y-3">
                            <div class="overflow-hidden rounded-xl border border-slate-200">
                                <div class="flex items-center justify-between gap-2 border-b border-slate-100 bg-slate-50/70 px-3 py-2">
                                    <label v-if="hasOrders" class="flex cursor-pointer items-center gap-2 text-xs font-bold text-slate-700">
                                        <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600" :checked="allSelected" @change="toggleAll" />
                                        Tất cả ({{ selectedOrders.length }}/{{ orders.length }})
                                    </label>
                                    <span v-else class="text-xs font-bold text-slate-700">Công nợ tổng</span>
                                    <span class="text-[11px] text-slate-500">Nợ hiện tại <b class="text-amber-600">{{ formatMoney(debt) }}</b></span>
                                </div>

                                <ul v-if="hasOrders" class="max-h-64 divide-y divide-slate-100 overflow-y-auto">
                                    <li v-for="order in displayOrders" :key="order.id">
                                        <label class="flex cursor-pointer items-center gap-2.5 px-3 py-2 transition hover:bg-blue-50/40" :class="selected[order.id] ? 'bg-blue-50/30' : ''">
                                            <input v-model="selected[order.id]" type="checkbox" class="h-4 w-4 shrink-0 rounded border-slate-300 text-blue-600" />
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate font-mono text-xs font-bold text-slate-800">{{ order.code }}</span>
                                                <span class="block truncate text-[11px] text-slate-500">{{ order.date }} · Tổng {{ formatMoney(order.total) }}</span>
                                            </span>
                                            <span class="shrink-0 text-right">
                                                <span class="block text-sm font-bold text-rose-600">{{ formatMoney(order.due) }}</span>
                                                <span v-if="selected[order.id] && allocationOf(order.id)" class="block text-[10px] font-semibold" :class="allocationOf(order.id).remain > 0 ? 'text-amber-600' : 'text-emerald-600'">
                                                    {{ allocationOf(order.id).remain > 0 ? `Còn ${formatMoney(allocationOf(order.id).remain)}` : 'Tất toán' }}
                                                </span>
                                            </span>
                                        </label>
                                    </li>
                                </ul>
                                <div v-else class="p-3">
                                    <label class="block text-xs font-semibold text-slate-600">Số tiền công nợ cần thanh toán (đ)
                                        <input :value="text(manualBase)" inputmode="numeric" class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-base font-bold text-slate-900 outline-none focus:border-blue-400" @input="onManualInput" />
                                    </label>
                                </div>
                            </div>

                            <!-- Tổng tiền + giảm trừ -->
                            <div class="rounded-xl border border-blue-100 bg-blue-50/40 p-3">
                                <div class="flex justify-between text-xs text-slate-500"><span>Tổng nợ {{ hasOrders ? 'đã chọn' : '' }}</span><b class="text-slate-700">{{ formatMoney(baseDue) }}</b></div>
                                <div class="mt-2 flex items-center gap-2">
                                    <span class="shrink-0 text-xs text-slate-500">Giảm trừ</span>
                                    <input :value="text(discountInput)" inputmode="numeric" placeholder="0" class="min-w-0 flex-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-right text-sm font-semibold outline-none focus:border-blue-400" @input="onDiscountInput" />
                                    <div class="inline-flex shrink-0 overflow-hidden rounded-lg border border-slate-200 text-xs font-bold">
                                        <button type="button" class="px-2.5 py-1.5" :class="discountType === 'amount' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600'" @click="setDiscountType('amount')">đ</button>
                                        <button type="button" class="px-2.5 py-1.5" :class="discountType === 'percent' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600'" @click="setDiscountType('percent')">%</button>
                                    </div>
                                </div>
                                <p v-if="discountAmount > 0" class="mt-1 text-right text-[11px] font-semibold text-emerald-700">− {{ formatMoney(discountAmount) }}</p>
                                <div class="mt-2 flex items-baseline justify-between border-t border-blue-100 pt-2"><span class="text-xs font-bold text-slate-700">Cần thanh toán</span><b class="text-xl font-black text-blue-700">{{ formatMoney(payable) }}</b></div>
                            </div>

                            <label class="relative block">
                                <FileText :size="14" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                                <input v-model="note" maxlength="1000" placeholder="Ghi chú (không bắt buộc)" class="w-full rounded-xl border border-slate-200 py-2.5 pl-9 pr-3 text-sm outline-none focus:border-blue-400" />
                            </label>
                        </div>

                        <!-- CỘT PHẢI -->
                        <div class="space-y-3">
                            <!-- Hình thức -->
                            <div class="grid grid-cols-3 gap-1.5">
                                <button v-for="item in methods" :key="item.value" type="button" class="flex items-center justify-center gap-1.5 rounded-xl border px-2 py-2 text-xs font-bold transition" :class="method === item.value ? 'border-blue-500 bg-blue-50 text-blue-700 ring-2 ring-blue-100' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'" @click="method = item.value">
                                    <component :is="item.icon" :size="14" />{{ item.label }}
                                </button>
                            </div>

                            <!-- QR chuyển khoản -->
                            <div v-if="method === 'bank'">
                                <template v-if="account">
                                    <select v-if="accounts.length > 1" v-model="accountId" class="mb-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold">
                                        <option v-for="item in accounts" :key="item.id" :value="String(item.id)">{{ item.bank_name || 'Ngân hàng' }} · {{ item.bank_account }}{{ item.bank_account_name ? ` · ${item.bank_account_name}` : '' }}</option>
                                    </select>
                                    <p v-else class="mb-2 text-[11px] text-slate-500">
                                        {{ isCustomer ? 'Tài khoản nhận' : 'Chuyển đến' }}: <b class="text-slate-700">{{ account.bank_name || 'Ngân hàng' }} · {{ account.bank_account }}</b><template v-if="account.bank_account_name"> · {{ account.bank_account_name }}</template>
                                    </p>
                                    <QrPayment
                                        :bank-bin="String(account.bank_bin)"
                                        :bank-account="String(account.bank_account)"
                                        :amount="payable"
                                        :description="transferNote"
                                        :theme="bank?.qr_theme || 'classic'"
                                        compact
                                        editable
                                        @amount-change="onQrAmount"
                                    />
                                </template>
                                <p v-else class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs text-amber-800">
                                    {{ isCustomer ? 'Chưa cấu hình tài khoản ngân hàng để tạo mã QR (Thiết lập → Thanh toán).' : 'Nhà cung cấp chưa có tài khoản ngân hàng để tạo mã QR. Vẫn có thể ghi nhận chuyển khoản.' }}
                                </p>
                            </div>

                            <!-- Tiền khách đưa / chuyển -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-600">{{ labels.received }}
                                    <input :value="text(received)" inputmode="numeric" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-lg font-black text-slate-900 outline-none focus:border-blue-400" @input="onReceivedInput" @focus="$event.target.select()" />
                                </label>
                                <div v-if="method === 'cash'" class="mt-1.5 flex flex-wrap gap-1.5">
                                    <button type="button" class="rounded-lg border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-700 hover:bg-blue-100" @click="setReceived(payable)">Đủ</button>
                                    <button v-for="value in quickCash" :key="value" type="button" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50" @click="setReceived(value)">{{ formatNumber(value) }}</button>
                                </div>
                            </div>

                            <!-- Trạng thái: thừa / thiếu / đủ (đổi màu theo từng trường hợp) -->
                            <div class="rounded-xl border px-3 py-2.5 transition-colors" :class="toneClass[status.tone]">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="text-xs font-bold">{{ status.title }}</div>
                                        <div class="text-[11px] opacity-80">{{ status.hint }}</div>
                                    </div>
                                    <div v-if="status.amount !== null" class="shrink-0 text-lg font-black">{{ formatMoney(status.amount) }}</div>
                                    <Check v-else-if="status.tone === 'blue'" :size="20" class="shrink-0" />
                                </div>
                                <div class="mt-1.5 flex justify-between border-t border-current/15 pt-1.5 text-[11px]"><span class="opacity-80">Nợ còn lại sau thanh toán</span><b>{{ formatMoney(debtAfter) }}</b></div>
                            </div>
                        </div>
                    </div>

                    <p v-if="error" class="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ error }}</p>
                </div>

                <!-- FOOTER -->
                <div class="flex shrink-0 items-center justify-between gap-3 border-t border-slate-100 bg-slate-50/60 px-5 py-3 sm:px-6">
                    <div class="min-w-0 text-xs text-slate-500">
                        {{ isCustomer ? 'Thu' : 'Chi' }}: <b class="text-sm text-slate-900">{{ formatMoney(applied) }}</b>
                        <template v-if="discountAmount > 0"> · Giảm: <b class="text-emerald-700">{{ formatMoney(discountAmount) }}</b></template>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <button type="button" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50" @click="emit('close')">Hủy</button>
                        <button type="button" :disabled="!canSubmit" class="rounded-xl bg-blue-600 px-5 py-2 text-sm font-bold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50" @click="submit">{{ form.processing ? 'Đang lưu…' : 'Xác nhận' }}</button>
                    </div>
                </div>
            </section>
        </div>
    </Teleport>
</template>
