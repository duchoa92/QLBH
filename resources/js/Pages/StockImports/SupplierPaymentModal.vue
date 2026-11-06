<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Receipt, X, Banknote, Landmark, NotebookPen, ChevronDown, WandSparkles, Check } from 'lucide-vue-next'
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
const handleEsc = event => { if (event.key === 'Escape') emit('close') }
onMounted(() => window.addEventListener('keydown', handleEsc))
onBeforeUnmount(() => window.removeEventListener('keydown', handleEsc))
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

const toggleOldDebt = async (enabled) => {
    payOldDebt.value = enabled
    if (enabled) {
        await loadOldDebtOrders()
        autoDistribute()
    } else {
        oldDebtOrders.value.forEach(order => { order.selected = false; order.payment_amount = 0 })
    }
}

// Phân bổ phần tiền dư (sau khi trả đủ phiếu mới) vào các phiếu cũ, phiếu cũ nhất trước
const autoDistribute = () => {
    let available = Math.max(0, amountTendered.value - Number(props.total || 0))
    oldDebtOrders.value.forEach(order => {
        const pay = Math.min(Number(order.remaining || 0), available)
        order.payment_amount = pay
        order.selected = pay > 0
        available -= pay
    })
}

watch(tenderedAmount, () => {
    if (payOldDebt.value && oldDebtOrders.value.length) autoDistribute()
})

