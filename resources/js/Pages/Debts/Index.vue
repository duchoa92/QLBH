<script setup>
import { computed, ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { HandCoins, Wallet, Users, Truck, X, ChevronDown, ScanLine, History } from 'lucide-vue-next'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import BaseModal from '@/Components/UI/BaseModal.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import SupplierPaymentModal from '@/Pages/Debts/SupplierPaymentModal.vue'
import SaleSearchModal from '@/Components/Search/SaleSearchModal.vue'
import StockImportDetailModal from '@/Pages/StockImports/StockImportDetailModal.vue'
import { formatMoney } from '@/utils/format'
import ListToolbar from '@/Components/UI/ListToolbar.vue'
import ListFilterSelect from '@/Components/UI/ListFilterSelect.vue'
import PaginationBar from '@/Components/UI/PaginationBar.vue'
import HighlightText from '@/Components/UI/HighlightText.vue'
import { useLiveList } from '@/Composables/useLiveList'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    entity: { type: String, required: true },
    rows: { type: Object, required: true },
    filters: { type: Object, default: () => ({ search: '' }) },
    summary: { type: Object, required: true },
    orders: { type: Array, default: () => [] },
    history: { type: Array, default: () => [] },
})

const isCustomer = computed(() => props.entity === 'customer')

// Xem hóa đơn / phiếu nhập ngay tại trang này, không điều hướng.
const viewOrderId = ref(null)
const openOrder = (id) => { if (id) viewOrderId.value = id }
const title = computed(() => isCustomer.value ? 'Thu công nợ khách hàng' : 'Thanh toán công nợ nhà cung cấp')
const search = ref(props.filters.search || '')
const level = ref(props.filters.level || '')
const levelOptions = [
    { value: '', label: 'Mọi mức nợ' },
    { value: 'lt1m', label: 'Dưới 1 triệu' },
    { value: '1m_5m', label: '1 – 5 triệu' },
    { value: '5m_20m', label: '5 – 20 triệu' },
    { value: 'gt20m', label: 'Trên 20 triệu' },
]
const selected = ref(null)
const supplierModal = ref(null) // { supplier, orderId }
const expandedId = ref(null)
const scanCode = ref('')
const scanMessage = ref('')
const error = ref('')
const form = useForm({ amount: '', payment_method: 'cash', note: '', order_id: '' })

// Tìm kiếm bằng AJAX, không tải lại trang; kết quả được bôi vàng.
const list = useLiveList({
    props,
    dataKey: 'rows',
    extraKeys: ['orders'],
    params: () => ({ search: search.value.trim(), level: level.value }),
})
const { data: rowsData, filters: filtersData, extras, loading } = list
const ordersData = extras.orders
watch(search, () => list.schedule())
watch(level, () => list.request())

const nameOf = row => isCustomer.value ? row.full_name : row.name
const pay = (row, order = null) => {
    // Nhà cung cấp: dùng modal thanh toán riêng (chia tiền cho nhiều phiếu nhập)
    if (!isCustomer.value) {
        const target = order || null
        supplierModal.value = { supplier: row, orderId: target?.id || '' }
        return
    }

    order = order || ordersData.value.find(item => Number(item.party_id) === Number(row.id)) || null
    selected.value = row
    error.value = ''
    form.reset()
    form.payment_method = 'cash'
    form.amount = Number(Math.min(Number(order?.due ?? row.debt_balance), Number(row.debt_balance))).toFixed(0)
    form.order_id = order?.id || ''
    scanCode.value = ''
    scanMessage.value = ''
    if (order) form.amount = Number(order.due).toFixed(0)
}
const partyOrders = computed(() => selected.value ? ordersData.value.filter(order => Number(order.party_id) === Number(selected.value.id)) : [])
const scanOrder = () => {
    const code = scanCode.value.trim().toLowerCase()
    if (!code) return
    const match = partyOrders.value.find(order => order.code.toLowerCase() === code || order.code.toLowerCase().includes(code))
    if (!match) { scanMessage.value = 'Không tìm thấy đơn nợ phù hợp với mã vừa quét.'; return }
    form.order_id = match.id
    form.amount = Number(Math.min(Number(match.due), Number(selected.value.debt_balance))).toFixed(0)
    scanMessage.value = `Đã chọn ${match.code} · còn nợ ${formatMoney(match.due)}`
}
const submit = () => {
    error.value = ''
    const url = isCustomer.value
        ? route('debts.customers.payments', selected.value.id)
        : route('debts.suppliers.payments', selected.value.id)
    form.post(url, {
        preserveScroll: true,
        onSuccess: () => { selected.value = null },
        onError: errors => { error.value = errors.amount || Object.values(errors)[0] || 'Không thể ghi nhận thanh toán.' },
    })
}
</script>

