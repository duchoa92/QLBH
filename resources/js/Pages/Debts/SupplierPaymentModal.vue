<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import {
    Receipt, X, Banknote, Landmark, ChevronDown, History, Wand2, ScanLine, Loader2, Check,
} from 'lucide-vue-next'
import { formatMoney, formatNumber } from '@/utils/format'

const props = defineProps({
    supplier: { type: Object, required: true },
    orders: { type: Array, default: () => [] },       // các phiếu nhập còn nợ của NCC này
    initialOrderId: { type: [Number, String], default: '' },
    history: { type: Array, default: () => [] },      // lịch sử thanh toán của NCC này
})

const emit = defineEmits(['close'])

/* ------------------------------------------------------------------ */
/* State                                                               */
/* ------------------------------------------------------------------ */
const debt = computed(() => Number(props.supplier.debt_balance || 0))

const primaryId = ref(props.initialOrderId ? Number(props.initialOrderId) : null)
const method = ref('cash')
const amount = ref(0)
const note = ref('')
const useOldDebt = ref(false)
const showHistory = ref(false)
const scanCode = ref('')
const scanMessage = ref('')
const error = ref('')

// số tiền trả cho từng phiếu cũ: { [orderId]: { checked, pay } }
const oldPay = ref({})

const form = useForm({
    amount: '',
    payment_method: 'cash',
    note: '',
    order_id: '',
    allocations: [],
})

const primary = computed(() => props.orders.find(o => Number(o.id) === Number(primaryId.value)) || null)
const otherOrders = computed(() => props.orders.filter(o => Number(o.id) !== Number(primaryId.value)))

/* ------------------------------------------------------------------ */
/* Tính toán phân bổ                                                   */
/* ------------------------------------------------------------------ */
const primaryAlloc = computed(() =>
    primary.value ? Math.min(amount.value, Number(primary.value.due)) : 0
)

const oldAlloc = computed(() =>
    useOldDebt.value
        ? otherOrders.value.reduce((sum, o) => sum + (oldPay.value[o.id]?.checked ? Number(oldPay.value[o.id]?.pay || 0) : 0), 0)
        : 0
)

// phần còn lại không gắn vào phiếu nào (trừ thẳng vào công nợ tổng)
const generalAlloc = computed(() => Math.max(0, amount.value - primaryAlloc.value - oldAlloc.value))

const remainingDebt = computed(() => debt.value - amount.value)
const overDebt = computed(() => amount.value > debt.value)

const canSubmit = computed(() =>
    amount.value > 0 && !overDebt.value && !form.processing
)

/* ------------------------------------------------------------------ */
/* Phân bổ tự động cho các phiếu cũ (cũ nhất trước)                    */
/* ------------------------------------------------------------------ */
const initOldPay = () => {
    const next = {}
    otherOrders.value.forEach(o => {
        next[o.id] = { checked: oldPay.value[o.id]?.checked ?? true, pay: 0 }
    })
    oldPay.value = next
}

const autoDistribute = () => {
    let available = Math.max(0, amount.value - primaryAlloc.value)

    otherOrders.value.forEach(o => {
        const row = oldPay.value[o.id] || (oldPay.value[o.id] = { checked: true, pay: 0 })

        if (row.checked && available > 0) {
            const pay = Math.min(Number(o.due), available)
            row.pay = pay
            available -= pay
        } else {
            row.pay = 0
        }
    })
}

watch([amount, primaryId, useOldDebt], () => {
    if (useOldDebt.value) autoDistribute()
})

watch(primaryId, () => {
    initOldPay()
    if (useOldDebt.value) autoDistribute()
})

const toggleOldDebt = () => {
    useOldDebt.value = !useOldDebt.value
    if (useOldDebt.value) {
        initOldPay()
        autoDistribute()
    } else {
        Object.values(oldPay.value).forEach(r => { r.pay = 0 })
    }
}

const toggleInvoice = (order, checked) => {
    oldPay.value[order.id].checked = checked
    if (!checked) oldPay.value[order.id].pay = 0
    autoDistribute()
}