const setTendered = value => { tenderedAmount.value = Math.max(0, Math.round(value)) }
const addTendered = value => setTendered(amountTendered.value + value)

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
    <Teleport to="body">
        <div class="fixed inset-0 z-[1200] flex items-center justify-center bg-slate-900/60 p-3 sm:p-4">
            <div class="absolute inset-0" @click="emit('close')"></div>

            <section role="dialog" aria-modal="true" class="relative flex max-h-[92dvh] w-full max-w-xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">

                <!-- HEADER -->
                <div class="flex shrink-0 items-center justify-between border-b border-slate-100 bg-slate-50/50 px-5 py-4 sm:px-6">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-blue-100 bg-blue-50 text-blue-600">
                            <Receipt :size="20" />
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-800">Thanh toán nhà cung cấp</h3>
                            <p class="text-xs text-slate-500">Phiếu nhập mới · <span class="font-medium text-slate-700">{{ formatNumber(total) }} đ</span></p>
                        </div>
                    </div>
                    <button type="button" title="Đóng" class="flex h-8 w-8 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" @click="emit('close')">
                        <X :size="18" />
                    </button>
                </div>

                <!-- BODY -->
                <div class="spm-scroll min-h-0 flex-1 space-y-5 overflow-y-auto p-5 sm:p-6">

                    <!-- Tóm tắt -->
                    <div class="rounded-xl border border-slate-200/80 bg-gradient-to-br from-slate-50 to-blue-50/30 p-4">
                        <div class="mb-3 border-b border-slate-200/60 pb-3">
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Nhà cung cấp</span>
                            <div class="mt-0.5 truncate text-base font-bold text-slate-800">{{ supplier.name }}</div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="rounded-lg border border-slate-100 bg-white p-3 shadow-sm">
                                <span class="mb-1 block text-xs text-slate-500">Tiền đơn hàng này</span>
                                <span class="text-lg font-bold text-slate-900">{{ formatNumber(total) }} đ</span>
                            </div>
                            <div class="rounded-lg border border-amber-100 bg-white p-3 shadow-sm">
                                <span class="mb-1 block text-xs font-medium text-amber-700">Công nợ hiện tại</span>
                                <span class="text-lg font-bold text-amber-600">{{ formatNumber(oldDebt) }} đ</span>
                            </div>
                        </div>
                    </div>

                    <!-- Hình thức -->
                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-wider text-slate-600">Hình thức thanh toán</label>
                        <div class="grid grid-cols-3 gap-2 rounded-xl bg-slate-100 p-1">
                            <button type="button" class="flex items-center justify-center gap-1.5 rounded-lg px-2 py-2.5 text-sm font-semibold transition" :class="settlement === 'cash' ? 'border border-slate-200/60 bg-white text-blue-600 shadow-sm' : 'text-slate-600 hover:text-slate-800'" @click="chooseSettlement('cash')">
                                <Banknote :size="16" class="text-emerald-500" /> Tiền mặt
                            </button>
                            <button type="button" class="flex items-center justify-center gap-1.5 rounded-lg px-2 py-2.5 text-sm font-semibold transition" :class="settlement === 'bank' ? 'border border-slate-200/60 bg-white text-blue-600 shadow-sm' : 'text-slate-600 hover:text-slate-800'" @click="chooseSettlement('bank')">
                                <Landmark :size="16" class="text-blue-500" /> Chuyển khoản
                            </button>
                            <button type="button" class="flex items-center justify-center gap-1.5 rounded-lg px-2 py-2.5 text-sm font-semibold transition" :class="settlement === 'debt' ? 'border border-slate-200/60 bg-white text-amber-600 shadow-sm' : 'text-slate-600 hover:text-slate-800'" @click="chooseSettlement('debt')">
                                <NotebookPen :size="16" class="text-amber-500" /> Ghi nợ
                            </button>
                        </div>
                    </div>

                    <!-- Ghi nợ -->
                    <div v-if="settlement === 'debt'" class="rounded-xl border border-amber-200 bg-amber-50 px-3.5 py-3 text-sm text-amber-800">
                        Chưa thanh toán tiền hàng. Toàn bộ giá trị phiếu ({{ formatNumber(total) }} đ) sẽ được cộng vào công nợ nhà cung cấp.
                    </div>

                    <!-- Thông tin tài khoản NCC -->
                    <div v-if="settlement === 'bank'" class="space-y-3 rounded-xl border border-blue-100 bg-blue-50/50 p-3.5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-blue-900">Thông tin tài khoản nhận</span>
                            <span v-if="selectedBankAccount?.bank_name" class="text-xs text-blue-600">{{ selectedBankAccount.bank_name }}</span>
                        </div>
                        <select v-if="bankAccounts.length > 1" v-model="selectedBankAccount" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800">
                            <option v-for="account in bankAccounts" :key="account.bank_account" :value="account">{{ account.bank_name || 'Ngân hàng' }} · {{ account.bank_account }}{{ account.is_default ? ' · Mặc định' : '' }}</option>
                        </select>
                        <div v-if="selectedBankAccount?.bank_account" class="rounded-lg border border-blue-100 bg-white p-2.5 text-xs">
                            <p class="font-bold text-slate-800"><span class="select-all">{{ selectedBankAccount.bank_account }}</span> - {{ selectedBankAccount.bank_account_name || '—' }}</p>
                            <p class="mt-0.5 text-slate-500">Nội dung: <span class="select-all font-mono font-medium text-slate-700">{{ paymentReference }}</span></p>
                        </div>
                        <p v-else class="rounded-lg bg-white p-2.5 text-xs text-amber-700">Nhà cung cấp chưa khai báo tài khoản ngân hàng.</p>
                    </div>

                    <!-- Số tiền -->
                    <div v-if="settlement !== 'debt'" class="space-y-3">
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-semibold text-slate-700">
                                {{ settlement === 'cash' ? 'Tiền đưa' : 'Số tiền chuyển' }}
                                <span class="ml-1 text-xs font-normal text-slate-400">(Để trống nếu nợ 100% phiếu này)</span>
                            </label>
                            <span class="text-xs text-slate-400">Đơn vị: VNĐ</span>
                        </div>
                        <div class="relative">
                            <input :value="tenderedDisplay" inputmode="numeric" placeholder="0" class="w-full rounded-xl border-2 border-slate-200 bg-white py-3 pl-4 pr-10 text-right text-xl font-bold text-blue-600 transition focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-500/10" @input="updateTendered" />
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-sm font-bold text-slate-400">đ</span>
                        </div>
                        <div class="flex flex-wrap gap-2 pt-1">
                            <button type="button" class="rounded-lg border border-slate-200 bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-600" @click="setTendered(total)">Trả đủ phiếu ({{ formatNumber(total) }}đ)</button>
                            <button type="button" class="rounded-lg border border-slate-200 bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-amber-200 hover:bg-amber-50 hover:text-amber-700" @click="setTendered(0)">Nợ 100% phiếu (0đ)</button>
                            <button type="button" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-100" @click="addTendered(500000)">+500k</button>
                            <button type="button" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-100" @click="addTendered(1000000)">+1tr</button>
                        </div>
                    </div>

                    <!-- QR chuyển khoản -->
                    <div v-if="settlement === 'bank'" class="space-y-3 rounded-xl border border-blue-100 bg-white p-3.5">
                        <div v-if="bankQrReady">
                            <QrPayment :bank-bin="String(selectedBankAccount.bank_bin)" :bank-account="String(selectedBankAccount.bank_account)" :amount="transferAmount" :description="paymentReference" compact />
                        </div>
                        <p v-else class="text-xs text-amber-700">Cần nhập mã BIN gồm 6 chữ số và số tài khoản để tạo mã QR.</p>
                        <label v-if="isMobile" class="block text-sm font-semibold text-slate-700">
                            Ứng dụng ngân hàng bạn dùng
                            <select v-model="selectedPaymentAppId" class="mt-1.5 h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm font-normal text-slate-800">
                                <option value="">{{ loadingPaymentApps ? 'Đang tải danh sách ứng dụng...' : 'Chọn ứng dụng ngân hàng...' }}</option>
                                <option v-for="app in paymentApps" :key="app.app_id" :value="app.app_id">{{ app.app_name }}</option>
                            </select>
                        </label>
                        <p v-if="paymentAppError" class="text-xs text-rose-600">{{ paymentAppError }}</p>
                        <p v-else-if="isMobile && !loadingPaymentApps && !paymentApps.length" class="text-xs text-amber-700">Danh sách ứng dụng ngân hàng hiện không khả dụng.</p>
                        <p v-else-if="isMobile && !selectedPaymentAppId" class="text-xs text-slate-500">Chọn app ngân hàng đã cài trên điện thoại để mở sẵn thông tin chuyển khoản.</p>
                        <a v-if="isMobile && paymentDeepLink" :href="paymentDeepLink" class="inline-flex min-h-10 items-center rounded-lg bg-emerald-600 px-4 text-sm font-bold text-white hover:bg-emerald-700">Mở {{ selectedPaymentApp?.app_name }} để chuyển khoản</a>
                    </div>

                    <!-- Trả nợ đơn cũ -->
                    <div v-if="settlement !== 'debt' && oldDebt > 0" class="overflow-hidden rounded-xl border border-amber-200 bg-amber-50/40">
                        <div class="flex cursor-pointer select-none items-center justify-between p-3.5" @click="toggleOldDebt(!payOldDebt)">
                            <div class="flex items-center gap-3">
                                <input :checked="payOldDebt" type="checkbox" class="h-5 w-5 cursor-pointer rounded border-amber-400 text-amber-600 focus:ring-amber-500" @click.stop @change="toggleOldDebt($event.target.checked)" />
                                <div>
                                    <span class="block text-sm font-bold text-amber-900">Trả nợ đơn cũ</span>
                                    <span class="text-xs text-amber-700">Công nợ hiện tại: <strong class="font-semibold text-amber-800">{{ formatNumber(oldDebt) }} đ</strong></span>
                                </div>
                            </div>
                            <ChevronDown :size="16" class="text-amber-600 transition-transform duration-200" :class="payOldDebt && 'rotate-180'" />
                        </div>

                        <div v-if="payOldDebt" class="space-y-2.5 border-t border-amber-200/60 px-3.5 pb-3.5 pt-2">
                            <div class="flex items-center justify-between py-1 text-xs font-medium text-amber-800">
                                <span>Danh sách phiếu chưa thanh toán hết:</span>
                                <button type="button" class="flex items-center gap-1 font-semibold text-blue-600 hover:underline" @click="autoDistribute">
                                    <WandSparkles :size="11" /> Tự động phân bổ
                                </button>
                            </div>
                            <div v-if="loadingOldDebtOrders" class="text-xs text-amber-700">Đang tải các phiếu còn nợ...</div>
                            <div v-else-if="!oldDebtOrders.length" class="text-xs text-amber-700">Không có phiếu nhập còn nợ để chọn.</div>
                            <div v-else class="spm-scroll max-h-60 space-y-2 overflow-y-auto pr-1">
                                <div v-for="order in oldDebtOrders" :key="order.id" class="flex items-center justify-between gap-2 rounded-lg border border-slate-200/80 bg-white p-2.5 shadow-sm transition hover:border-amber-300">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <input v-model="order.selected" type="checkbox" class="h-4 w-4 cursor-pointer rounded border-slate-300 text-amber-600 focus:ring-amber-500" @change="!order.selected && (order.payment_amount = 0)" />
                                        <div class="min-w-0">
                                            <span class="block truncate font-mono text-xs font-bold text-slate-800">{{ order.code }}</span>
                                            <div class="text-[11px] text-slate-500">{{ order.date }} · Còn: <strong class="font-semibold text-slate-700">{{ formatNumber(order.remaining) }} đ</strong></div>
                                        </div>
                                    </div>
                                    <input :disabled="!order.selected" :value="order.payment_amount ? formatNumber(order.payment_amount) : ''" inputmode="numeric" placeholder="Số trả" class="w-32 shrink-0 rounded-lg border border-slate-200 px-2 py-1.5 text-right text-xs font-bold text-amber-700 focus:border-amber-500 focus:outline-none disabled:bg-slate-50 disabled:text-slate-400" @input="updateOldDebt(order, $event)" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bảng tính nhanh -->
                    <div v-if="settlement !== 'debt'" class="space-y-2 rounded-xl bg-slate-900 p-4 text-xs text-slate-200 shadow-inner">
                        <div class="flex items-center justify-between text-slate-300">
                            <span>Thanh toán phiếu mới:</span>
                            <span class="font-mono font-semibold text-white">{{ formatNumber(paidAmount) }} đ</span>
                        </div>
                        <div class="flex items-center justify-between text-slate-300">
                            <span>Thanh toán trả nợ cũ:</span>
                            <span class="font-mono font-semibold text-amber-400">{{ formatNumber(oldDebtPaid) }} đ</span>
                        </div>
                        <div v-if="settlement === 'cash'" class="flex items-center justify-between border-t border-slate-800 pt-2 text-slate-300">
                            <span>Tiền thừa trả lại:</span>
                            <span class="font-mono font-semibold text-emerald-400">{{ formatNumber(changeAmount) }} đ</span>
                        </div>
                    </div>
                </div>

                <!-- FOOTER -->
                <div class="shrink-0 space-y-3 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="block text-xs text-slate-500">Công nợ còn lại sau phiếu</span>
                            <span class="text-xs text-slate-400">(Nợ cũ + Phiếu mới − Tổng trả)</span>
                        </div>
                        <span class="font-mono text-xl font-extrabold" :class="dueAmount > 0 ? 'text-red-600' : 'text-emerald-600'">{{ formatNumber(dueAmount) }} đ</span>
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-1">
                        <button type="button" class="rounded-xl border border-slate-300 px-4 py-2.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-100" @click="emit('close')">Hủy bỏ</button>
                        <button type="button" class="flex items-center gap-1.5 rounded-xl bg-blue-600 px-5 py-2.5 text-xs font-bold text-white shadow-lg shadow-blue-500/25 transition hover:bg-blue-700" @click="submit">
                            <Check :size="14" /> Xác nhận và lưu phiếu
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </Teleport>
</template>

<style scoped>
.spm-scroll::-webkit-scrollbar { width: 6px; }
.spm-scroll::-webkit-scrollbar-track { background: #f1f5f9; }
.spm-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.spm-scroll::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>