<template>
    <div class="space-y-3">
        <PageHeader :title="title" :description="isCustomer ? 'Theo dõi số dư và ghi nhận các lần khách trả nợ.' : 'Theo dõi số dư phải trả và ghi nhận các lần thanh toán nhà cung cấp.'" />

        <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center gap-3">
                    <span class="rounded-xl bg-amber-50 p-3 text-amber-600"><Wallet :size="20" /></span>
                    <div><div class="text-xs font-semibold text-slate-500">{{ isCustomer ? 'Tổng khách hàng còn nợ' : 'Tổng công nợ phải trả' }}</div><div class="mt-1 text-xl font-black text-slate-900">{{ formatMoney(summary.total) }}</div></div>
                </div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center gap-3">
                    <span class="rounded-xl bg-blue-50 p-3 text-blue-600"><component :is="isCustomer ? Users : Truck" :size="20" /></span>
                    <div><div class="text-xs font-semibold text-slate-500">{{ isCustomer ? 'Khách hàng đang nợ' : 'Nhà cung cấp còn công nợ' }}</div><div class="mt-1 text-xl font-black text-slate-900">{{ summary.count }}</div></div>
                </div>
            </div>
        </div>

        <ListToolbar v-model="search" :loading="loading" @submit="list.request()" :placeholder="isCustomer ? 'Tìm tên, mã hoặc số điện thoại...' : 'Tìm nhà cung cấp, mã hoặc số điện thoại...'">
            <template #filters>
                <ListFilterSelect v-model="level" label="Mức nợ" :show-placeholder="false" :options="levelOptions" />
            </template>
        </ListToolbar>
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="app-data-table w-full min-w-[720px] border-collapse text-left text-xs">
                    <thead><tr class="select-none border-b border-slate-200 bg-slate-50/80 text-[11px] font-extrabold uppercase tracking-wider text-slate-500"><th class="px-3.5 py-2.5">{{ isCustomer ? 'Khách Hàng' : 'Nhà Cung Cấp' }}</th><th class="px-3.5 py-2.5">Mã</th><th class="px-3.5 py-2.5">Điện Thoại</th><th class="px-3.5 py-2.5 text-right">Còn Nợ</th><th class="px-2 py-2.5 text-center">Thao Tác</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 text-xs font-medium">
                        <template v-for="row in rowsData.data" :key="row.id">
                            <tr class="transition-colors hover:bg-emerald-50/70"><td class="px-3.5 py-2.5 font-bold text-slate-900"><HighlightText :text="nameOf(row)" :keyword="search" /></td><td class="px-3.5 py-2.5 text-slate-600"><HighlightText v-if="row.code" :text="row.code" :keyword="search" /><template v-else>—</template></td><td class="px-3.5 py-2.5 text-slate-600"><HighlightText v-if="row.phone" :text="row.phone" :keyword="search" /><template v-else>—</template></td><td class="px-3.5 py-2.5 text-right text-sm font-black text-rose-600">{{ formatMoney(row.debt_balance) }}</td><td class="px-3.5 py-2.5 text-center"><div class="inline-flex items-center justify-center gap-1"><button type="button" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="expandedId = expandedId === row.id ? null : row.id"><ChevronDown :size="14" :class="expandedId === row.id ? 'rotate-180' : ''" />Đơn nợ</button><ActionButton @click="pay(row)"><HandCoins :size="15" />{{ isCustomer ? 'Ghi nhận thu' : 'Thanh toán' }}</ActionButton></div></td></tr>
                            <tr v-if="expandedId === row.id"><td colspan="5" class="bg-slate-50 px-4 py-3"><div class="space-y-2"><div class="text-xs font-bold uppercase tracking-wide text-slate-500">Các {{ isCustomer ? 'hóa đơn' : 'phiếu nhập' }} còn nợ</div><div v-for="order in orders.filter(item => Number(item.party_id) === Number(row.id)).reverse()" :key="order.id" class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white p-3"><div class="min-w-[220px]"><button type="button" class="font-bold text-blue-700 hover:underline" @click="openOrder(order.id)">{{ order.code }}</button><div class="mt-1 text-xs text-slate-500">{{ order.date }} · Tổng {{ formatMoney(order.total) }} · Đã thanh toán {{ formatMoney(order.paid) }}</div></div><div class="flex items-center gap-3"><span class="text-sm font-bold text-rose-600">Còn {{ formatMoney(order.due) }}</span><button type="button" class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-bold text-white hover:bg-blue-700" @click="pay(row, order)">Thanh toán đơn này</button></div></div><p v-if="!orders.some(item => Number(item.party_id) === Number(row.id))" class="text-sm text-slate-500">Chưa có hóa đơn/phiếu nhập nợ riêng để đối chiếu. Có thể ghi nhận khoản thanh toán tổng bằng nút phía trên.</p></div></td></tr>
                        </template>
                        <tr v-if="!rowsData.data.length"><td colspan="5" class="px-4 py-12 text-center text-slate-500">{{ search || level ? 'Không tìm thấy kết quả phù hợp.' : 'Hiện không có công nợ.' }}</td></tr>
                    </tbody>
                </table>
            </div>
            <PaginationBar :data="rowsData" :filters="filtersData" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-100 p-4"><History :size="18" class="text-blue-600" /><div><h2 class="font-bold text-slate-900">Lịch sử thanh toán gần đây</h2><p class="mt-1 text-xs text-slate-500">Hiển thị tối đa 100 lần thu/chi mới nhất.</p></div></div>
            <div class="overflow-x-auto"><table class="app-data-table w-full min-w-[760px] border-collapse text-left text-xs"><thead><tr class="select-none border-b border-slate-200 bg-slate-50/80 text-[11px] font-extrabold uppercase tracking-wider text-slate-500"><th class="px-4 py-3">Ngày giờ</th><th class="px-4 py-3">{{ isCustomer ? 'Khách hàng' : 'Nhà cung cấp' }}</th><th class="px-4 py-3">Đơn liên quan</th><th class="px-4 py-3">Hình thức</th><th class="px-4 py-3">Ghi chú</th><th class="px-4 py-3 text-right">Số tiền</th></tr></thead><tbody class="divide-y divide-slate-100 text-xs font-medium"><tr v-for="item in history" :key="item.id" class="transition-colors hover:bg-emerald-50/70"><td class="px-4 py-3 text-slate-600">{{ item.date }}</td><td class="px-4 py-3 font-semibold">{{ item.party || '—' }}</td><td class="px-4 py-3"><button v-if="item.code && item.source_id" type="button" class="font-semibold text-blue-700 hover:underline" @click="openOrder(item.source_id)">{{ item.code }}</button><span v-else class="text-slate-400">{{ item.code || 'Thanh toán tổng' }}</span></td><td class="px-4 py-3 text-slate-600">{{ { cash: 'Tiền mặt', bank: 'Chuyển khoản', card: 'Thẻ' }[item.method] || item.method }}</td><td class="max-w-[220px] truncate px-4 py-3 text-slate-500">{{ item.note || '—' }}</td><td class="px-4 py-3 text-right font-bold text-emerald-700">{{ formatMoney(item.amount) }}</td></tr><tr v-if="!history.length"><td colspan="6" class="px-4 py-8 text-center text-slate-500">Chưa có lịch sử thanh toán.</td></tr></tbody></table></div>
        </div>

        <SupplierPaymentModal
            v-if="supplierModal"
            :key="`${supplierModal.supplier.id}-${supplierModal.orderId}`"
            :supplier="supplierModal.supplier"
            :orders="orders.filter(item => Number(item.party_id) === Number(supplierModal.supplier.id))"
            :history="history.filter(item => Number(item.party_id) === Number(supplierModal.supplier.id))"
            :initial-order-id="supplierModal.orderId"
            @close="supplierModal = null"
        />

        <BaseModal v-if="selected" :title="isCustomer ? 'Ghi nhận khách trả nợ' : 'Thanh toán cho nhà cung cấp'" size="md" @close="selected = null">
            <p class="mb-4 text-sm text-slate-500">{{ nameOf(selected) }} · Còn nợ {{ formatMoney(selected.debt_balance) }}</p>
            <div class="mb-3 rounded-xl border border-blue-100 bg-blue-50/60 p-3"><label class="block text-xs font-bold text-slate-700">Quét mã hóa đơn / phiếu nhập<input v-model="scanCode" autofocus @keyup.enter.prevent="scanOrder" class="mt-1.5 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-400" placeholder="Quét mã rồi nhấn Enter" /></label><div class="mt-2 flex items-center justify-between gap-2"><span class="text-xs" :class="scanMessage.startsWith('Đã chọn') ? 'text-emerald-700' : 'text-slate-500'">{{ scanMessage || 'Quét mã để gắn khoản thu/chi vào đúng đơn nợ.' }}</span><button type="button" class="inline-flex shrink-0 items-center gap-1 rounded-lg bg-blue-600 px-3 py-2 text-xs font-bold text-white" @click="scanOrder"><ScanLine :size="14" />Tìm đơn</button></div><select v-if="partyOrders.length" v-model="form.order_id" class="mt-3 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"><option value="">Thanh toán công nợ tổng</option><option v-for="order in partyOrders" :key="order.id" :value="order.id">{{ order.code }} — còn {{ formatMoney(order.due) }}</option></select></div>
            <label class="mb-3 block text-sm font-semibold text-slate-700">Số tiền (đ)<input v-model="form.amount" type="number" min="1" step="1" required class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 outline-none focus:border-blue-400" /></label>
            <label class="mb-3 block text-sm font-semibold text-slate-700">Phương thức<select v-model="form.payment_method" class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5"><option value="cash">Tiền mặt</option><option value="bank">Chuyển khoản</option><option value="card">Thẻ</option></select></label>
            <label class="mb-3 block text-sm font-semibold text-slate-700">Ghi chú<input v-model="form.note" maxlength="1000" class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 outline-none focus:border-blue-400" placeholder="Không bắt buộc" /></label>
            <p v-if="error" class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ error }}</p>
            <template #footer>
                <div class="flex justify-end gap-2"><button type="button" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold" @click="selected = null">Hủy</button><button type="button" :disabled="form.processing" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white disabled:opacity-50" @click="submit">{{ form.processing ? 'Đang lưu…' : 'Xác nhận' }}</button></div>
            </template>
        </BaseModal>

        <template v-if="viewOrderId">
            <SaleSearchModal v-if="isCustomer" :sale-id="viewOrderId" @close="viewOrderId = null" />
            <StockImportDetailModal v-else :import-id="viewOrderId" @close="viewOrderId = null" />
        </template>
    </div>
</template>