const onInvoicePayInput = (order, event) => {
    const others = otherOrders.value.reduce(
        (s, o) => s + (o.id === order.id ? 0 : Number(oldPay.value[o.id]?.pay || 0)), 0
    )
    const room = Math.max(0, amount.value - primaryAlloc.value - others)
    const value = Math.min(parseMoney(event.target.value), Number(order.due), room)

    oldPay.value[order.id].pay = value
    event.target.value = value ? formatNumber(value) : ''
}

/* ------------------------------------------------------------------ */
/* Nhập tiền                                                           */
/* ------------------------------------------------------------------ */
const parseMoney = (v) => parseInt(String(v ?? '').replace(/\D/g, ''), 10) || 0

const amountText = computed(() => amount.value ? formatNumber(amount.value) : '')

const onAmountInput = (event) => {
    amount.value = parseMoney(event.target.value)
    event.target.value = amount.value ? formatNumber(amount.value) : ''
}

const setAmount = (value) => { amount.value = Math.max(0, Math.round(value)) }
const addAmount = (value) => setAmount(amount.value + value)

/* ------------------------------------------------------------------ */
/* Chọn phiếu / quét mã                                                */
/* ------------------------------------------------------------------ */
const selectPrimary = (id) => {
    primaryId.value = id ? Number(id) : null
    if (primary.value) setAmount(Math.min(Number(primary.value.due), debt.value))
}

const scanOrder = () => {
    const code = scanCode.value.trim().toLowerCase()
    if (!code) return

    const match = props.orders.find(o => o.code.toLowerCase() === code)
        || props.orders.find(o => o.code.toLowerCase().includes(code))

    if (!match) {
        scanMessage.value = 'Không tìm thấy phiếu nhập còn nợ phù hợp.'
        return
    }

    selectPrimary(match.id)
    scanMessage.value = `Đã chọn ${match.code} · còn nợ ${formatMoney(match.due)}`
    scanCode.value = ''
}

/* ------------------------------------------------------------------ */
/* Gửi                                                                 */
/* ------------------------------------------------------------------ */
const submit = () => {
    if (!canSubmit.value) return
    error.value = ''

    const allocations = []

    if (primary.value && primaryAlloc.value > 0) {
        allocations.push({ order_id: primary.value.id, amount: primaryAlloc.value })
    }

    if (useOldDebt.value) {
        otherOrders.value.forEach(o => {
            const row = oldPay.value[o.id]
            if (row?.checked && Number(row.pay) > 0) {
                allocations.push({ order_id: o.id, amount: Number(row.pay) })
            }
        })
    }

    form.amount = amount.value
    form.payment_method = method.value
    form.note = note.value
    form.order_id = ''
    form.allocations = allocations

    form.post(route('debts.suppliers.payments', props.supplier.id), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
        onError: errors => {
            error.value = errors.amount || Object.values(errors)[0] || 'Không thể ghi nhận thanh toán.'
        },
    })
}

const handleEsc = (e) => { if (e.key === 'Escape') emit('close') }

onMounted(() => {
    window.addEventListener('keydown', handleEsc)
    initOldPay()
    // mặc định: trả đủ phiếu đang chọn, hoặc để trống nếu thanh toán công nợ tổng
    if (primary.value) setAmount(Math.min(Number(primary.value.due), debt.value))
})
onBeforeUnmount(() => window.removeEventListener('keydown', handleEsc))

const methodLabel = { cash: 'Tiền mặt', bank: 'Chuyển khoản', card: 'Thẻ' }
</script>

<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-[1000] flex items-center justify-center bg-slate-900/60 p-3 sm:p-4">
            <div class="absolute inset-0" @click="emit('close')"></div>

            <section
                role="dialog"
                aria-modal="true"
                class="relative flex max-h-[92dvh] w-full max-w-xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl"
            >
                <!-- HEADER -->
                <div class="flex shrink-0 items-center justify-between border-b border-slate-100 bg-slate-50/50 px-5 py-4 sm:px-6">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-blue-100 bg-blue-50 text-blue-600">
                            <Receipt :size="20" />
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-800">Thanh toán nhà cung cấp</h3>
                            <p class="text-xs text-slate-500">
                                <template v-if="primary">
                                    Mã phiếu nhập: <span class="font-mono font-medium text-slate-700">{{ primary.code }}</span>
                                </template>
                                <template v-else>Thanh toán công nợ tổng</template>
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        title="Đóng"
                        class="flex h-8 w-8 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                        @click="emit('close')"
                    >
                        <X :size="18" />
                    </button>
                </div>

                <!-- BODY -->
                <div class="custom-scrollbar min-h-0 flex-1 space-y-5 overflow-y-auto p-5 sm:p-6">

                    <!-- Tóm tắt NCC -->
                    <div class="rounded-xl border border-slate-200/80 bg-gradient-to-br from-slate-50 to-blue-50/30 p-4">
                        <div class="mb-3 flex items-start justify-between border-b border-slate-200/60 pb-3">
                            <div class="min-w-0">
                                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Nhà cung cấp</span>
                                <div class="mt-0.5 truncate text-base font-bold text-slate-800">{{ supplier.name }}</div>
                                <div class="text-xs text-slate-500">
                                    {{ supplier.code || '—' }}<template v-if="supplier.phone"> · {{ supplier.phone }}</template>
                                </div>
                            </div>
                            <button
                                type="button"
                                class="flex shrink-0 items-center gap-1 text-xs font-medium text-blue-600 hover:text-blue-800 hover:underline"
                                @click="showHistory = !showHistory"
                            >
                                <History :size="13" /> Lịch sử nợ
                            </button>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="rounded-lg border border-slate-100 bg-white p-3 shadow-sm">
                                <span class="mb-1 block text-xs text-slate-500">Tiền phiếu này</span>
                                <span class="text-lg font-bold text-slate-900">{{ primary ? formatMoney(primary.due) : '—' }}</span>
                            </div>
                            <div class="rounded-lg border border-amber-100 bg-white p-3 shadow-sm">
                                <span class="mb-1 block text-xs font-medium text-amber-700">Công nợ hiện tại</span>
                                <span class="text-lg font-bold text-amber-600">{{ formatMoney(debt) }}</span>
                            </div>
                        </div>

                        <!-- Lịch sử thanh toán gần đây -->
                        <div v-if="showHistory" class="mt-3 space-y-1.5 rounded-lg border border-slate-200 bg-white p-3">
                            <div class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Thanh toán gần đây</div>
                            <div v-for="item in history.slice(0, 5)" :key="item.id" class="flex items-center justify-between text-xs">
                                <span class="text-slate-500">{{ item.date }} · {{ methodLabel[item.method] || item.method }}<template v-if="item.code"> · {{ item.code }}</template></span>
                                <span class="font-semibold text-emerald-700">{{ formatMoney(item.amount) }}</span>
                            </div>
                            <p v-if="!history.length" class="text-xs text-slate-400">Chưa có lịch sử thanh toán.</p>
                        </div>

                        <!-- Chọn phiếu / quét mã -->
                        <div v-if="orders.length" class="mt-3 space-y-2">
                            <div class="flex gap-2">
                                <input
                                    v-model="scanCode"
                                    type="text"
                                    class="min-w-0 flex-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs outline-none focus:border-blue-400"
                                    placeholder="Quét / nhập mã phiếu nhập rồi nhấn Enter"
                                    @keyup.enter.prevent="scanOrder"
                                />
                                <button type="button" class="inline-flex items-center gap-1 rounded-lg bg-blue-600 px-3 py-2 text-xs font-bold text-white hover:bg-blue-700" @click="scanOrder">
                                    <ScanLine :size="13" /> Tìm
                                </button>
                            </div>
                            <p v-if="scanMessage" class="text-xs" :class="scanMessage.startsWith('Đã chọn') ? 'text-emerald-700' : 'text-slate-500'">{{ scanMessage }}</p>
                            <select
                                :value="primaryId ?? ''"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs"
                                @change="selectPrimary($event.target.value)"
                            >
                                <option value="">Thanh toán công nợ tổng (không gắn phiếu)</option>
                                <option v-for="o in orders" :key="o.id" :value="o.id">{{ o.code }} — còn {{ formatMoney(o.due) }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- Hình thức thanh toán -->
                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-wider text-slate-600">Hình thức thanh toán</label>
                        <div class="grid grid-cols-2 gap-2 rounded-xl bg-slate-100 p-1">
                            <button
                                type="button"
                                class="flex items-center justify-center gap-2 rounded-lg px-3 py-2.5 text-sm font-semibold transition"
                                :class="method === 'cash' ? 'border border-slate-200/60 bg-white text-blue-600 shadow-sm' : 'text-slate-600 hover:text-slate-800'"
                                @click="method = 'cash'"
                            >
                                <Banknote :size="16" class="text-emerald-500" /> Tiền mặt
                            </button>
                            <button
                                type="button"
                                class="flex items-center justify-center gap-2 rounded-lg px-3 py-2.5 text-sm font-semibold transition"
                                :class="method === 'bank' ? 'border border-slate-200/60 bg-white text-blue-600 shadow-sm' : 'text-slate-600 hover:text-slate-800'"
                                @click="method = 'bank'"
                            >
                                <Landmark :size="16" class="text-blue-500" /> Chuyển khoản
                            </button>
                        </div>
                    </div>

                    <!-- Số tiền -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <label for="supplier-paid-amount" class="text-sm font-semibold text-slate-700">Số tiền thanh toán</label>
                            <span class="text-xs text-slate-400">Đơn vị: VNĐ</span>
                        </div>

                        <div class="relative">
                            <input
                                id="supplier-paid-amount"
                                type="text"
                                inputmode="numeric"
                                :value="amountText"
                                placeholder="0"
                                class="w-full rounded-xl border-2 bg-white py-3 pl-4 pr-10 text-right text-xl font-bold text-blue-600 transition focus:outline-none focus:ring-4 focus:ring-blue-500/10"
                                :class="overDebt ? 'border-rose-300 focus:border-rose-400' : 'border-slate-200 focus:border-blue-500'"
                                @input="onAmountInput"
                            />
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-sm font-bold text-slate-400">đ</span>
                        </div>
                        <p v-if="overDebt" class="text-xs font-medium text-rose-600">Số tiền vượt quá công nợ hiện tại ({{ formatMoney(debt) }}).</p>

                        <div class="flex flex-wrap gap-2 pt-1">
                            <button
                                v-if="primary"
                                type="button"
                                class="rounded-lg border border-slate-200 bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-600"
                                @click="setAmount(Math.min(Number(primary.due), debt))"
                            >
                                Trả đủ phiếu ({{ formatMoney(primary.due) }})
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border border-slate-200 bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700"
                                @click="setAmount(debt)"
                            >
                                Trả hết công nợ
                            </button>
                            <button type="button" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-100" @click="addAmount(500000)">+500k</button>
                            <button type="button" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-100" @click="addAmount(1000000)">+1tr</button>
                        </div>
                    </div>

                    <!-- Trả nợ phiếu cũ -->
                    <div v-if="otherOrders.length" class="overflow-hidden rounded-xl border border-amber-200 bg-amber-50/40">
                        <div class="flex cursor-pointer select-none items-center justify-between p-3.5" @click="toggleOldDebt">
                            <div class="flex items-center gap-3">
                                <input
                                    type="checkbox"
                                    :checked="useOldDebt"
                                    class="h-5 w-5 cursor-pointer rounded border-amber-400 text-amber-600 focus:ring-amber-500"
                                    @click.stop
                                    @change="toggleOldDebt"
                                />
                                <div>
                                    <span class="block text-sm font-bold text-amber-900">Trả nợ phiếu cũ</span>
                                    <span class="text-xs text-amber-700">
                                        {{ otherOrders.length }} phiếu nhập còn nợ khác
                                    </span>
                                </div>
                            </div>
                            <ChevronDown :size="16" class="text-amber-600 transition-transform duration-200" :class="useOldDebt && 'rotate-180'" />
                        </div>

                        <div v-if="useOldDebt" class="space-y-2.5 border-t border-amber-200/60 px-3.5 pb-3.5 pt-2">
                            <div class="flex items-center justify-between py-1 text-xs font-medium text-amber-800">
                                <span>Danh sách phiếu chưa thanh toán hết:</span>
                                <button type="button" class="flex items-center gap-1 font-semibold text-blue-600 hover:underline" @click="autoDistribute">
                                    <Wand2 :size="11" /> Tự động phân bổ
                                </button>
                            </div>

                            <div class="custom-scrollbar max-h-60 space-y-2 overflow-y-auto pr-1">
                                <div
                                    v-for="o in otherOrders"
                                    :key="o.id"
                                    class="flex items-center justify-between gap-2 rounded-lg border border-slate-200/80 bg-white p-2.5 shadow-sm transition hover:border-amber-300"
                                >
                                    <div class="flex min-w-0 items-center gap-3">
                                        <input
                                            type="checkbox"
                                            :checked="oldPay[o.id]?.checked"
                                            class="h-4 w-4 cursor-pointer rounded border-slate-300 text-amber-600 focus:ring-amber-500"
                                            @change="toggleInvoice(o, $event.target.checked)"
                                        />
                                        <div class="min-w-0">
                                            <span class="block truncate font-mono text-xs font-bold text-slate-800">{{ o.code }}</span>
                                            <div class="text-[11px] text-slate-500">
                                                {{ o.date }} · Còn: <strong class="font-semibold text-slate-700">{{ formatMoney(o.due) }}</strong>
                                            </div>
                                        </div>
                                    </div>
                                    <input
                                        type="text"
                                        inputmode="numeric"
                                        placeholder="Số trả"
                                        :value="oldPay[o.id]?.pay ? formatNumber(oldPay[o.id].pay) : ''"
                                        :disabled="!oldPay[o.id]?.checked"
                                        class="w-32 shrink-0 rounded-lg border border-slate-200 px-2 py-1.5 text-right text-xs font-bold text-amber-700 focus:border-amber-500 focus:outline-none disabled:bg-slate-50 disabled:text-slate-400"
                                        @input="onInvoicePayInput(o, $event)"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bảng tính nhanh -->
                    <div class="space-y-2 rounded-xl bg-slate-900 p-4 text-xs text-slate-200 shadow-inner">
                        <div class="flex items-center justify-between text-slate-300">
                            <span>{{ primary ? 'Thanh toán phiếu này:' : 'Thanh toán công nợ tổng:' }}</span>
                            <span class="font-mono font-semibold text-white">{{ formatMoney(primary ? primaryAlloc : amount) }}</span>
                        </div>
                        <div class="flex items-center justify-between text-slate-300">
                            <span>Thanh toán trả nợ phiếu cũ:</span>
                            <span class="font-mono font-semibold text-amber-400">{{ formatMoney(oldAlloc) }}</span>
                        </div>
                        <div v-if="primary && generalAlloc > 0" class="flex items-center justify-between border-t border-slate-800 pt-2 text-slate-300">
                            <span>Trừ vào công nợ tổng (không gắn phiếu):</span>
                            <span class="font-mono font-semibold text-emerald-400">{{ formatMoney(generalAlloc) }}</span>
                        </div>
                    </div>

                    <!-- Ghi chú -->
                    <div>
                        <label for="supplier-payment-note" class="mb-1 block text-xs font-medium text-slate-600">Ghi chú thanh toán</label>
                        <input
                            id="supplier-payment-note"
                            v-model="note"
                            maxlength="1000"
                            type="text"
                            placeholder="Nhập ghi chú phiếu chi (không bắt buộc)..."
                            class="w-full rounded-lg border border-slate-200 bg-white p-2.5 text-xs text-slate-700 focus:border-slate-400 focus:outline-none"
                        />
                    </div>

                    <p v-if="error" class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ error }}</p>
                </div>

                <!-- FOOTER -->
                <div class="shrink-0 space-y-3 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="block text-xs text-slate-500">Công nợ còn lại sau thanh toán</span>
                            <span class="text-xs text-slate-400">(Công nợ hiện tại − Tổng trả)</span>
                        </div>
                        <span class="font-mono text-xl font-extrabold" :class="remainingDebt > 0 ? 'text-red-600' : 'text-emerald-600'">
                            {{ formatMoney(Math.max(0, remainingDebt)) }}
                        </span>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-1">
                        <button type="button" class="rounded-xl border border-slate-300 px-4 py-2.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-100" @click="emit('close')">
                            Hủy bỏ
                        </button>
                        <button
                            type="button"
                            :disabled="!canSubmit"
                            class="flex items-center gap-1.5 rounded-xl bg-blue-600 px-5 py-2.5 text-xs font-bold text-white shadow-lg shadow-blue-500/25 transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                            @click="submit"
                        >
                            <Loader2 v-if="form.processing" :size="14" class="animate-spin" />
                            <Check v-else :size="14" />
                            {{ form.processing ? 'Đang lưu…' : 'Xác nhận thanh toán' }}
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </Teleport>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>
